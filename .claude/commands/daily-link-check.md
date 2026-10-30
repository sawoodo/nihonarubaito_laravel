---
description: Daily due-job check (Mac, Japan VPN). Scans shigotoin + baitoru jobs due in the next N days (default 2), builds the Link Check upload and a guarded expire plan, lists direct-employer jobs for the team. Expires nothing until I say so.
argument-hint: "[days=2] | expire <out-dir>"
---

# /daily-link-check — check every job before its review date

`$ARGUMENTS` empty or a number → Gates 1–5 (scan + plan), then STOP.
`expire <out-dir>` → Gates 6–7 on that folder, after I've uploaded the extend file.

Why: the 02:00 `jobs:auto-trash` is report-only since a986614. Nothing extends or expires a job unless its source page was checked. This command is that check.

## Rules (non-negotiable)

- **Mac only, Japan VPN on.** `scripts/link-check.py` refuses to run without it; never run the scan on the server.
- **One scan per site at a time, 6 s between requests.** shigotoin and baitoru may run side by side (different sites); never two shigotoin scans.
- Gates 1–5 are **read-only** on the database. SELECT only.
- Every SSH command names its target. DB credentials via a temporary defaults file deleted in the same command (as in `/check-translator-duplicates`). Never print `.env`.
- Show raw output at every gate. Never edit generated SQL by hand. Never run a rollback unless I ask.
- **An ERROR is a failed check, not a closed job.** ERROR rows are never expired.

Folder for today: `~/nihon-linkcheck/daily-YYYYMMDD/` (call it `<D>`). Window `DAYS` = `$ARGUMENTS` or 2.

---

## Gate 1 — preflight

```bash
ssh nihonarubaito 'whoami && date -u && cd www/nihonarubaito.com/public_html/laravel && \
  ls -l ../../private/overdue/ | tail -3 && \
  tail -n +2 ../../private/overdue/overdue-$(date -u +%F).csv 2>/dev/null | wc -l'
```

Expect: `u4602-zegubfmytubg`; today's overdue report present (its row count = jobs that passed their date unchecked — they are included in the export below anyway). No report for today → the 02:00 scheduler didn't run: say so, carry on.

## Gate 2 — export the due jobs (read-only)

```bash
DAYS=2   # ← the window
ssh nihonarubaito "bash -s" -- "$DAYS" <<'REMOTE'
set -euo pipefail
DAYS="$1"; case "$DAYS" in (*[!0-9]*|'') echo "bad DAYS"; exit 1;; esac
cd ~/www/nihonarubaito.com/public_html/laravel
val() { grep -E "^$1=" .env | cut -d= -f2- | sed -e 's/^["'"'"']//' -e 's/["'"'"']$//'; }
CNF=$(mktemp); chmod 600 "$CNF"; trap 'rm -f "$CNF"' EXIT
printf '[client]\nuser="%s"\npassword="%s"\nhost="%s"\n' "$(val DB_USERNAME)" "$(val DB_PASSWORD)" "$(val DB_HOST)" > "$CNF"
DB=$(val DB_DATABASE)
OUT=~/exports/daily-$(date -u +%Y%m%d); mkdir -p "$OUT"
COLS='job_no, job_status_id, apply_link, delete_at, updated_at, Expire_Date AS expire_date, title'
WHERE="job_status_id = 3 AND lang_id = 1 AND DATE(delete_at) <= CURDATE() + INTERVAL $DAYS DAY"
q() { mysql --defaults-extra-file="$CNF" --batch "$DB" -e "SELECT $COLS FROM jobs WHERE $WHERE AND $1 ORDER BY delete_at, job_no"; }
q "apply_link LIKE '%shigotoin.com%'"        > "$OUT/due-shigotoin.tsv"
q "apply_link LIKE '%baitoru.com%'"          > "$OUT/due-baitoru.tsv"
q "apply_link LIKE '%staff.family.co.jp%'"   > "$OUT/due-familymart.tsv"
q "apply_link LIKE '%ptj.sej.co.jp%'"        > "$OUT/due-sej.tsv"
q "apply_link LIKE '%sftworks.jp%'"          > "$OUT/due-sftworks.tsv"
q "apply_link NOT LIKE '%shigotoin.com%' AND apply_link NOT LIKE '%baitoru.com%'
   AND apply_link NOT LIKE '%staff.family.co.jp%' AND apply_link NOT LIKE '%ptj.sej.co.jp%'
   AND apply_link NOT LIKE '%sftworks.jp%'" > "$OUT/due-other.tsv"
wc -l "$OUT"/due-*.tsv
echo "OUT=$OUT"
REMOTE
mkdir -p ~/nihon-linkcheck && scp -r nihonarubaito:<OUT path> ~/nihon-linkcheck/
```

