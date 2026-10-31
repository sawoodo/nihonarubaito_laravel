# Nihon Arubaito — Engineering Ledger

Decisions and hard-won lessons for nihonarubaito.com. This records why things are the way they are and what already went wrong, so a future session (or the terminal agent) doesn't relearn it the hard way. Keep entries lean — reusable lessons only, not a changelog. Newest at top.

---

## 2026-10-31 — Lawson can be checked after all; two upload files were left behind

**Lawson.** Every entry URL landed on `/errors/408`, which read as a block. It was
a missing session cookie: one curl with cookies on follows the redirect to the real
form. Closed pages say 「現在公開されていません」 (retired jobs 162694 and 162532
were the controls), and an open form names the store. An open job ends on the
session page `/entry/entryform`, so OPEN rows keep our URL, the same protection the
7-Eleven links needed on 2026-10-30. `company_name` is often the franchise operator,
so a store is checked against the address: 24 of 51 matched by name, 27 by address.

First run: 54 due, 51 open, 3 closed, 0 errors. 159373, expired 25 Oct with no
history row, still had an open form and no twin at its address, so it was revived to
7 Nov, not 1 Nov, which would have made it due the next day.

**Left-behind files.** The FamilyMart (322) and sftworks (33) extend files of
2026-10-30 were never applied, and nothing said so: 188 jobs sat past their date.
Both applied 31 Oct. Gate 1 now stops on a file like that. It works row by row,
because a batch doesn't record its file. The Expiring Jobs "today" tile also hid 127
jobs already overdue from the day before.

## 2026-10-31 — station pages list the right station, not any text that contains its name

**Deployed as `e9330c4`.** New `config/stations.php`, `app/Support/StationPattern.php`.

A station page ran `LIKE '%omiya station%'` over seven columns with no word
boundary, so the audit of the 40 sitemap pages found:

- 1,553 placements: 1,169 right, 368 a different station (154 in the wrong
  prefecture), 16 from other fields, and 68 real jobs missed.
- Omiya claimed 178 listings with 45 real: Sannomiya and Utsunomiya end in
  "omiya station". Only 9 of its first 30 cards were Omiya jobs.
- Hirose-dori showed 0 of 29 and was noindexed, while still in the sitemap and
  on the homepage: the page turns the slug's hyphen into a space, so it could
  never match "Hirose-dori Station". Kyoto-Kawaramachi showed 2 of 27 for the
  same reason.
- The suspected cause, passing mentions in descriptions, was almost nothing: 19
  jobs, 6 of them clearly false. 157007 was on the Shibuya page because its
  description says it "is not near Shibuya Station".

Each sitemap station now has hand-written spellings, kanji and prefectures. A page
matches the station field only, with no letter or hyphen before the English name
and no kanji or kana before the Japanese one. Other slugs stay a text search.

Result, 1,553 → 1,234 (390 removed, 71 added): Omiya 40, Funabashi 15, Oyama 18,
Hirose-dori 29, Kyoto-Kawaramachi 27, Hommachi 29, Namba unchanged at 53. Full
before/after with samples: `docs/reports/station-match-2026-10-31.md`.

The prefecture filter also takes 6 real jobs off a page, as specified: 149517,
157169 and 160549 are Tokyo jobs listing a border station, 146538 is in Aichi,
144522 in Fukuoka, 164363 in Kagoshima (a different Sendai). 5 Kyoto Omiya jobs
left on purpose. All stay on their own prefecture and area pages.

**Deploy check:** the two overwritten files matched git; four files by `.new`,
lint and md5, then atomic mv; config cache rebuilt, `key_len=51 db=mysql
env=production debug=false stations=40`. All 40 pages cache-busted: 200, shown
count equal to expected, no noindex. 29 of Omiya's first 30 cards are titled
Omiya. Homepage, prefecture, area, hand-cash, a non-config station, page 2 and
search all 200. `laravel.log` has no entries since Oct 30. Local suite: 67 tests,
1 failure, `ExampleTest`, which fails identically on `main` (no Mix manifest).

**Lesson: a new `config/` file does nothing until `config:cache` runs, and the
page gives no sign.** `stationFor()` returns null against an old cache and the
page quietly stays a text search, so the check after a config deploy has to be a
count that differs between the two.

**Lesson: strip HTML comments before counting links.** The audit reported 28 dead
theme links on every page. They sit inside a comment in `navigation.blade.php`, so
no visitor or crawler ever saw them, and the regex just read through the comment.
The removal is on `station-nav-links` (`3dc6e8d`): tidying, 8.2 KB a page, riding
with the next deploy. `station-explore-nearby` (`9c275ef`) waits about a week so
its effect stays separate from this one, and needs the owner's 28-day GSC export
for the 8 Osaka pages first.

**Found, parked:** `/part-time-jobs-in-kashiwa` 301s to Kashiwara (Osaka) and
`/part-time-jobs-in-oyama` to Nishikasugai-Toyoyama (Aichi): the area-slug bug,
leaving 55 and 19 published jobs with no reachable area page.

---

## 2026-10-31 — the duplicates were coming from the Clone button, so Clone is gone and publishing is checked

**Deployed as `f2022b6`, with the `job_match_discards` migration.**

The owner reported that pressing Back on the import card saved a Draft. It
doesn't: the card renders before any job exists. Gate 0 then blamed the
dropzone, and that was wrong too. The real source only showed up when the rows
were read properly:

- 164337 and 164088 had `updated_by` NULL and `updated_at = date`, so nobody ever
  edited them. That rules out "the link was pasted on the edit page".
- Both had `delete_at = date + 60`: Clone's hard-coded value, and the manual form's
  default, so a pointer rather than proof. Of ~1,150 jobs in 14 days, 2 had that
  offset. Normal imports are +20 or +27.
- The proof: the access log has `GET /admin/jobs/151033/clone` at 20:29:13 on 27 Oct
  and `/164087/clone` at 20:43:39 on 24 Oct, to the second of each job's creation.

Clone copied the apply link, ran no check and logged nothing. The icon sat right
beside Edit on every row, so these look like misclicks.

**Lesson: survey every path that creates a row, not just the ones named "create".
When a theory explains a row, test it against that row's own columns and the
access log before building on it.** `logs/nihonarubaito.com-YYYY-MM-DD.gz` on the
server keeps admin GETs.

### What changed

- **Clone removed:** button, route and `cloneJob()`. The only link to it was the
  button; the deployed JS has none. `fa-clone` on the Duplicates pages is just an icon.
- **Publish check:** `JobMatcher` runs on the two 2→3 paths, the Publish button and
  Update & Publish, excluding the job itself. There is no bulk publish in the app.
  A certain match stops it and shows the card on the edit page, with "Update job N
  with this link and trash this one" or "Publish anyway" (reason ≥ 10 characters,
  admin only, logged as `published despite match with N: …`, source `publish-check`).
  Only jobs leaving status 2 are checked, so Update & Publish on a status 4/5 job
  is not.
- **Discard button** on the import card, every role. Logs to the new
  `job_match_discards` table; writes nothing to `jobs`.
- **`<apply_url>`** is read by both XML paths, and the single-import form's link
  wins over it. `apply_link` stopped being `required` in validation so the fallback
  can fire; no link in either place is still refused.
- `CLAUDE.md` now has the status label mapping. Nothing renamed.

### Traps found on the way

**The XML link was never read.** `createJobArray()` fell back to `$xml->apply_link`,
but the feed carries `<apply_url>`: the owner confirmed the v2 translation format
uses it. The `?:` looked like a working fallback for as long as it has existed.
`<apply_link>` stays accepted.

**`source = 'dedupe'` is a contract, not a label.** `resolveDedupeTrashed()` follows
only rows with that source and `duplicate of job K` in the reason. A trashed copy
keeps its link, so under any other source the next import matches the copy and
offers to revive it. All trashes here use it, including the card's button (batch
prefix `pc-`).

**Removing a route under a catch-all gives a 500, not a 404.** `/admin/jobs/N/clone`
fell into `jobs/{status?}/{langId?}/{userId?}` and threw a TypeError on `int
$langId`. That route now requires numeric `langId` and `userId`. An admin tab left
open across a deploy would have hit this.

**Status-1 `[SAME AS N]` rows are a dead end.** The listing-id query is any-status,
so one would match a later import and show a button `relink()` refuses. `findMatches()`
now skips status 1. Still open: the admin list hides status 1 and `JobRetirement`
won't trash it, so once bulk jobs carry links these rows are visible only in the
upload message, despite the "reviewer can delete the draft" comment in `uploadFile()`.

**A link-only comparison misses a wrong-link duplicate.** Gate 0 paired 164027 with
164028 because they shared a link, and called it "not a duplicate" on company. The
branch's matcher, replayed on the pending queue, found it was a full twin of live
164026 (same company, address, wage, title) carrying 164028's link by mistake.
The link was not moved: it was never 164027's.

### Production actions

| job | what | batch | backup on the server |
|---|---|---|---|
| 164337 | trashed, duplicate of 151033 | `dd-20261030-162127` | `~/exports/pending-dup-trash-20261030-162127/` |
| 164088 | trashed, duplicate of 164087 | same | same |
| 164027 | trashed, duplicate of 164026 | `dd-20261030-220451` | `~/exports/pending-dup-trash-20261030-220451/` |
| 158220 | wage and title 1,040円 → 1,100円 | `wf-20261030-223108` | `~/exports/wage-fix-20261030-223108/` |
| 159998 | wage and title 1,150円 → 1,200円 | same | same |

