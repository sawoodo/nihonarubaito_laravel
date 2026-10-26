---
description: Read-only check of jobs created in the last N days (default 2) — how many were duplicates of a live job, re-translations of a recently expired job, or the same listing with a changed apply link. Writes a report; changes nothing.
argument-hint: "[days=2]"
---

# /check-translator-duplicates — were the last N days of jobs really new?

**Window:** `$ARGUMENTS` days (empty → **2**). Call it `DAYS` below.

Translators check for an existing job by pasting the source URL into the admin search. Shigotoin changes URLs, so the search misses and the job gets translated again. This command measures how often that happened in the window.

## Rules (non-negotiable)

- **READ-ONLY.** Only `SELECT` statements. No `UPDATE`, `INSERT`, `DELETE`, `ALTER`, no artisan command that writes, no file changes on the server except one temporary credentials file that is deleted in the same command.
- **Every SSH command names its target** (`ssh nihonarubaito …`). Never run any of this against a local database.
- **DB name comes from `.env`**, never hardcoded. Never print the password or the contents of `.env`.
- **Show raw output at every gate.** A summary never clears a gate.
- Analysis runs **locally** with `python3 -I scripts/translator-dup-check.py`. Do not edit that script during this command; if it fails, stop and report the error.
- Stop after Gate 4. Do not expire, trash, merge or edit any job — the report is for a human.

---

## Gate 1 — preflight (STOP on anything unexpected)

```bash
ssh nihonarubaito 'whoami && cd www/nihonarubaito.com/public_html/laravel && \
  DB=$(grep -E "^DB_DATABASE=" .env | cut -d= -f2- | tr -d "\"" | tr -d "'"'"'" | xargs) && \
  echo "db=$DB" && test -n "$DB" && echo ok'
```

Expect `u4602-zegubfmytubg`, a non-empty `db=…`, then `ok`.

## Gate 2 — export (read-only SELECTs → TSV on the server, then copy here)

Run this exactly, with `DAYS` substituted (integer only — refuse anything else):

```bash
DAYS=2   # ← replace with the window
ssh nihonarubaito "bash -s" -- "$DAYS" <<'REMOTE'
set -euo pipefail
DAYS="$1"; case "$DAYS" in (*[!0-9]*|'') echo "bad DAYS"; exit 1;; esac
cd ~/www/nihonarubaito.com/public_html/laravel
val() { grep -E "^$1=" .env | cut -d= -f2- | sed -e 's/^["'"'"']//' -e 's/["'"'"']$//'; }
CNF=$(mktemp); chmod 600 "$CNF"; trap 'rm -f "$CNF"' EXIT
printf '[client]\nuser="%s"\npassword="%s"\nhost="%s"\n' "$(val DB_USERNAME)" "$(val DB_PASSWORD)" "$(val DB_HOST)" > "$CNF"
DB=$(val DB_DATABASE)
OUT=~/exports/dupcheck-$(date +%Y%m%d-%H%M); mkdir -p "$OUT"
COLS='job_no, job_status_id, `date`, updated_at, Expire_Date, user_id, company_name, address, job_category_id, title, apply_link'
mysql --defaults-extra-file="$CNF" --batch "$DB" -e "
  SELECT $COLS FROM jobs
   WHERE lang_id = 1 AND job_status_id IN (3,4,5,6)
     AND \`date\` >= NOW() - INTERVAL $DAYS DAY" > "$OUT/recent.tsv"
mysql --defaults-extra-file="$CNF" --batch "$DB" -e "
  SELECT $COLS FROM jobs
   WHERE lang_id = 1 AND job_status_id IN (2,3,4,5,6)
     AND (job_status_id IN (2,3,6) OR \`date\` >= NOW() - INTERVAL 400 DAY)" > "$OUT/pool.tsv"
mysql --defaults-extra-file="$CNF" --batch "$DB" -e "
  SELECT id, first_name, last_name FROM users
   WHERE id IN (SELECT DISTINCT user_id FROM jobs WHERE \`date\` >= NOW() - INTERVAL $DAYS DAY)" > "$OUT/users.tsv"
mysql --defaults-extra-file="$CNF" --batch "$DB" -e "SELECT NOW() AS server_now"
wc -l "$OUT"/*.tsv
echo "OUT=$OUT"
REMOTE
```

Show the output. Expect `recent.tsv` with roughly 100–300 lines for 2 days (≈80 jobs/day + header), `pool.tsv` in the tens of thousands. **If `recent.tsv` has only the header line, STOP** and report — the window or the `date` column is wrong, not "no duplicates".

Copy the folder printed as `OUT=` to this machine (never into the repo):

```bash
mkdir -p ~/nihon-reports && scp -r nihonarubaito:<OUT path> ~/nihon-reports/
```

## Gate 3 — analyse locally

```bash
python3 -I scripts/translator-dup-check.py \
  --recent ~/nihon-reports/<folder>/recent.tsv \
  --pool   ~/nihon-reports/<folder>/pool.tsv \
  --users  ~/nihon-reports/<folder>/users.tsv \
  --out-dir ~/nihon-reports --days <DAYS>
```

Show the printed summary in full.

**Positive control (blocking):** open the CSV and confirm at least one known pair appears if the window contains one. If the window has zero flagged jobs, pick 2 flagged-looking rows by hand (same company in `recent.tsv` and `pool.tsv`) and confirm the script's verdict for them is right. An empty result without this check does not count.

## Gate 4 — report and STOP

Paste the top of the `.md` report (totals table + by-translator table) and the first 15 flagged rows. Then list, plainly:

1. How many jobs were **DUPLICATE_LIVE** (an older copy is still live — one of the two should go).
2. How many were **REPOST_RECENT** (the listing was retired ≤ 60 days ago — should have been revived with the new link).
3. Of those, how many had a **CHANGED** link (the source moved the listing; the existing job should have had its link updated).
4. Any `shop~` matches (same address + category, different company) — flag these as "check by eye".

Do not act on any job. Remove nothing from `~/exports/` on the server (it holds no credentials; it can be cleaned later by hand).

---

### How the matching works (for reading the report)

| Basis | Means |
|---|---|
| `link` | same apply link after removing tracking parameters |
| `source-id` | same site and listing id (shigotoin `offer/N`, baitoru `jobN`, FamilyMart `new_entry/N`) |
| `shop` | same normalised address (full-width → half-width, 丁目/番地/号 → dashes, building name dropped) and same company name |
| `shop~` | same normalised address and same category, different company name — medium confidence |

Only a job created **before** the new one counts as its original. Two new jobs for one listing inside the window show the newer one as the duplicate.
