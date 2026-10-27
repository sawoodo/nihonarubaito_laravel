# Build: duplicate check at XML import (and manual create)

Goal: stop translators creating a job we already have. On Oct 24–26, 41 of 157 new jobs (26%, strict count) were repeats of a live job or of one retired in the last 60 days. The import already has a duplicate check (`JobDeduplicator::findByApplyLink()` + `findDuplicate()`), but it misses most real repeats:

| Today | Misses because |
|---|---|
| exact `apply_link`, status 2/3 only | shigotoin rotates offer ids; tracking params; no link history; expired jobs never checked |
| company + prefecture + first 30 chars of title | "Izakaya Hall and Kitchen" vs "Izakaya Hall & Kitchen" |
| exact company string | full-width vs half-width (鳥メロ　岡山本町店 / 鳥メロ 岡山本町店) |
| statuses 1/2/3 | a job expired 2 weeks ago is invisible |

The rules that are now proven on production data are in `scripts/dedupe-plan.py` (`judge()` and its normalisers; four review passes, batch dd-20261027-072837). This build ports those rules to PHP and uses them at import.

Follow CLAUDE.md for every deploy (commit → push → scp → md5 → config cache left built). Work in a git worktree on a branch. Show raw output at every gate.

---

## Gate 0 — read-only survey, then STOP

Report, with file:line:
1. Every call site of `JobDeduplicator::findByApplyLink`, `findDuplicate`, `refreshExisting` (single XML import, bulk XML import, manual create in `Admin/JobController`).
2. What the single-import warning card shows and what its buttons do (`resources/views/admin/jobs/create-from-xml.blade.php`, `admin/jobs/create.blade.php`).
3. The fields `createJobArray()` produces that the rules need: `company_name`, `address`, `station`, `job_category_id`, `wage`, `title`, `apply_link`, `prefecture_id`. Confirm company and address are the Japanese source text.
4. Confirm `refreshExisting()` has no caller (it sets `date = now()`, which would fake a fresh "date posted"; if it is unused, leave a comment saying never to call it as is).
5. Row counts the matcher will scan: published + pending + quota-full, and expired/trashed with `COALESCE(Expire_Date, updated_at) >= NOW() - INTERVAL 60 DAY`, per prefecture (top 5).

STOP.

## Gate 1 — the matcher (PHP), plus a parity test against Python

New class `app/Services/JobMatcher.php`. Pure functions, no DB in the normalisers:

- `normCompany`, `normAddr` (with the kanji 丁目 step first), `stationKey`, `wageKey` (starting wage), `rolePart` + `titleSim` (word-set Jaccard on the role part), `normLink`, `sourceId` — each a line-for-line port of the Python of the same name in `scripts/dedupe-plan.py` / `scripts/translator-dup-check.py`. Keep the Python constants: `TITLE_MIN 0.80`, `TITLE_SAME_LISTING_DIFFERS 0.90`, `SAME_LISTING_ROLE_MIN 0.50`.
- `judge(array $a, array $b): ?string` → `'certain' | 'review' | null`, identical logic to Python `judge()`.
- `findMatches(array $new): array` → candidates, each `['job' => row, 'verdict' => certain|review, 'reason' => 'same listing' | 'same shop + role' | 'same station + role', 'status_label', 'retired_days_ago']`, certain first, then live before retired, then newest.
  Candidate set (one query each, then judge in PHP):
  1. jobs whose normalised `apply_link` or `sourceId` equals the new one — any status — **plus** jobs whose `job_apply_link_history.old_apply_link` / `new_apply_link` matches;
  2. jobs in the same prefecture and category with status 2/3/6, or 4/5 retired within 60 days.
  Keep only verdicts `certain` and `review`.

**Parity test (blocking):** a small CLI script (e.g. `scripts/matcher-parity.php`, plain PHP that requires the class, no Laravel boot) that reads `~/nihon-reports/dedupe-20261027-0137/published.tsv`, runs `judge()` on every pair `scripts/dedupe-plan.py` compared (bucketed the same way), and diffs the PHP verdicts against the Python verdicts. **Must be 0 differences.** Also unit-check these known cases: 162280/163986 certain · Hotel Emion six → none certain · 141386/158167 certain · 荒本新町/菱江 not certain · Dimple office "Family Restaurant Cashier" vs "Shrimp Cracker Shop" not certain.

STOP with the parity result.

## Gate 2 — wire it into the imports (branch only, not deployed)

**Single XML import** (`CreateJobFromXmlController`, where `findByApplyLink`/`findDuplicate` run today): replace both with `JobMatcher::findMatches()`. If any `certain` match:
- show a card: **"This looks like job N"** — status (Published / Pending / Quota full / Expired 12 days ago / Trashed 30 days ago), its title, company, address, station, wage, current apply link, the reason;
- buttons:
  - **Update job N with this link** — live job: set `apply_link` to the new link (row in `job_apply_link_history`, source `xml-import`), `delete_at` = later of its own and the new default; retired ≤ 60 days: also status → 3 and a `job_status_history` row (source `xml-import`, reason `revived instead of re-translated`). Never touch `date` (date posted). Then go to job N's edit page with a success message.
  - **It's a different job — create anyway** — requires a short reason (text box, required); the new job is created as today and the reason is written to `job_status_history` for the new job (source `xml-import`, reason `created despite match with N: <text>`).
- `review` matches only (no `certain`): a quieter notice listing them, creation allowed, no reason needed.

**Bulk XML import**: for each job with a `certain` match, keep today's behaviour (save as Draft) but prefix the title with `[SAME AS N] ` instead of `[POSSIBLE DUPLICATE]`, and include `same as N: <reason>` in the response message list. Never auto-update anything in bulk.

**Manual create** (`Admin/JobController`): same card as single import.

Keep `skip_duplicate_check` only for "create anyway" (with the reason). Remove the one-click bypass.

## Gate 3 — test on real recent data (read-only on prod)

Run the matcher (read-only, from the branch on the Mac against a fresh export, or via the parity script's data) on every job created in the last 7 days. Report:
- how many would have shown a `certain` card, and compare with `/check-translator-duplicates 7`;
- 10 random `certain` cases with both titles, and 5 `review`-only cases;
- 0 cards for the 10 most recent jobs that the duplicate check called UNIQUE (spot-check by eye).

STOP for my OK.

## Gate 4 — deploy

After my OK: merge the branch, commit, push, deploy the changed files (md5 each), leave the config cache built. Then live tests in admin, using a real XML of an existing published job (e.g. re-paste job 164025's XML) — **do not click any button that creates or updates**:
1. Upload → the card appears naming 164025 with reason "same listing".
2. The next real new job a translator imports (not a test upload — a single import with no card creates the job) → no card, created normally. Check it in admin afterwards.

For the Python side of the parity test, import the functions from `scripts/dedupe-plan.py` (it loads `translator-dup-check.py` by path) and write each pair's verdict to a TSV; the PHP script writes the same TSV; `diff` them.
Report, add a ledger entry draft. Do not commit the ledger unless I ask.