The three trashed jobs were pending and never public. Each batch has
`precheck.tsv`, `jobs-before.sql`, `apply.sql` and `rollback.sql`. 163965 is left
pending on the owner's decision: it holds 163731's offer link with a different
company and category, so the publish check will not stop it.

The two wages were found while checking the sftworks relinks: the source pages
listed a higher wage than ours. Only `wage`, `title` and `updated_at` changed, and
each job has a `wage-fix` row in `job_status_history`. A title edit changes the
URL: both old URLs 301 to the new ones, checked live. Stale wages are likely more
widespread, so a minimum-wage count and a wage check inside the link check are on
the work list.

### Numbers before deploy

Replay of the check over a production snapshot, taken before 164027 was trashed:
53 of 568 pending jobs would be blocked, mostly old ones, and 3 of the 22 from the
last 14 days. About 27 ms per job locally. Two pending twins block each other until
one is merged or published anyway, and a job made with "create anyway" is asked for
a reason again at publish.

Tests: `tests/Feature/JobMatchCardTest.php`, against a throwaway MySQL database
built from the production schema (setup in the file header), because the matcher
uses `INTERVAL` arithmetic. Render checks cover the jobs list, edit, XML import and
manual create pages as HTML; the built CSS and JS are not in this checkout, so the
card has not been seen styled.

### Deploy and verification

The route constraint was checked first: the only link filling `langId` and
`userId` is the pagination, which uses integers, and 14 days of access logs held no
non-numeric value there except the two Clone clicks.

All 9 files being replaced matched main on production before the swap. Everything went up as
`.new`, then the migration ran on its own with `--path`, then one `mv` chain swapped
the code. Backup: `~/exports/pre-deploy-f2022b6/` (old files, schema, `migrations`
table). Config cache rebuilt, `key_len=51 db=mysql env=production debug=false`.
`laravel.log` stayed at 4,521 lines.

The three browser checks were first run on live code, through the full HTTP kernel
as the super-admin, inside one database transaction that was always rolled back:
re-pasting 164087's XML with the form link empty showed the card with
`new_link` taken from `<apply_url>` and created no job; Discard logged one row and
returned an empty form; publishing 163912 stopped on 163911 with the right buttons.
Counts were identical afterwards. **This does not replace looking at it in a
browser, which is still to do.**

**Three small things that cost time on the way.**
- zsh does not split `$FILES`, so `for f in $FILES` over a plain string is one
  filename. Use an array. The first attempt stopped at its first md5 check with
  nothing live touched, but its cleanup was a `find -name '*.new' -delete` over the
  whole Laravel folder, which matched nothing only by luck. Clean up the exact
  files you uploaded.
- A CLI request through the kernel gets a 419 if the token is read before the session
  has started. Send a GET first, as a browser does.
- Give every verification curl `-m`. One unbounded curl stalled a finished deploy
  for minutes, on a URL that answered in 1.7 s when retried.

## 2026-10-31 — Link Check page fixes deployed: only a MOVED row may rewrite a link

`b91e7d0` (`a2200b8` rebased onto main; only docs had moved) is live:
`LinkCheckController.php` and `MovedTargetMatcher.php`. Admin-only. The four fixes
and why they exist are in the 2026-10-30 entry below.

Gate before deploy: `scripts/matcher-target-tests.php` 23 of 23, and 0 regressions
over the 736 real triples with 6 rows newly verifiable, the same result as before
the rebase. The corpus for that run is still the file made on 30 Oct.

Production's two files matched main's md5 before the deploy, so nothing server-only
was overwritten. Each file went up as `.new`, was linted and md5-checked, then
renamed into place. The previous copies are in `~/exports/*.pre-b91e7d0.php` on the
server. `laravel.log` stayed at 4,521 lines through deploy and verification.

### Verifying an admin page without a browser session

The preview needs a login and a real upload stages a file, so verification ran a
throwaway script on the server: the full HTTP kernel for the GET, as the super-admin
on an in-memory session, then the controller's own `parseResultsCsv()` and
`buildPlan()` by reflection, the same code the upload path runs. `apply()` was never
called. Row counts and `MAX(updated_at)` were taken before and after, and were
identical.

