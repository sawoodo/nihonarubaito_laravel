---
description: Merge published duplicate jobs — keep the oldest, give it the newest apply link, trash the copies. Plans first, applies only after I approve, with backup and rollback.
argument-hint: "[plan|apply <out-dir>]"
---

# /dedupe-published — merge certain duplicates among published jobs

`$ARGUMENTS` empty or `plan` → Gates 1–4 (read-only), then STOP.
`apply <out-dir>` → Gates 5–8 on a plan I have approved.

What a merge does, per group of published jobs that are certainly one listing:
- **keep the oldest job** (its page has the history and ranking);
- give it the **newest job's apply link** (old link saved in `job_apply_link_history`) and the group's **latest review date**;
- **trash the newer copies** (status 5, `Expire_Date` and `updated_at` = now), one `job_status_history` row each, batch id `dd-…`.

The rules for "certain" and "review" are in the header of `scripts/dedupe-plan.py`. Do not edit that script during this command; if it fails, stop and report.

## Rules (non-negotiable)

- Gates 1–4 are **read-only**: SELECT only.
- Every SSH command names its target (`ssh nihonarubaito …`). Never against a local database.
- DB name and credentials come from `.env` via a temporary defaults file deleted in the same command (as in `/check-translator-duplicates`). Never print `.env` or the password.
- Show raw output at every gate. A summary never clears a gate.
- **Never apply without my explicit "apply" in chat for that out-dir.** Never edit the generated SQL by hand.
- Never run `rollback.sql` unless I ask.

---

## Gate 1 — preflight

```bash
ssh nihonarubaito 'whoami && cd www/nihonarubaito.com/public_html/laravel && \
  DB=$(grep -E "^DB_DATABASE=" .env | cut -d= -f2- | tr -d "\"" | tr -d "'"'"'" | xargs) && \
  echo "db=$DB" && test -n "$DB" && echo ok'
```

## Gate 2 — export published jobs (read-only)

```bash
ssh nihonarubaito "bash -s" <<'REMOTE'
set -euo pipefail
cd ~/www/nihonarubaito.com/public_html/laravel
val() { grep -E "^$1=" .env | cut -d= -f2- | sed -e 's/^["'"'"']//' -e 's/["'"'"']$//'; }
CNF=$(mktemp); chmod 600 "$CNF"; trap 'rm -f "$CNF"' EXIT
printf '[client]\nuser="%s"\npassword="%s"\nhost="%s"\n' "$(val DB_USERNAME)" "$(val DB_PASSWORD)" "$(val DB_HOST)" > "$CNF"
DB=$(val DB_DATABASE)
OUT=~/exports/dedupe-$(date +%Y%m%d-%H%M); mkdir -p "$OUT"
mysql --defaults-extra-file="$CNF" --batch "$DB" -e "
  SELECT job_no, job_status_id, \`date\`, updated_at, Expire_Date, delete_at,
         company_name, address, station, job_category_id, wage, title, apply_link
    FROM jobs WHERE lang_id = 1 AND job_status_id = 3" > "$OUT/published.tsv"
wc -l "$OUT/published.tsv"
echo "OUT=$OUT"
REMOTE
```

Expect roughly 5,500–6,500 lines. Copy the folder here (never into the repo):

```bash
mkdir -p ~/nihon-reports && scp -r nihonarubaito:<OUT path> ~/nihon-reports/
```

## Gate 3 — plan (local)

```bash
python3 -I scripts/dedupe-plan.py --published ~/nihon-reports/<folder>/published.tsv \
  --out-dir ~/nihon-reports/<folder>/plan
```

Show the printed summary.

## Gate 4 — evidence, then STOP

1. The summary (groups, kept, relinked, trashed, review pairs).
2. **20 groups from `plan.csv`**: the 10 largest groups and 10 random ones, every column.
3. **Positive control**: confirm `162280` (keep) / `163986` (trash) appears as a group if both are still published, with 162280 taking `offer/121462867`. If either is no longer published, say so and pick another pair I can check by eye.
4. **Negative control**: name one company with two published jobs at **different street addresses** and confirm they are NOT in the plan.
5. Count of `review.csv` pairs and the first 10.
6. Run the precheck against production (read-only) and show the result — it must be empty:

```bash
scp ~/nihon-reports/<folder>/plan/precheck.sql nihonarubaito:<OUT path>/
```
then run it with **Run SQL file** (below) on `<OUT path>/precheck.sql`.

**STOP.** Wait for me to say `apply <out-dir>`.

### Run SQL file (used by Gates 4–6)

```bash
FILE=<OUT path>/<name>.sql   # a file already on the server
ssh nihonarubaito "bash -s" -- "$FILE" <<'REMOTE'
set -euo pipefail
FILE="$1"; cd ~/www/nihonarubaito.com/public_html/laravel
val() { grep -E "^$1=" .env | cut -d= -f2- | sed -e 's/^["'"'"']//' -e 's/["'"'"']$//'; }
CNF=$(mktemp); chmod 600 "$CNF"; trap 'rm -f "$CNF"' EXIT
printf '[client]\nuser="%s"\npassword="%s"\nhost="%s"\n' "$(val DB_USERNAME)" "$(val DB_PASSWORD)" "$(val DB_HOST)" > "$CNF"
mysql --defaults-extra-file="$CNF" --default-character-set=utf8mb4 "$(val DB_DATABASE)" < "$FILE"
REMOTE
```

---

## Gate 5 — backup (only after my "apply")

Copy the plan files to the server and take a fresh dump of the affected rows plus both history tables, with `--complete-insert`:

```bash
scp ~/nihon-reports/<folder>/plan/{apply.sql,rollback.sql,precheck.sql,backup.csv} nihonarubaito:<OUT path>/
```

On the server, with the same temporary defaults file:
`mysqldump --complete-insert --single-transaction --no-create-info "$DB" jobs --where="job_no IN (<every job_no in plan.csv>)" > <OUT>/jobs-before.sql` and
`mysqldump --complete-insert --single-transaction "$DB" job_status_history job_apply_link_history > <OUT>/history-before.sql`.
Show `ls -l` and `wc -l` of both. Then re-run the precheck: **must be empty**, else STOP.

## Gate 6 — apply

Run **Run SQL file** on `<OUT path>/apply.sql`. Show the final counts it prints (trashed / kept / relinked).
They must equal the plan's numbers. If any count is lower, some rows changed since the export: report which (compare `job_status_history` for the batch with `plan.csv`), do NOT re-plan or retry.

## Gate 7 — verify live

- For 3 trashed jobs: `curl -s -o /dev/null -w "%{http_code} %{size_download}\n" -A "Mozilla/5.0 (Macintosh)" https://nihonarubaito.com/jobs/<job_no>/detail` (no `-L`) → 200 with the expired page (noindex), since Expire_Date is today.
- For their 3 keepers: 200, and the page's apply link is the new one.
- A published control job outside the plan: unchanged.
- Remind me to flush SiteGround Dynamic Cache and purge the CDN.

## Gate 8 — report

Batch id, the three counts, the backup paths, and the rollback command (do not run it). Add a ledger entry draft to `docs/ledger.md` (do not commit unless I ask).
