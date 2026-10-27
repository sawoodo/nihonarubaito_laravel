# Build: every retirement records its date (expired-job policy, change 1)

**Problem.** The expired-page tiers (`JobController::handleExpiredJob()`) count days from `Expire_Date ?? updated_at ?? date`. Only the per-job **Expire** button, Link Check, `jobs:auto-trash --expire`, the bulk-expire SQL and the dedupe merge write `Expire_Date`. Other retirement paths (bulk trash, the Expiring page, the Duplicates page, the per-job **Trash** button) leave it NULL, and some of them use `DB::table()->update()`, which does not touch `updated_at` either. So a job trashed last week can be dated by an edit months ago and **skip the 90-day closed page, going straight to a 301 or 410.**

**Evidence (GSC, 26 Jul–25 Oct, `/jobs/` pages):** 201 retired pages with 740 clicks sit in the "retired 91–365 days" bucket, including 141660 (bed making, Hakata, 41 clicks, now 301) and 141766 (bed making, Osaka, 26, now 301). A page that had really been redirecting for 9 months would not be earning those clicks.

Follow CLAUDE.md for deploys. Read-only until Gate 2. Raw output at every gate.

---

## Gate 0 — survey (read-only), then STOP

1. **Every code path that sets `job_status_id` to 4 or 5**, with file:line and whether it sets `Expire_Date`, bumps `updated_at`, and writes `job_status_history`. Search for the behaviour, not one spelling: `job_status_id`, `STATUS_EXPIRED`, `STATUS_TRASHED`, literal `=> 4` / `=> 5`, raw SQL in scripts. Known so far: `Admin/JobController::trash()` (~:495) and its bulk status change; `AnalyticsController::trashExpiring()` (~:727) and duplicates keep-and-trash (~:1202); `LinkCheckController` (~:276 group trash, ~:326 row expire); `AutoTrashJobs`; `scripts/build-linkcheck-files.py`; `scripts/dedupe-plan.py`.
2. **TRIGGER privilege:** `SHOW GRANTS FOR CURRENT_USER()` and whether any triggers exist (`SHOW TRIGGERS LIKE 'jobs'`). Report only, create nothing.
3. **How wrong the clock is today.** For status 4/5 jobs with `Expire_Date IS NULL`, compare the current tier (from `updated_at ?? date`) with the tier a better date would give: `GREATEST(updated_at, LEAST(delete_at, NOW()))` — the review date is usually when a job was retired, and capping it at now keeps it from pointing into the future. Count how many jobs would move from Tier 2 (301) or Tier 3 (410) back to Tier 1, and how many of the 542 retired pages in the GSC set (Oct 27 export, `/jobs/` filter) are among them. List the top 20 by GSC clicks.
4. **Exact dates already known:** count status 4/5 jobs with `Expire_Date IS NULL` that have a `job_status_history` row (22 Oct onward) giving the exact retirement time.

STOP.

## Gate 1 — the fix (branch, not deployed)

1. **One helper** for every retirement, e.g. `App\Support\JobRetirement::retire(array $jobNos, int $toStatus, string $source, string $actor, string $reason, ?string $batch = null)`: in one transaction, guarded on the job still being live (2, 3 or 6), it sets `job_status_id`, `Expire_Date = NOW()`, `updated_at = NOW()`, and writes one `job_status_history` row per job actually changed (count the rows from the same guarded set, never `ROW_COUNT()` alone).
2. **Route every app path from Gate 0 through it.** Keep each screen's behaviour and messages the same, only the record-keeping changes. Scripts that already write `Expire_Date` + history (link-check generator, dedupe planner, auto-trash `--expire`) stay as they are.
3. **Tier date** in `handleExpiredJob()`: `Expire_Date ?? GREATEST(updated_at, LEAST(delete_at, now())) ?? date`, with a comment saying why (the fallback errs towards "retired later", so a page gets its closed-page period rather than an early 301). Only used when `Expire_Date` is NULL, i.e. for old rows.
4. **No trigger** unless Gate 0 shows the privilege and I say so. The app path is the source of truth; a trigger would be a hidden second writer.

Tests before STOP: `php -l`; render-through-engine check for any touched Blade; a local or read-only proof that each rewritten path calls the helper (grep listing); and for item 3, the tier each of the Gate 0 top-20 pages would get under the new rule.

STOP with the diff summary.

## Gate 2 — backfill exact dates (only after my OK)

For status 4/5 jobs with `Expire_Date IS NULL` and a `job_status_history` retirement row: set `Expire_Date` to that row's `created_at`. Guarded (`Expire_Date IS NULL` and status unchanged), backup with `--complete-insert` first, count before/after, rollback SQL ready. No other backfill: for rows without history the new tier-date fallback does the work, and invented dates would be worse than a documented fallback.

## Gate 3 — deploy and verify

CLAUDE.md deploy (atomic `.new` + rename, md5, config cache left built). Then:
- curl (browser UA, no `-L`) 141660 and 141766 and 3 more from the Gate 0 list → expect whatever tier the new rule gives, and report it;
- don't trash test jobs to prove the paths; prove it on real work instead: the next job the team retires by hand gets `Expire_Date` + a history row (check the next day);
- homepage + a published job control unchanged; laravel.log no new errors.
Ledger draft; don't commit unless asked.

**Note on the 301 → 200 flip.** Pages Gate 0 finds moving from 301 back to the closed page (200 + noindex) will change response for Google. That's intended (they get their alternatives page), but report the number before deploying so the size of the change is known.