Result for `upload-sftworks-relink-20261030-173528.csv` (15 rows): page 200, 3 apply
(155158, 158220, 159998, all MOVED, relink to the new `work_id`), 12 review ("apply_link
changed since the scan"), 0 wrong target. All three were accepted by
`sameSftworksListing()`, which the controller tries first, so its "15 observations
of one host" limit now covers real relinks.

Applying this does not resolve the three Open items directly. Their stored
`requested_url` stops matching the job's link, and the next page load auto-resolves
them, recorded as `fixed_by_hand`. Expected, though the label is not literally true.

**A 403 from curl after a deploy is not proof of an outage.** Right after this one
the homepage and the Link Check URL both answered 403 to bare curl. With a browser
user agent both were fine (200 at 210 KB; 302 to login), the bare GET recovered
within a minute, and `curl -I` stayed 403 from the proxy. Always verify with `-A`
and a browser UA, and check `laravel.log`, before treating it as a fault.

## 2026-10-30 — the daily check covers five sites, and an extend file nearly rewrote 73 apply links

First real `/daily-link-check` (a day later than planned, so 179 jobs were
already past their review date). Window 3 days, 837 jobs due by 2 Nov. Three
new host profiles shipped (`8daa16e`): FamilyMart, 7-Eleven and sftworks, which
hold **1,974 of the 2,353 published jobs whose apply link is neither shigotoin
nor baitoru** — hosts that had no automated check at all, because translators
no longer check due jobs by hand.

Scanned 732 jobs across five sites in parallel, one process per host:

| host | scanned | OPEN | MOVED | CLOSED | GONE | HELD | ERROR |
|---|---|---|---|---|---|---|---|
| shigotoin | 224 | 153 | 25 | 46 | — | — | — |
| baitoru | 12 | 11 | 1 | — | — | — | — |
| familymart | 343 | 322 | — | 18 | — | — | 3 |
| sej | 89 | 73 | — | 15 | — | — | 1 |
| sftworks | 64 | 33 | — | 15 | 1 | 15 | — |

**95 jobs expired** because their source page said closed (`be-20261030-165620`
46 · `-181108` 18 · `-194316` 15 · `-181131` 16), **4 more** because the link
now opens a different job (`-172529-diffjob`), and **8 trashed as duplicates**
across three merge batches. Published 5,883 → 5,776. Every batch ran
precheck-then-apply with the count matching, and two pages per host were
verified cache-busted as Tier 1 closed pages (200, ~40 KB, `noindex`, no
`JobPosting`).

### The one that would have done damage

The admin Link Check page takes `new_link` from `final_url` for **every**
actionable row and rewrites `apply_link` whenever it differs — OPEN rows
included. Every open 7-Eleven row redirects to
`/entry/entryform;jsessionid=<per-request id>`, so uploading that extend file
would have replaced **73 real job URLs with dead session URLs**. It was caught
by simulating the page's own `buildPlan()` against the file before upload, not
by reading the file.

Two reasons it was not hypothetical: **68 jobs already store a `jsessionid`**
(2 live, 66 retired, pasted in during late 2025), and the page had already
applied four batches that day.

Fixed in the generator, so it protects every host with no deploy: an OPEN row
now carries `requested_url` as its `final_url`, which makes
`new_link == current_link` and the rewrite impossible. The results file still
records where the scan really landed — the scan record stays honest, only the
upload carries the link to keep. The page-side half (`apply()` rewrites only
for MOVED) is on `worktree-linkcheck-page-fixes`, not deployed.

### Lessons

- **A redirect target that another of our jobs already holds is a duplicate,
  not a relink.** 7 of 16 proposed relink targets were already the live
  `apply_link` of a newer job — a translator had re-translated the same
  vacancy after the old offer id rotated. Relinking would have created 7
  duplicate pairs, which is what the dedupe batches exist to remove. Check
  "does any other live job already point at this offer" before every relink.
  One was a three-way (158617 / 162573 / 160807 on one offer).
- **Company + station + wage is not enough to identify an agency job.** Of 17
  rows that agreed on all three, 5 were wrong: two pairs were duplicates of
  each other, and 162405 was a different placement by the same agency at the
  same station for the same wage (family restaurant service vs baked-sweets
  sales). The role has to be compared too.
- **A status code is not a closed signal on FamilyMart.** A dead store answers
  with 「ただいま、この店舗は募集を行っておりません」 on a 404 of 6,745 bytes
  here, and on a 200 of ~7 KB in the 24 Oct browser check. The sentence
  decides, before the code. Never shorten it: 「ございません」 alone sits in the
  boilerplate of every OPEN page and would have expired the whole estate.
- **A quarter of due sftworks jobs rotate their `work_id`** (15 of 64). The
  entry form stops opening but the listing is still advertised — all 15 still
  had an apply button. That is a relink, not an expiry, and the new id is
  printed in the detail page's own title. The verdict is `HELD`, never
  extended and never expired, until the new id is read and the entry URL
  fetched to confirm it opens a form.
- **`size_download` is the transfer size, so it is the compressed length on a
  host that gzips.** sftworks is the only one of the five that does (10,937
  bytes for a page measured at 98,680 uncompressed), which is why its rule
  reads the title and never the size. The FamilyMart 20 KB floor now measures
  the decompressed body, so turning gzip on there cannot silently stop the
  scan.
- **Write nothing to a table before reading its schema.** A merge transaction
  aborted on `Unknown column 'reason'` — `job_apply_link_history` carries
  `http_code, num_redirects, checked_at` instead. It rolled back cleanly
  (verified: published unchanged, 0 history rows) but it cost a run. The same
  review found `checked_at` being written as ISO `2026-10-30T11:03:07Z`; this
  database is **not strict**, so that would have been stored as a zero date
  with only a warning. 13 literals converted.
- **An identical answer for every input is a refusal, not a result** (again).
  Lawson's entry URLs all return the same 20,098-byte `/jobfind-pc/errors/408`
  page — a missing session, not a timeout. A two-step fetch with a cookie jar
  reaches the real form (200, ~7 KB, 応募シート 記入) and a nonexistent id
  gives a clean 404. The *public* detail URL `/jobfind-pc/job/<id>` 404s for
  live jobs as well as dead ones, so a profile built on it would have expired
  all 369 Lawson jobs. Only known-live controls made that visible.
- **`府` is a prefecture suffix and an ordinary city character** — 利府, 府中,
  甲府, 別府, 防府. In `MovedTargetMatcher::places()` this cost matches twice
  over: the city-name class excluded it, and the prefecture regex matched the
  府 inside 利府 and emitted junk (宮城郡利, 町利). Third bug of that family in
  one class; the prefecture is only ever at the start of an address, so take
  it from there and remove it.
- **Parity is measured on verdicts, not on intermediate values.** The first
  parity assertion for that fix ("no address loses a place name") failed on
  16,487 addresses — correctly, because the fix exists to drop junk captures.
  Replaying the old matcher against the new one over 736 real
  (company, address, destination title) triples is the test that means
  something: 0 regressions, 6 newly verifiable.

---

## 2026-10-28 — the XML import was silently dropping formatting, and a CDATA script could reach a raw-rendered page

**Three deploys:** `d14dd3f` listing-card truncation, `28aea03` import rewrite
+ allow-list + four title fixes, `e174160` the job page's line breaks. No
migration in any of them. Spec: `docs/specs/retirement-date.md` is a different
build; this one has no spec file — it grew out of a read-only check.

### What was wrong

`createJobArray()` built the description with `(string) $xml->description`.
**Casting a SimpleXML element to a string returns its TEXT only**, so every
`<br/>` written as real markup was discarded, and the newline-plus-indent
around it collapsed into a run of spaces. Separately, the `<responsibilities>`
lookup tested `$xml->description->responsibilities` while the format puts that
element NEXT TO `<description>` — so the task list was never appended at all.
**4,825 jobs imported in 60 days hold one run-on paragraph with no list and no
line breaks**, and 4,824 of them contain no HTML tag of any kind.

### The hole that mattered more

The new translation format, live from **2026-10-27**, wraps the description in
CDATA. A CDATA section is handed over verbatim by the parser, so its contents
are stored as-is — which is why jobs from the 27th onward do have `<b>` and
`<br/>`. The same is true of `requirement` and `benefits` items, and all three
columns are printed **raw** on the job detail page (`detail.blade.php:103`,
`:202`, `:210`). Proved against the deployed controller:

```
OLD requirement: Bring your card<script>alert('stolen')</script><br />
                 <a href="//evil.example" onclick="x()">Apply here instead</a>
OLD benefits   : <img src=x onerror="alert(2)">Daily pay
```

A live `<script>`, an off-site anchor and an `<img onerror>`, stored and
rendered unescaped on a public page, reachable by whoever writes the XML.

**Audited before deploying: 0 of 123,015 jobs** carry a script, iframe, object,
embed, img, svg, inline handler or anchor. Nothing was exploited; the surface
opened when the format moved to CDATA. Only `<br>` (83,709) and `<b>` (56,008)
are in use, so the allow-list — `br, b, strong, em, ul, li`, attributes removed,
`script/style/iframe/object/embed` deleted **with their contents** — is wider
than reality by design.

### Four lessons

- **`(string) $element` on SimpleXML drops child elements.** It returns text
  nodes only. To keep markup, walk `dom_import_simplexml($el)->childNodes` and
  take a CDATA node's `nodeValue` (its text IS the markup) while serialising
  element children.
- **`cleanXmlInput` must never reach inside a CDATA section.** Its
  `html_entity_decode` turned `&amp;` into `&`, its bare-`&` rule turned it
  back, and the parser then handed that over verbatim — so **either** spelling
  of `&` inside a CDATA title reached the database as the five characters
  `&amp;`, rendering to a visitor as "Hall &amp;". A plain (non-CDATA) title
  was always fine, which is why it was 4 jobs and not 644. CDATA exists so its
  contents are not interpreted; transforms must split on it and skip it.
- **Any column printed raw needs an allow-list at the point of write**, not a
  promise about the feed. `strip_tags` alone is not enough: it keeps the TEXT
  inside a tag it removes, so `<script>alert(1)</script>` leaves `alert(1)`
  sitting in the description as prose. Remove those elements whole first.
- **A byte-based `substr` on text that may contain markup or kanji is a bug
  waiting for the data to change.** The listing card cut at 210 BYTES and
  printed the result raw. With the cut placed deliberately: inside a tag it
  ends `...aaa<b`, inside 駅 it ends mid-codepoint (invalid UTF-8), inside an
  attribute it ends `...<b class="`. Each corrupts every card after it.
  `strip_tags` → `mb_substr` → escape.

### Also

The old descriptions' lost breaks are recoverable: 4,834 of 4,852 tagless
descriptions carry a run of 3+ spaces, 25,500 runs, all but one exactly four
spaces, and **109 of 109 runs in a 20-job sample fall straight after a . ! or
?**. The job page now renders those runs as `<br/>` for tagless descriptions
only, escaping first so the only markup in the output is the `<br/>` it inserts
— strictly safer than the raw print it replaced, and self-disabling as the old
descriptions age out. Stored data, the card, the JobPosting schema and
`og:description` are untouched.

Four published titles holding `&amp;` were corrected (163765, 163474, 159878,
143582), title column only, slugs deliberately left alone. `updated_at` was not
bumped: a data correction is not a human edit, and that column is the only
"last edited by a person" signal this schema has.

**Testing lesson from this session:** a bootstrapped script run on production
writes to the production `laravel.log`. One of my own test runs put a
`ParseError` there at 10:15:00 — my Blade-block extraction had grabbed the
wrong `</small>` and produced an unbalanced `@if`. It looked exactly like a
deploy error in the log. Name test scripts so they are obvious in a stack
trace, and check the trace before reading an entry as production breakage.
---

## 2026-10-27 — every retirement records its date; 2,947 pages stop redirecting early

**What:** `App\Support\JobRetirement` is now the one way a job reaches status 4
or 5. It sets `Expire_Date`, bumps `updated_at`, and writes one
`job_status_history` row per job actually changed, guarded on the job still
being in a status it may be retired from. The expired-page tier falls back to
`GREATEST(updated_at, LEAST(delete_at, now()))` when `Expire_Date` is absent.
Commits `69afd00`, `117f75f`, merged `d35812a`. Spec:
`docs/specs/retirement-date.md`. No migration.

**Why.** The tiers in `JobController::handleExpiredJob()` count days from
`Expire_Date ?? updated_at ?? date`, but only some retirement paths wrote
`Expire_Date`. The per-job Trash button wrote none; both `AnalyticsController`
paths used `DB::table()`, which does not touch `updated_at` either. So a job
trashed last week could be dated by an edit months earlier and **skip its
90-day closed page, going straight to a 301 or a 410**.

Measured: of 108,332 retired jobs, **97,229 had no `Expire_Date`** (96,369 of
96,773 trashed). Against GSC (26 Jul–25 Oct, `/jobs/` filter, 989 job pages,
3,785 clicks), **197 of the 542 retired pages that still earn clicks were
redirecting early**, carrying 727 clicks — 42% of retired-page traffic. Job
141660 (bed making, Hakata) was dated 271 days old and had been retired 20; a
page redirecting for nine months does not earn 41 clicks in 92 days.

**Rolled out:** 2,947 pages move from 301 back to the closed page, 793 from 410
to a 301. All 20 of the worst offenders verified flipping Tier 2 → Tier 1 at
real ages of 5–67 days.

**Four things worth keeping:**

- **A bug you cannot see in the data is still provable.** The 4 → 5 transition
  and its "keep the first retirement date" rule had no failing row to point at,
  so it was tested by running the real write inside a transaction always rolled
  back in a `finally`, re-reading the row afterwards to prove nothing stuck.
  Four cases, history count back to 4,127 each time. **A rolled-back
  transaction is a legitimate test on production** and far better than
  deploying write logic that has never run.
- **`jobs.job_no` has no unique index.** Three legacy CI3-era values are shared
  by two rows each (7vyeO, BtQuB, uOr8M). Found because a test picked a status-4
  row and read back a status-5 one under the same `job_no`. No live row shares
  one, so nothing current could hit it, but every write now keys on `jobs.id`
  — the id of the rows actually read under lock — so a future duplicate cannot
  widen one request into two retirements.
- **`php -l` does not find a missing `use`.** `AnalyticsController` had no
  `App\Models\Job` import while the new code called `Job::STATUS_TRASHED`; the
  file lints clean and would have failed only when that button was pressed.
  After adding a class reference, check the file's imports, not just its syntax.
- **Count a mysqldump's rows by its tuples, not its INSERTs.** `--complete-insert`
  batches many rows per statement, so `grep -c "INSERT INTO"` returned 2 for 713
  rows; and a `[(,]\(` pattern misses the first tuple of each statement, which
  is preceded by `VALUES `. The honest count is
  `VALUES \((\d+),` plus `,\((\d+),` — 2 + 711 = 713, matching the 713 distinct
  ids.

**Behaviour deliberately kept loose:** retiring an already-expired job to
trashed is allowed and **keeps the first `Expire_Date`**, so the 90-day clock
does not restart — both statuses show a visitor the same page, and refusing it
would only confuse the team. `5 -> 4` is refused. And for a job retired before
its review date came due, the fallback pins the age at 0 until `delete_at`
passes, so its clock effectively starts there; that errs only towards keeping
the closed page longer.

**Gate 2 backfill:** 713 jobs — every one from the three `bulk-expire` batches
of 22–24 Oct, which ran on the generator before it wrote `Expire_Date` — took
their exact retirement time from `job_status_history` (471 dated 22 Oct, 242
dated 24 Oct). Guarded on `Expire_Date IS NULL` and status unchanged; 713
backfilled, 0 left. Backup `~/exports/retirement-backfill-20261027-150417/`
(`--complete-insert`, 713 rows verified), rollback alongside it, not run. That
is 0.7% of the 97,229 undated rows — the fallback carries the other 99.3%.

**Verified live:** 141660, 141766 and 154939 now serve 200 + `noindex` at ~40 KB
(were 301); two backfilled pages likewise; one control per tier behaves
(Tier 1 → 200/40 KB, Tier 2 → 301, Tier 3 → 410); published control and
homepage unchanged; `laravel.log` byte-identical, 0 entries today; config cache
still the Oct 24 build.

**Deploy note:** five files up as `<name>.new`, md5-checked and `php -l`-ed
while inert, then renamed — `JobRetirement.php` first because all four
controllers reference it, and `app/Http/Controllers/JobController.php` last
because it serves every public job page.
---

## 2026-10-27 — the XML import now checks whether we already have the job

**What:** `App\Services\JobMatcher` ports the merge rules proven on the whole
published table in `scripts/dedupe-plan.py` (batch dd-20261027-072837, 221
merges) to PHP and runs them at import — single XML, bulk XML and manual create.
A certain match shows a card naming the existing job and offers to give it the
new apply link, reviving it if it was retired within 60 days. Commits `39af369`
(merge) and `51e1355` (a Blade fix found in testing). Spec:
`docs/specs/import-duplicate-check.md`. No migration.

**Why:** the old check could not see most repeats. It matched an exact
`apply_link` on statuses 2–3, or company + prefecture + the first 30 characters
of the title on 1–3. shigotoin rotates its offer ids, so the link match misses;
"Izakaya Hall and Kitchen" and "Izakaya Hall & Kitchen" differ in the first 30
characters; and a job retired last week was invisible. Over 24–26 Oct, 41 of
157 new jobs were repeats it had let through.

**Measured on the last 7 days (553 imports):** 69 would be blocked (12.5%), 145
would get an informational notice, 339 nothing. Three quarters of the blocks
point at a job retired 3–56 days ago — the re-translations the old check could
not see at all. `/check-translator-duplicates 7` flagged 144 on the same
snapshot; the matcher blocks 69 because it also requires the same role and the
same starting wage, which is what keeps agency-office collisions out.

**Four things worth keeping:**

- **Blade will not compile a directive glued to a word character.** `ago@endif`
  stayed in the output as literal text, the enclosing `@if` was left open, and
  the compiled view became a PHP parse error. Nothing on the source catches it:
  `php -l` does not read Blade, the directives balance when counted, and a
  hand-written preview renderer compiled it happily. It would have surfaced in
  production the first time a card actually appeared — the page loads fine until
  there is a match to show. **Test a Blade change by rendering it through the
  engine, not by reading it.** One `php` script that boots the app and calls
  `View::make(...)->render()` is enough, and it is read-only.
- **A card must never point at a job we merged away.** The dedupe batch left 221
  trashed copies that still hold the apply link and address that matched, so a
  fresh import matched the *copy*; "Update job <copy>" would have revived it and
  recreated by hand the duplicate the merge removed. Candidates with a
  `{source: 'dedupe', reason: 'duplicate of job K'}` history row are swapped for
  K. Measured: 3 certain cards pointed at a copy, now 0.
- **A blocking card whose button then refuses is worse than no card.** A match
  on a job retired beyond the 60-day revive window is now a notice, not a block
  — `relink()` would not revive it. Rare but real: 19 imports in 90 days.
- **The write is guarded like the Link Check apply step.** The card carries the
  status and apply_link it displayed; `relink()` re-reads the row FOR UPDATE and
  refuses unless both still match, because two people work the same import queue
  under one account. Verified live: a deliberately stale card wrote nothing and
  `job_status_history` stayed at 4,125 rows.

**Also:** the one-click "Create Anyway" bypass is gone — a second copy now costs
a typed reason, recorded against the new job. Bulk import never updates
anything; a certain match is saved as a Draft titled `[SAME AS N]`, and
publishing is refused while that prefix remains. `relink` is admin/manager only:
the `admin` middleware admits advertisers (roles 1, 2 **and** 4), which is wider
than it looks. `JobDeduplicator::findByApplyLink()` and `findDuplicate()` now
have no callers; `findAllDuplicateGroups()` still serves the Duplicates page.
`refreshExisting()` carries a never-call warning — it overwrites `date`, the
posted date.

**Deploy note:** every file went up as `<name>.new`, was md5-checked and
`php -l`-ed while still inert, then renamed over the original — new classes and
partials first, `routes/web.php` last. No `config:clear`; the config cache is
still the Oct 24 build. Backup of the 6 overwritten files:
`~/deploy-backups/import-dup-check-20261027-134130/`. **No cache purge needed:
nothing public changed.**
---

## 2026-10-27 — jobs:auto-trash no longer expires anything by default

**What:** `app/Console/Commands/AutoTrashJobs.php` (commit a986614). The default
run — the one the 02:00 schedule calls — now lists the overdue jobs and changes
nothing. Expiring moved behind `--expire`, for manual use after a link check.

**Why:** `delete_at` is OUR review date, not the employer's closing date. It is
set to "now + N days" at creation and at every extension, and nothing in that
path ever looks at the source listing. So expiring on the date alone meant "we
have not checked this job lately" was rendered to readers and to Google as "this
job has ended" — noindex, then a 301, then a 410. The link checks of Oct 22 and
Oct 24 measured the gap directly: of the jobs that had reached their `delete_at`,
most were still open at the source and were extended; the ones genuinely gone had
to be PROVEN gone by fetching the listing (404 on baitoru, a closing notice on
shigotoin). A date is not evidence.

**Three things this run taught, worth keeping:**

- **A silent log proved nothing.** The old command's only trace was
  `Log::info("jobs:auto-trash expired N jobs")`, and production runs
  `LOG_LEVEL=error`, so that line was discarded every time. Zero log hits could
  not distinguish "ran and found nothing" from "never ran". The one line it now
  writes is `Log::error`, and only when N > 0 — not because anything is wrong,
  but because `error` is the only level that reaches `laravel.log` here.
- **The filesystem answered what the log could not.** `storage/app/sitemap.xml`
  had mtime `Oct 27 02:30`, matching `sitemap:generate`'s scheduled time, which
  proved `schedule:run` is wired and firing. Hence the CSV is written on EVERY
  default run, even an empty one: its mtime is the proof the next person needs.
- **The report goes outside the web root.** First draft wrote to
  `storage/app/overdue/`. On Oct 13 `/laravel/storage/app/sitemap.xml` was
  publicly fetchable — nginx serves some static extensions straight from disk and
  never reaches the `.htaccess` deny. Re-confirmed today: that URL still returns
  **200**. The report now lives at `dirname(base_path(), 2) . '/private/overdue'`
  = `<domain>/private/overdue/`, a sibling of `public_html`, mode 0700. Verified
  404 over HTTPS, with the homepage 200 as control. **nginx serves `.xml` and
  `.html` from disk past the `storage/.htaccess` deny — never write files of
  those types under `public_html`.** The Oct 13 entry below found this for
  `.xml`; a full sweep of `storage/` on Oct 27 added `.html` to the list and
  confirmed `.log`, `.php`, `.gitignore` and all 1,898 extensionless session
  files are denied. That sweep also found 59 stale prefecture-content backups
  sitting in `storage/*.html` since February — Kanagawa, the page held in
  reserve, among them. Moved to `private/blog-backups/`.

  **Moving the file did not end the exposure.** Both URLs kept returning 200
  with the exact old byte counts and `x-proxy-cache: HIT` at
  `cache-control: max-age=15552000` — a 180-day cache of files that no longer
  existed on disk. The origin was already correct, which a cache-busted request
  (`?cb=…` → 403) proved while the plain URL still served the content. After the
  Site Tools Dynamic Cache flush + CDN purge: both 403, `x-proxy-cache: MISS`,
  homepage 200 with a HIT on the second call, so the cache layer was alive and
  the 403s were genuinely the origin. **A static-file exposure is closed by the
  purge, not by the `mv`** — and the second homepage control is what
  distinguishes "denied" from "the whole site is WAF-blocked".

**`--expire` also fixed two audit gaps** the old code had: it now sets
`Expire_Date` (not just `updated_at`, which made its output indistinguishable
from a manual bulk expire) and writes one `job_status_history` row per job under
batch `at-YYYYMMDD-HHMMSS`, inside a transaction that rolls back rather than
leave jobs expired with no record of why.

**State after deploy:** 5,636 published, 0 overdue, 0 due by tomorrow. First real
test is **2026-10-30 (175 jobs)**, then ~200–380/day. The CSV from each night is
the queue for the link check — expiry is now a decision someone makes, with
evidence, not a date arriving.

---

## 2026-10-27 — 221 duplicate published jobs merged (batch dd-20261027-072837)

**What:** 212 groups of published jobs that were the same listing. The oldest job
in each group was kept, given the newest copy's apply link, and the group's latest
delete_at; the newer copies were trashed (status 5, Expire_Date = now).

**Result:** 221 trashed, 212 kept, 165 relinked. Verified against all 433 affected
rows: 0 wrong status, 0 wrong apply_link, 0 missing, 0 trashed without Expire_Date.
Final spread exactly 212 published / 221 trashed.

**The rule took four passes to get right, and each pass was caught by evidence, not
by review of the code.** The first plan would have trashed 389 jobs, of which 182
were not duplicates at all:

- A `shop` match required the same address but no similar ROLE. For a staffing
  agency the stored address is the agency office, so one address covers many
  different placements. 株式会社ディンプル had "Family Restaurant Cashier" merged
  with "Shrimp Cracker Shop Sales Staff". Fixed by requiring role similarity on
  every path (role = the title before " at ").
- Six real vacancies at ホテルエミオン東京ベイ (Italian cooking, Italian hall,
  Japanese restaurant hall, …) shared one address and wage and were merged into
  one. All six had DISTINCT shigotoin offer ids — the source itself said they were
  different listings. The planner never consulted the offer id; now it does.
- `wage_key` joined every digit, so "1,150円〜1,500円" became 11501500 and did not
  equal "1,150円" → 1150. Two genuine FamilyMart duplicates were missed. Now it
  takes the STARTING wage.
- `norm_addr` converted 4丁目 but not 四丁目, so 北栄一丁目13-17 and 北栄1-13-17
  read as different places. Now kanji 丁目 is normalised first.
- Two jobs sharing a station but in different towns (東大阪市荒本新町 vs 菱江,
  both 荒本駅) were treated as one place. Station-only matches now also require
  the town-level addresses to agree.
- Same-listing pairs whose roles differ wildly ("Warehouse Packing" vs "Hospital
  Cooking Staff" on one offer) are a mistranslation, not a duplicate. 14 such
  pairs are held for the team: held-for-review-20261027.csv.

**Lessons:**
- **One address is not one workplace.** For agencies it is the office. Any
  address-based identity rule needs a second signal — here, the role.
- **The source's own listing id is the authority on whether two ads are one job.**
  Distinct offer ids were present and ignored in the first three passes.
- **A plan-first command earns its keep.** Every one of these defects was found by
  reading 20 sample groups and counting, before anything was written. The apply
  itself was uneventful.
- **ROW_COUNT() is 0 for an UPDATE that changes nothing**, so the keeper history
  INSERT (guarded on @r = 1) was skipped for 23 keepers whose link was already
  newest and whose delete_at was already latest. kept = 189, not 212. Predicted
  exactly: 165 relinked + 24 delete_at bumps. The data is correct; only those 23
  audit rows are absent. If that matters, guard on ROW_COUNT() >= 0 with a
  separate existence check instead.

**Backups (server):** ~/exports/dedupe-20261027-0137/
  jobs-before.sql (1,012,360 B, all 433 rows, --complete-insert),
  history-before.sql (1,026,484 B, both history tables),
  backup.csv, plan.csv, review.csv, rollback.sql

**Rollback (do not run unless needed):** rollback.sql in that folder restores the
keepers' apply_link and delete_at, returns the 221 to status 3 with their original
Expire_Date and updated_at, and deletes this batch's history rows.

---

## 2026-10-15 — Status 6 (quota full) is an unhandled lifecycle state: 621 pages advertised expired JobPostings

**Found while checking why four early-2025 top-ranking job pages (108421, 112252, 112112, 113067) had lost value.** They had not been redirected or 410'd — all four were **live, 200, indexable, with `JobPosting` schema**, for listings that closed in Nov 2024 – Jan 2025. `validThrough` read `2024-11-29`, `2025-01-16`, `2025-01-14`, `2025-01-26` — 21–23 months in the past.

**Why the lifecycle never touched them.** `JobController::detail()` branches on statuses 1/2 (→404) and 4/5 (→`handleExpiredJob()`); **status 6 falls straight through to the published render path**. `createSchema()` had the same gap: it suppressed schema for 2/4/5 only. By the tier rules these pages are 637–695 days past expiry, i.e. Tier 3 → 410, but that code never runs for them.

**Status 6 is terminal.** It is set in exactly one place — `FetchApplicationLogs.php:175`, the hourly ValueCommerce cron, when the affiliate feed reports the employer's quota is filled. Every status write in `app/` was audited: `jobs:auto-trash` only moves **status 3** past `delete_at` → 4, and the admin/analytics paths are operator-driven. **Nothing automatic ever moves a job out of 6**, which is why 591 of the 621 are more than a year old (oldest `delete_at` 2024-10-18).

**These pages earn their keep, so they were kept live.** 30 days of logs: **2,749 human (non-bot) hits** across **190 of the 621** pages, ≈92/day. Top: 121561 Sendai (63), 109867 Tennoji (40), 124099 (38), 119482 Omiya (34). They are **not in the sitemap** (0 of 621 — `SitemapController` filters `job_status_id = 3`), so they rank from history and internal links. The human-facing page is already honest: `detail.blade.php` shows a **"Quota Full"** badge (lines 74, 255) and the apply CTA is gated on status 3, so no visitor is sent to a dead listing. Only the machine-readable signal was wrong.

**Fix (`8e6a1f7`, deployed 2026-10-15):** `Job::STATUS_QUOTA_FULL` added to the `createSchema()` exclusion list. One file, one condition. No routing, `noindex` or redirect change.

**Verified, cache-busted, homepage control first and last:**

| Page | before | after |
|---|---|---|
| 108421 status 6 | 200, JobPosting **1**, validThrough 2024-11-29, 59,509 B | 200, JobPosting **0**, no validThrough, 58,119 B |
| 112112 status 6 | 200, JobPosting **1**, validThrough 2025-01-14 | 200, JobPosting **0** |
| 121561 status 6 (top traffic) | — | 200, JobPosting **0**, "Quota Full" present, 32 related links |
| 162932 published (control) | 200, JobPosting **1**, validThrough 2026-10-30, 67,534 B | **unchanged**, byte-identical |
| 162698 Tier 1 | 200 + `noindex, follow`, 0 schema | unchanged, byte-identical |
| 145418 Tier 2 | 301 → `/part-time-jobs-in-osaka-city-minato-ku` | unchanged |
| listing, /admin/login, homepage | 0 schema | unchanged, byte-identical |

All status-6 pages keep HTTP 200, the "Quota Full" badge and 32 related-job links; only ~1.4 KB of JSON-LD disappears.

**Still open (deliberately not fixed here).** The pile grows ~30/month because status 6 has no exit. The durable fix is to let `jobs:auto-trash` consider status 6 past `delete_at` too — but that retires the 190 pages still drawing traffic, so it needs its own decision. Parked.

**Note:** `php -l` on `JobController.php` emits a pre-existing deprecation (`detail(): implicitly marking parameter $slug as nullable`, line 16) — unrelated to this change, worth fixing when PHP 8.4 lands.

---

## 2026-10-14 — Lighthouse flood #4 (149,596 req): the trigger is Ahrefs Site Audit, not /blog

**Event.** 200,029 requests on Oct 14 vs 40,558 on Oct 13. 149,596 (75%) were
`Chrome-Lighthouse` from 96 forward-confirmed `google-proxy-*.google.com` IPs
(`64.233.172.x`, `192.178.10.x`), 09:31:30–12:46:54 UTC, peak 982 req/min at 09:52.
22,420 carried the internal Google referer `http://ghost-rider/`. 5,824 distinct HTML
pages — effectively the whole job inventory — each loaded ~1.85×, plus the full asset
set per load. 17,480 × 403 for `.js.map` (100% of the day's 403s).

**AdSense/GA4 reconciliation.** 10,766 Lighthouse HTML loads × ~2 ≈ 21,500 ≈ the
21,462 US page views AdSense reported, with 36 impressions and 0 clicks (Google's
invalid-traffic filter declining its own infrastructure). GA4 logged only 1,138,
because the GA4 tag is delayed 1s and Lighthouse finishes first.

**The /blog theory was wrong.** `sg-cachepress` was deleted with `/blog` on Oct 13;
the flood recurred the next day. The actual predictor is an **Ahrefs Site Audit run**:

| | Oct 14 | Sep 23 | Sep 30 (Wed) | Oct 7 (Wed) | Oct 13 |
|---|---|---|---|---|---|
| Lighthouse | 149,596 | 99,659 | 22 | 0 | 92 |
| AhrefsSiteAudit | 5,864 | 5,600 | 3 | 3 | 13 |

Ahrefs begins ~90 min earlier (08:03 both days) and ends within a minute of
Lighthouse (12:46:34/12:46:54 Oct 14; 12:40:45/12:41:30 Sep 23). **100% of
Lighthouse's HTML pages were also crawled by Ahrefs** (5,821/5,824 and 5,327/5,328).
Mechanism: Site Audit's Core Web Vitals feature calls the PageSpeed Insights API per
URL; PSI runs Lighthouse from Google's proxies. The "Wednesday pattern" was just the
audit schedule — two Wednesdays with no Ahrefs run had no flood.

**Action: do NOT block the IPs** — they are Google's and shared with other services.
Turn off Core Web Vitals / PSI collection in Ahrefs Site Audit settings (or pause the
audit). Separately, drop `sourceMappingURL` from the production JS build: removes
17,480 × 403 per run, ~12% of the flood.

**Instrument notes.** Oct 14 UTC spans two log files because rotation is ~04:33:
`-2026-10-14.gz` (13/Oct 04:38 → 14/Oct 04:31) and `-2026-10-15.gz` (14/Oct 04:31 →
15/Oct 04:27). There is no live uncompressed log on disk — during the event itself the
only readable proxy was session-file mtimes (`SESSION_DRIVER=file`), which showed
~2,200 origin page hits/hour. Counts here were done in Python; `grep -c` on a single
file silently returns 0 on this Mac. Parse control: Googlebot lines 2,152 / 2,046 /
2,377 on Oct 14 / Oct 13 / Sep 23.

---

## 2026-10-13 — `/blog` WordPress install removed (it was the source of the credential exposure)

**Why.** The `/security-check` sweep found two full-site backups downloadable at the web root: `public_html_2_9_2026.zip` (245 MB, 10,134 files) and `public_html.zip` (124 MB). The first was confirmed downloadable (HTTP 206 + `PK\x03\x04`); the second returned 403 only because the WAF happens to match that filename. **9,231 of the 10,134 entries were `blog/`**, and both archives contained `blog/wp-config.php` **byte-identical to the live file** — the WordPress DB password and all 8 auth keys/salts. **Neither archive contained `laravel/` (0 entries), so no Laravel credential was exposed.**

**Blast radius: no evidence of download.** 0 requests for either filename across all 30 days of retained logs (13/Sep–13/Oct). The files were dated Feb 8–9, so ~8 months of exposure is unobservable; treated as compromised anyway.

**The blog was already dead and unused:**
- WordPress could not connect to its database (`wp-cli` → "Error establishing a database connection"), on a **different database and user** from Laravel's.
- `/blog/` received **3 requests in 30 days, all bots, 0 humans** (vs 1,000,946 non-bot requests to the rest of the site). Its only content was Kadence starter-template demo posts.
- It accounted for 209 of the 249 sensitive-extension files under the web root, and hosted `sg-cachepress`.
- A `blog/.htaccess` dated 2026-03-14 shows it was already decommissioned then, with redirects bolted on inside the folder.

**Compromise check before removal — clean.** 0 PHP files anywhere under `wp-content/uploads/`; all 67 PHP files modified in 60 days accounted for (38 compiled Blade views, the rest our own deploys plus the known stray loose copies from the September drift audit); no admin accounts enumerable because the DB was already unreachable.

**Done:** both zips and `blog/.htaccess.bak` moved to `~/site-archives/`; `blog/` archived to `~/site-archives/blog-2026-10-13.tar.gz` (68.8 MB, 9,231 entries, `wp-config.php` verified present) and deleted — **171 MB reclaimed**. DB identifiers captured to `~/wpdbnames.txt` for manual deletion in Site Tools. Rotation of the WP secrets became unnecessary once the install and its DB user were removed.

**Redirects: the old rules lived inside the folder.** `blog/.htaccess` held the catch-all *and* the `-jobs-in-` category mapping; only the prefecture rule and `^blog/?$` existed in `public_html/.htaccess`. A naive `^blog(/.*)?$ / [R=301,L]` would have sent `/blog/convenience-store-jobs-in-japan` to the homepage instead of its live page. Replacement, now at the **top** of `public_html/.htaccess`:

```apache
RewriteRule ^blog/part-time-jobs-in-([^/]+)/?$ /part-time-jobs-in-$1 [R=301,L]
RewriteRule ^blog/(.+-jobs-in-[^/]+)/?$ /$1 [R=301,L]
RewriteRule ^blog(/.*)?$ / [R=301,L]
```

**Two ordering lessons, both found by testing:**
1. **Placement above the trailing-slash stripper matters.** With the rules at line 111, `/blog/` went 301 → `/blog` → 301 → `/` because the global stripper at lines 2–3 fires first. Moving them above it restored single-hop.
2. **`(.+)/?$` captures the trailing slash.** `/blog/part-time-jobs-in-tokyo/` became `/part-time-jobs-in-tokyo/` → another 301. Fixed with `([^/]+)/?$`.

Verified single-hop: `/blog`, `/blog/`, `/blog/part-time-jobs-in-tokyo(/)`, `/blog/convenience-store-jobs-in-japan(/)`, `/blog/wp-login.php`, `/blog/designing-runway-looks/`, `/blog/wp-content/uploads/x.jpg`. Unchanged: homepage, `/sitemap.xml`, job detail, `/admin/login`, prefecture pages, `/part-time-jobs-in-tokyo/page/2`, and `/faq/` (stripper still works).

**Note:** `public_html/.htaccess` is **outside the git repo** (the repo root is `laravel/`). A snapshot is committed at `docs/server/public_html.htaccess` — it is a copy, not the live file; edits there do nothing until deployed.

**Open:** `/blog-posts-sitemap.xml` and `/blog-categories-sitemap.xml` return **404 and did so before this change**. They are not Laravel routes and are referenced nowhere in `robots.txt` (which lists only `/sitemap.xml`) or in the generated sitemap (which is a flat `<urlset>`, not an index). Yet they drew **46 of 49 `/blog`-ish requests in 30 days** — almost certainly still submitted in Search Console. Remove them there.

---

## 2026-10-13 — `laravel.log` was world-readable (16 MB); `laravel/` has no `.htaccess` of its own

**Found by `/security-check`.** `https://nihonarubaito.com/laravel/storage/logs/laravel.log` returned **200, 16,220,159 bytes** to a normal browser UA. `laravel/artisan` also returned 200 (350 B, served as text — no `.php` extension). `config/database.php` and `routes/web.php` returned **500**: PHP under `laravel/` is *executed*, not denied.

**No credential values leaked.** 0 hits for `DB_PASSWORD` / `DB_USERNAME` / `MAIL_PASSWORD` / `APP_KEY=` / `Bearer` / `token`. What was exposed: the DB name (226 hits), the MySQL user and host via *"Access denied for user '…'@'…' (using password: NO)"*, absolute paths including the account name, framework versions, full stack traces, session ids inside SQL. Not linked from `robots.txt` or the sitemap. Decision: **no credential rotation** (SiteGround MySQL accepts local connections only).

**Root cause: there is no `public_html/laravel/.htaccess`.** `.env`, `composer.json`, `.git/config` and `bootstrap/cache/config.php` return 403 only because **SiteGround's WAF** blocks those filenames — not because of anything in this repo. Files the WAF doesn't recognise (a `.log`) were served.

**Fix applied (step 1 + 2):**
1. The 16 MB log moved out of the web root to `~/log-archive/laravel-2026-10-13.log.gz`; Laravel recreated an empty `storage/logs/laravel.log` (same owner, 644).
2. `public_html/laravel/storage/.htaccess` = `Require all denied`. Verified active with a discriminator: a **nonexistent** file under `storage/` returns **403**, while one outside returns **404** — so the rule is ours, not the WAF's. Safe because nothing is served from `storage/` by URL: `laravel/public/storage` does not exist, `storage/app/public` is empty, job images live in `public_html/images` and `public_html/frontend/images`, and the sitemap is read from disk by `SitemapController`.
3. Unaffected after the change: homepage, `/sitemap.xml`, job detail, images, `/admin/login` — all 200.

**Still open:**
- **nginx serves some static extensions directly, bypassing `.htaccess`.** `/laravel/storage/app/sitemap.xml` still returns 200 *with the deny in place*, while `.log`, `.txt` and `.gitignore` under the same directory return 403. So an `.htaccess` deny cannot protect static files of nginx-handled types; those must be moved out of the web root or blocked at nginx level by SiteGround.
- A full `laravel/.htaccess` deny is **planned, not applied** (step 3).
- `LOG_CHANNEL=single` means one file grows without bound. Switching to `daily` with ~14 days retention is an `.env` change, so it needs `config:cache` afterwards.

**Instrument lessons:**
- **The CDN keeps serving a deleted file.** After the move, a cache-busted request returned 97 bytes but a **normal request still returned the full 16 MB** for at least 2 minutes. Fixing the origin does not end an exposure — the dynamic cache must be purged.
- **A 403 can be the WAF's challenge page, not your protection.** It is exactly 75,193 bytes and identical for every path. A run where *all* paths 403 — homepage included — is a WAF block, not a pass. The Windows-Chrome UA tripped it; the Mac-Chrome UA did not. Control with the homepage before *and* after every sweep.
- **Prove a deny with a nonexistent path**: 403 means the rule applies, 404 means it does not.
- `grep -c pattern file` returns 0 on the Mac even when matches exist — count markers with Python.
- `git grep` over `git rev-list --all` found nothing for a string known to be in history; use `git cat-file --batch-all-objects` instead.

---

## 2026-10-05 — Japanese-level work rolled back (parked until after ~Oct 8)

Both Japanese-level changes were reverted the same day they shipped; **the "Benifits" typo fix (`8415e4e`) is retained**.

- `51716b4` — display labels: JLPT levels added, N4/N5 collapse fixed, blank level 1 filled. Reverted by `53f374a`.
- `06c606c` — XML import derived `japanese_level` from `job_category_id` when the feed sent its default (3). Reverted by `964b518`.

**Reason:** parked deliberately, not because anything failed. Redo the whole thing in one pass once the spam-update rollout settles (~Oct 8): labels + new-job assignment + an optional bulk fix of existing rows.

**Worth keeping from the investigation:**
- **Level number = JLPT number** (5 = N5, easiest). Confirmed in two places: the admin dropdown in `JobController::getFormDropdowns()` and the front-end labels. Convenience Store at N3 is deliberately the hardest of the five.
- **The feed's `japanese_level` is a near-constant 3**: 2,292 of 2,417 imports (94.8%) in the 30 days to Oct 5, regardless of category. It carries no category signal. The ~125/month at 2/4/5 may be real publisher input, which is why the chosen rule overrode *only* the value 3.
- **Agreed map:** 1 Packing→5, 2 Restaurant→4, 3 Konbini→3, 4 Bed Making→4, 5 Delivery→4; unmapped categories keep the feed value.
- **Existing rows were never touched.** A retroactive update would hit ~3,358 published rows (Packing 848, Restaurant 1,700, Bed Making 803, Delivery 7).
- **Watch when redoing:** `*/` inside a docblock path like `lang/*/content.php` closes the comment and breaks the file.

---

## 2026-09-23 — Random 500s (~0.1% of requests): host returns an empty `.env`; fixed with a cached config

**Symptom:** 21–46 HTTP 500s a day, scattered across job detail pages, area pages, `/jobs/search`, `/account` and admin POSTs. Random, never reproducible, a reload always worked. Long-standing, not caused by any deploy.

**Diagnosis:** Every distinct 500 timestamp in the access log had an exception logged in the **same second** (30/30 on 2026-09-22). The exception was `Illuminate\Encryption\MissingAppKeyException`. Two other errors in the same log confirmed the cause: `Table 'sessions' doesn't exist` and `database.sqlite does not exist` — both are Laravel's **defaults**, the values used when `.env` isn't loaded. `.env` itself was intact, readable and unchanged since Jul 23.

**Root cause:** Laravel read `.env` on every request, and on this shared host that read intermittently returned empty. The request then booted with framework defaults (no `APP_KEY`, sqlite, database sessions) and died. A hosting/filesystem fault, not a code bug.

**Fix (2026-09-23 14:33 UTC):** `php artisan config:cache`. Config, including `APP_KEY`, now comes from `bootstrap/cache/config.php`, so requests no longer read `.env`. No code change. Verified safe first: **zero** `env()` calls outside `config/` (those return null once config is cached).

**Deploy procedure this creates:** `.env` and `config/` changes now do nothing until `config:cache` is re-run. See SETUP.md section 2.

**Lessons:**
- **A cache rebuild can bake in the same failure.** `config:cache` reads `.env` once; if that read fails, broken defaults are served to 100% of requests instead of 0.1%. Verify `.env` reads clean before caching, and check the result immediately after, with `config:clear` ready.
- **Verify a cache by reading the generated file with `php -r`, not through the app.** Asking `artisan`/`tinker` means asking the possibly-broken cache about itself.
- **Not fully solved.** Cron jobs and `artisan` commands still read `.env` directly and can still hit the hiccup (a run fails, the next succeeds). The real fix is on SiteGround's side.

**SiteGround ticket:** #5141798, opened 2026-09-30, escalated to senior techs, awaiting their update — https://my.siteground.com/support/history. Evidence supplied: file intact and manual reads always succeed, but the app's read intermittently returns empty (a storage-layer read failure, not permissions). Reference timestamps: 30 correlated web failures on 2026-09-22; cron failures at 02:05–02:29 UTC on 2026-09-23.

---

## 2026-09-17 — Site unreachable (ERR_SSL_PROTOCOL_ERROR): SiteGround global CDN outage, not code

**Symptom:** Browsers showed `ERR_SSL_PROTOCOL_ERROR` on nihonarubaito.com. Reported by two people in different cities, on different networks.

**Cause (confirmed by SiteGround support):** A global, temporary SiteGround CDN issue. SiteGround fixed it on their side. No code, deploy, or certificate change was involved. The certificate was valid throughout (Let's Encrypt `*.nihonarubaito.com`, Aug 20 – Nov 18 2026).

**Misdiagnosis to avoid:** The agent called it "local to the Mac". Its checks came back healthy, but only because they ran from the Mac, after recovery had started, and from the SiteGround server. On the Mac, the system resolver failed for the bare domain while `dig` worked; that was a symptom of the outage, not a local fault. **The SiteGround server can't tell you whether visitors can reach the site, because it is inside SiteGround's own network.** Never call an outage local until someone on a *different network* (for example a phone on mobile data, or a person elsewhere) has checked.

**If it happens again:**
1. **Scope:** test from a phone on mobile data and ask someone on another network. If it fails for more than one network, treat it as a hosting-layer outage. Don't touch the code.
2. **Status:** check the SiteGround status page for an active CDN/network incident.
3. **Certificate** (rules out an expiry):
   ```bash
   echo | openssl s_client -connect nihonarubaito.com:443 -servername nihonarubaito.com 2>/dev/null | openssl x509 -noout -dates
   ```
4. **Purge:** Site Tools → Speed → Caching → purge the cache (kicks the CDN edge). A cache purge is safe; don't reissue the SSL certificate unless step 3 shows it has expired.
5. **Still failing:** open a SiteGround ticket. Say "multiple users, different cities/networks, certificate valid → CDN/DNS layer."
6. **After recovery:** a machine may keep the cached failure. Run `sudo dscacheutil -flushcache; sudo killall -HUP mDNSResponder`, clear `chrome://net-internals/#dns`, or use incognito.

---

## 2026-08-27 — Empty homepage cache poisoning: never cache 0-job renders

**Symptom:** Homepage shows "No job found" on cookieless first load (Googlebot, private browsers, first-time visitors); refresh shows jobs. AdSense Auto Ads placed 0 in-page ads (scanned the empty page).

**Root cause:** CDN cached a stale empty/Japanese render (likely from Aug 12 language outage). ApplyCacheHeaders set `Cache-Control: max-age=1800, public` on ANY English response, including empty ones. If the app momentarily returned 0 jobs, CDN served that empty page to cookieless visitors for 30 minutes.

**Diagnosis evidence:**
- Gate 1 query: 5,661 published English jobs exist (lang_id=1) — app layer clean, query correct
- Gate 2 cookieless curl: Cache EXPIRED, fresh response contains 90 job links — currently working, but risk of regression
- Verdict: (B) Cache-layer bug, not app-layer data mismatch

**Fix (deployed 2026-08-27):**
Two guards in ApplyCacheHeaders, following existing non-English-poisoning pattern (early return → Set-Cookie survives → nginx refuses to cache):

1. `ListingController::index()` sets `skip_cache_empty` flag when `$totalRows === 0`
2. `ApplyCacheHeaders` checks flag and returns early if set

Result: Empty renders stay uncached (Set-Cookie preserved); populated pages still cache 30 min. Driven by actual query count ($totalRows), not fragile HTML parsing.

**Regression test (run after every deploy touching homepage/cache):**
```bash
curl -s -A 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15' \
  https://nihonarubaito.com/ | grep -c "detail/english"
# Expected: 50-90 (page size dependent)
# Failure: 0 or <10 indicates empty homepage cached
```

**Post-fix verification:** 90 job links on cookieless request (2026-08-27)

**Note:** FrontendComposer emergency override (forces English) remains in place — its removal is a separate task pending language path verification.

---

## 2026-07-22 — Bulk-edit lessons (visa-hours correction, 10 pages)

- **Protected-page flags belong in the exclusion list.** Kanagawa carried a DO-NOT-MODIFY flag and was still swept into a 10-page bulk edit, because the exclusion list only covered content-based exceptions (Saitama's earnings line). Standing instructions need checking *before* writing a multi-page update.
- **Splice replaces eat conjunctions.** Replacing a mid-sentence clause consumed the preceding "and", producing a run-on on 9 pages. Conservation counts passed; the grammar didn't. Check rendered output, not just match counts.
- **Prefecture pages are template-*similar*, not identical.** Three heading variants exist for the same section ("How Foreigners Find Jobs in {X}", "How Foreigners Find Part-Time Jobs", "How Foreign Residents Find Part-Time Jobs"). A single shared anchor silently skips pages — enumerate variants first.

---

## 2026-07-22 — Widget (job-listing rich result) baseline restated

**The old metric is void.** The pre-registered schema-fix success measure — "0.18% CTR on 181,725 impressions/28d" — describes a surface that no longer exists at that size.

**What happened.** Rich-result impressions were *rising* through mid-July (Jul 13: 1,663 → Jul 14: 3,723 → Jul 15: 4,807), then collapsed: Jul 17: 144, Jul 18: 117, Jul 19: 179. A ~97% drop against the Jul-15 peak, same window as the desktop-impression deflation. Cause unverified; effect clear.

**Markup is exonerated** — valid items held 6,431–6,725 throughout, critical issues 0, warnings decaying (postalCode 6,546→4,767; maxValue 1,502→326), and position *improved* 2.18 → 1.48. Nothing about the fix caused this.

**New baseline:** ~150 impressions/day at position ~1.5. Clicks TBD — the 7-day click figure (23) spans the discontinuity and can't be split by day.

**Measure schema-fix success by:**
1. Warning decay (already effectively proven — both classes falling fast)
2. Valid-item count holding
3. CTR and position computed on post-Jul-17 data only

**Do not use:** any impression or CTR comparison spanning Jul 16–17. The Jul 13–19 aggregate (12,981 impressions) exceeds the prior week's 6,883 purely because it includes the pre-collapse days — a week-over-week read shows +89% growth on a channel that actually fell 97%.

---

## 2026-07-21 — Part G: Hamamatsu ward merge (COMPLETE)

Hamamatsu reorganized 7 wards → 3 on 2024-01-01; the areas table still held the 6 defunct wards. Resolved:

- Deleted areas 705–710; inserted 1898 (中央区 Chuo-ku), 1899 (浜名区 Hamana-ku).
- Renamed 711 english → "Hamamatsu Tenryu-ku" (was mistranslated "Hamamatsu chuo-ku").
- Renamed 3 municipalities: 421 篠山市→丹波篠山市, 670 筑紫郡那珂川町→那珂川市, 923 黒川郡富谷町→富谷市 (all with postal codes).
- Migrated 365 jobs (321 Chuo / 44 Hamana), including 8 address-corrected off 北区.
- Deduped 32 preferences → 15 (dedup was load-bearing: no unique index on user_id+area_id).
- Six retired slugs given explicit 301 overrides (see redirect note below).
- Sitemap regenerated (5,915 URLs); production dumps re-exported and swapped into translator.
- Import guard deployed (log-only) — see import-pipeline note.

**Backup:** `/home/customer/partg_backup_20260721_132931.json`

**Routing authority:** 中央区 gets old 中区/東区/西区/南区 + Mikatahara district of 北区 (初生町/三方原町/東三方町/豊岡町/三幸町/大原町/根洗町, verified vs city.hamamatsu.shizuoka.jp). 浜名区 gets 浜北区 + all other 北区. 711 天竜区 unchanged.

---

## 2026-07-21 — Area-deletion redirect fallback = lowest-id-in-town (NOT similarity)

When an area row is deleted, the area-slug fallback redirects the orphaned slug to the lowest-id surviving area in the same `town_id` — not a similarity match. All six Hamamatsu retired slugs 301'd to tenryu-ku (lowest id) until explicit overrides were added to RedirectLeakedPaths middleware.

**Consequence:** the earlier Miyakojima merge's "correct" redirect was luck (121 was the sole survivor in its town), not intelligence. Every future area deletion needs explicit 301 overrides — do not assume the fallback routes correctly.

---

## 2026-07-21 — Import pipeline: area_id is unvalidated; the feed is wrong independently of any reorg

CreateJobFromXmlController assigns `area_id` straight from the XML feed with no validation. The `join('areas')` in `Job::scopeWithLocalizedNames` is an INNER join, so a bad area_id makes the job silently vanish — no listing, no detail page, no 404. This hid 1,035 orphaned jobs for ~3 years.

Audit found 8 live-adjacent jobs whose addresses read 中央区/南区 but sat on 北区 — the feed mis-assigns area_id regardless of the reorg. Durable fix is address-based validation at import. Interim: log-only guard deployed at CreateJobFromXmlController (~line 352) — fires on a present-but-unresolvable id, skips `area_id=0` (legitimate "no area" case). Check `storage/logs/laravel.log` for `unresolvable area_id`.

---

## 2026-07 — Four instrument-failure lessons: a negative from an unvalidated instrument is NOT evidence

Each of these looked like a real defect; each was the measuring tool failing:

1. **Bare curl → 403.** Site blocks no-UA requests; read as a broken page. → Always use a browser User-Agent when curling the site.
2. **Redirect-stub greps.** Grepping a 301 response for content finds nothing; read as a missing feature. → Follow the redirect (`curl -L`) first.
3. **localhost-on-nginx.** `http://127.0.0.1` 301'd to HTTPS with empty body; read as origin failure. → Use `curl --resolve host:443:127.0.0.1`.
4. **Pretty-print grep miss.** `"postalCode":"..."` never matched because JSON is pretty-printed (`"postalCode": "..."`); read as field absent. → Grep the bare key first; check for whitespace/formatting.

**Verification stack** now has four named layers: (1) prose review, (2) byte/hash gates, (3) runtime-load confirmation (opcache disabled on host; CLI/tinker sees new code, web may not — md5 proves the FILE deployed, not that the RUNTIME loaded it), (4) instrument-validation-before-trusting-a-negative.

---

## 2026-07-21 — Bug #11: multilingual columns need per-column checks on rename

Area 711's vietnamese column still read "Hamamatsu chuo-ku," after the english-only rename fixed just english. The areas table has `chinese`/`english`/`japanese`/`korean`/`vietnamese` columns; a rename must check all language columns, not just english. (Observed pattern: chinese=kanji copy, korean=empty string, vietnamese=romaji with trailing comma.) Corrected to "Hamamatsu Tenryu-ku,".

---

## 2026-10-21 — XML import set the wrong prefecture/area; the address was always right

**Symptom.** Imported jobs arrived with a wrong `prefecture_id`/`area_id` (Kanagawa
especially); translators fixed them by hand on the preview page. Two live jobs were
addressed in 神奈川県横浜市西区 but stored as Osaka.

**Not the translators' table.** Diffed their 47-row ID reference against `prefectures`:
zero id→english mismatches, and all 47 of their Japanese names are the correct official
forms. The error is upstream, in the address-reading step. Checking the reference first
stopped a day of fixing the wrong thing.

**`prefectures.japanese` is not a safe match key.** 40 of 47 rows hold the bare name
(東京), 4 the full official name (愛知県), and 3 were simply wrong — id 14 Niigata
`新潟市`, id 23 Kumamoto `熊本市` (city names) and id 39 Wakayama `若山` (wrong kanji).
Matching that column would have failed silently for exactly those three prefectures.
Fixed to 新潟 / 熊本 / 和歌山 with an `english` guard. Not public — every published job
is `lang_id=1`, so `prefecture_name` renders from `english` — but it feeds the admin
demand-supply shigotoin search links, where `若山` returned nothing, quietly starving
Wakayama sourcing.

**Schema correction.** `areas` has NO `prefecture_id`. It hangs off `towns`
(`areas.town_id → towns.id → towns.prefecture_id`, 47 towns, 1,892 areas). CLAUDE.md had
this wrong and is now corrected.

**Two substring traps that produce confident wrong answers.** Always match the full
prefecture name *with* its 都/道/府/県 suffix:
  - `京都` is a substring of `東京都` → a Tokyo address reads as Kyoto
  - `神奈川` is a substring of `横浜市神奈川区` → a Yokohama ward hijacks the prefecture
Area matching takes the longest `areas.japanese` contained in the address, which is what
makes 横浜市中区 beat a bare 中区, 堺市西区 beat 大阪市西区, and 東広島市 beat 広島市東区.

**Measuring against "what the team saved" needs adjudication, not a match rate.** Raw
agreement on the last 250 imports was 99.2% prefecture / 92.0% area — but the
"disagreements" were mostly the resolver being right. Adjudicating each one *textually*
(does the name occur verbatim in the address?) gave 17 resolver-right, **0**
resolver-wrong, 3 empty areas filled, 1 correctly flagged. A bare match rate would have
read as 8% error when the true error was zero.

**Bulk fix (183 of 6,254 published jobs).** 2 prefecture + 181 area. Guarded UPDATEs
(`WHERE job_no=… AND prefecture_id=… AND area_id=…`) so the run is idempotent and a
no-op on any row a translator changed meanwhile. Verified: 183 at new values, 0 at old.
`updated_at` deliberately NOT bumped — it is the only "last human edit" signal this
schema has (no status/field history table exists), and bumping 183 rows would destroy it.

**Held back, deliberately: 2 jobs.** `144213` 栃木県那須塩原市（勤務地は那須町） — the
bracket names a *different* municipality, so neither value is certainly right. `163725`
千葉県山武市芝山町岩山 — self-contradictory address, and edited 2h earlier. A first-pass
rule held all 7 bracketed addresses; six were building/store names (（新千歳空港内）,
（大丸心斎橋店内）) that do not contradict the address, so the rule now fires only when the
bracket contains a municipality marker 市区町村郡. Over-broad guards cost real fixes.

**URLs do not move.** Job URLs are `/jobs/{job_no}/detail/{slug}` with
`slug = Str::slug("english-" . title)` — language + title only. Changing
`prefecture_id`/`area_id` changes no URL, canonical or sitemap entry. Confirmed against
live 301 targets before writing. Job 163735's URL already said `in-kanagawa` while its id
said Osaka; the fix aligned the id *to* the URL.

**Area-page side effects, measured:** 17 area pages dropped to 0 published jobs (now
noindexed by the existing three-guard logic) and 7 rose from 0 (now indexable). All are
long-tail municipalities with 1–2 listings. Worth knowing a correct data fix still moves
indexable surface area.

**Fail-safe matters more than the feature.** The first deploy called
`AddressLocationResolver::fromDatabase()` unguarded inside the import; a DB failure would
have 500'd the admin import screen — an enhancement acting as a dependency. Now wrapped:
on any Throwable the import proceeds with the XML's own values and both fields are marked
"location check unavailable" for a human. Commits `7a2f74e`, `054f4b5`.

**Rollback artefacts:** `~/exports/location-fix-backup-20261021-180617.tsv`
(183 rows, md5 `91bd2f3ad450f9c85eb92ee83b1cc7af`) and `fix-rollback.sql`, guarded on the
new values.

---

## Standing environment notes

- **SSH:** ssh.nihonarubaito.com port 18765, user u4602-zegubfmytubg, Laravel root `~/www/nihonarubaito.com/public_html/laravel/`. All production DB work runs via SSH — never local XAMPP (a wrong-target near-miss occurred when a step ran against the empty local DB).
- **Opcache is disabled on this host** — file identity (md5) is sufficient to confirm a deploy; do not generalize this assumption to other environments.
- **SSH heredoc form** (`<< 'REMOTE'`) avoids nested-quote escape mangling that breaks inline tinker commands with `$` variables.
- **Sitemap regen:** `rm -f storage/app/sitemap.xml` FIRST (24h file cache makes `sitemap:generate` a no-op loop otherwise), then regenerate.
- **Translator dumps** (areas, prefectures) live as knowledge files in the separate "Nihon Arubaito Translation" project, resolved by grep. Re-export from production after any area edit. As of 2026-07-22 both are clean UTF-8 (PHP-written) — the old cp1252→`/tmp/areas_fixed.sql` encoding workaround is retired.