Each file has a header line, so `wc -l` minus 1 = jobs. A header-only file is fine (nothing due on that site). If **all six** are header-only, say so and STOP — nothing to do today.

`due-other.tsv` is now only the sites with no profile (gigabaito, mynavi, anything new, and any job whose `apply_link` is not a URL). It is never scanned — it goes to the team in Gate 5.

## Gate 3 — guards, then scan (Mac, VPN)

Guards first, per site that has jobs, against **that site's own due file** (guard 4 checks every row belongs to the site, so the file must be passed in):

```bash
python3 -I scripts/link-check.py --host shigotoin  --input <D>/due-shigotoin.tsv  --guards-only
python3 -I scripts/link-check.py --host baitoru    --input <D>/due-baitoru.tsv    --guards-only
python3 -I scripts/link-check.py --host familymart --input <D>/due-familymart.tsv --guards-only
python3 -I scripts/link-check.py --host sej        --input <D>/due-sej.tsv        --guards-only
python3 -I scripts/link-check.py --host sftworks   --input <D>/due-sftworks.tsv   --guards-only
```

All four guards must PASS (JP IP, open control OPEN/MOVED, closed control CLOSED/GONE, every row on this site). **Any FAIL → STOP for that site** — a host whose closed control stops firing would read every job as open. Skip a site whose file is header-only.

Then scan:

**One process per host, all five in parallel.** They are five different sites, so the 6 s gap applies per host, not across them. Never two processes on the same host.

```bash
python3 -I scripts/link-check.py --host shigotoin  --input <D>/due-shigotoin.tsv  --output <D>/results-shigotoin.csv  --limit 0 --delay 6
python3 -I scripts/link-check.py --host baitoru    --input <D>/due-baitoru.tsv    --output <D>/results-baitoru.csv    --limit 0 --delay 6
python3 -I scripts/link-check.py --host familymart --input <D>/due-familymart.tsv --output <D>/results-familymart.csv --limit 0 --delay 6
python3 -I scripts/link-check.py --host sej        --input <D>/due-sej.tsv        --output <D>/results-sej.csv        --limit 0 --delay 6
python3 -I scripts/link-check.py --host sftworks   --input <D>/due-sftworks.tsv   --output <D>/results-sftworks.csv   --limit 0 --delay 6
```

`--limit 0` = every row. Rough time per host: jobs × 6 s (175 jobs ≈ 18 min), so the run takes as long as the biggest host — FamilyMart is usually that one. If a run reports ERROR rows, run the **same command once more**: it resumes and retries the ERROR rows first. Do not loop beyond that.

## Gate 4 — build the files (local, writes nothing to the DB)

For each site that had jobs:

```bash
python3 -I scripts/build-linkcheck-files.py --host shigotoin --results <D>/results-shigotoin.csv \
  --snapshot <D>/due-shigotoin.tsv --out-dir <D> --require-complete <jobs in due-shigotoin.tsv>
```

(same for each of the other four hosts that had jobs). `--require-complete` = the number of jobs exported, so a half-finished scan is refused.

## Gate 5 — report, then STOP

Table per site: OPEN · MOVED (relink) · CLOSED · GONE · HELD · ERROR · held · excluded, and the reconciliation line ("accounted for … OK").

`HELD` is a verdict, not a failure: sftworks returns it when a `work_id` no longer opens an entry form while the listing is still advertised. It is never extended and never expired.

Then list, with paths:

1. **Upload files** `upload-<site>-<ts>.csv` → I upload them at Admin → Link Check → Preview → Apply.
2. **Expire plans** `expire-<site>-<ts>-apply.sql` with the job count each.
3. **Held** jobs (ERROR, HELD, MOVED id_changed): job_no + reason, for the team.
4. **Unprofiled jobs** from `due-other.tsv`: job_no, title, apply_link, delete_at — the team checks these by hand today. Lawson (`crew.lawson.co.jp`) has no profile yet: its entry URLs answer every request with the `/jobfind-pc/errors/408` page, so it cannot be scanned — see the note at the end of this file.

**STOP.** Wait for `expire <D>`. (Upload first: the extend upload and the expire plan touch different jobs, but do them in that order so the Link Check page sees today's results.)

---

## Gate 6 — expire (only after my "expire <D>")

For each site's plan:

1. Copy `expire-<site>-<ts>-{precheck,apply,rollback}.sql` and `-backup.csv` to the server folder `<OUT path>`.
2. Run the precheck with **Run SQL file** (below). It prints `guard_matches`; the header says the expected number.
   - Equal → go on.
   - Lower → some jobs changed since the scan (e.g. the team edited them). Say how many and go on: the apply skips exactly those rows.
   - Higher → impossible; STOP.
3. Run the apply with **Run SQL file**. It runs in one transaction, prints `expired_in_batch`, and commits. It must equal `guard_matches` from step 2.

### Run SQL file

```bash
FILE=<OUT path>/<name>.sql
ssh nihonarubaito "bash -s" -- "$FILE" <<'REMOTE'
set -euo pipefail
FILE="$1"; cd ~/www/nihonarubaito.com/public_html/laravel
val() { grep -E "^$1=" .env | cut -d= -f2- | sed -e 's/^["'"'"']//' -e 's/["'"'"']$//'; }
CNF=$(mktemp); chmod 600 "$CNF"; trap 'rm -f "$CNF"' EXIT
printf '[client]\nuser="%s"\npassword="%s"\nhost="%s"\n' "$(val DB_USERNAME)" "$(val DB_PASSWORD)" "$(val DB_HOST)" > "$CNF"
mysql --defaults-extra-file="$CNF" --default-character-set=utf8mb4 "$(val DB_DATABASE)" < "$FILE"
REMOTE
```

## Gate 7 — verify and report

- 2 expired jobs: `curl -s -o /dev/null -w "%{http_code} %{size_download}\n" -A "Mozilla/5.0 (Macintosh)" https://nihonarubaito.com/jobs/<job_no>/detail` (no `-L`) → 200, about 40 KB, the closed page: contains `noindex` and no `JobPosting` (expired jobs skip the slug redirect).
- Published count before and after (`SELECT COUNT(*) FROM jobs WHERE job_status_id = 3`): the difference must equal the jobs expired.
- Report: batch ids, counts per site, rollback file paths (do not run them), the held + unprofiled lists again.
- Remind me: Dynamic Cache Flush + CDN Purge in Japan's quiet hours (22:00–02:00 PKT), not now.

---

## Hosts without a profile

**Lawson — `crew.lawson.co.jp`, 369 published jobs, no profile (2026-10-30).**
Every live entry URL answers HTTP 200 but redirects to `/jobfind-pc/errors/408`
("408 Request Timeout"), the same 20,098-byte page for all three live jobs
tested. A nonexistent id gives a clean 404, so GONE is detectable, but open
versus closed is not — we never reach a job page. That is the
identical-answer-for-every-input shape that means *we are being refused*, not
that the jobs are gone (ledger lesson 87). A profile built on it would be
worthless. Lawson jobs stay in `due-other.tsv` for the team until a fetch that
reaches a real job page is found.

**Too few jobs to be worth a profile:** gigabaito.com (5), baito.mynavi.jp (3).
Both go to the team through `due-other.tsv`.
