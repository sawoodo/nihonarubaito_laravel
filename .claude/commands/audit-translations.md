---
description: Read-only audit of jobs created in the last N days (default 7) against the translation rules (v2) — title role vs tasks, wage in title, payment hook, shifts, working days, Japanese level, tags, banned characters, sections. Optional deep check compares a sample with the source listing. Changes nothing.
argument-hint: "[days=7] [deep]"
---

# /audit-translations — are translators following the v2 rules?

**Arguments:** `$ARGUMENTS`. First number = window in days (empty → **7**), call it `DAYS`. The word `deep` anywhere → also run Gate 5 (needs the Japan VPN).

The rules being checked are the translation project's instructions, v2 (claude.ai project doc `claude/translation-instructions-v2.md`). Translators switched to v2 on 2026-10-27. Jobs before that are "old" format and are reported separately, so the report shows before vs after.

## Rules (non-negotiable)

- **READ-ONLY.** Only `SELECT`. No `UPDATE`/`INSERT`/`DELETE`/`ALTER`, no writing artisan command, no file changes on the server except one temporary credentials file deleted in the same command.
- **Every SSH command names its target** (`ssh nihonarubaito …`). Never against a local database.
- **DB name from `.env`**, never hardcoded. Never print the password or `.env`.
- **Raw output at every gate.** A summary never clears a gate.
- Analysis runs locally: `python3 -I scripts/audit-translations.py`. Don't edit the script during the command; if it fails, stop and report.
- Do not edit, expire or trash any job. The report is for a human.

---

## Gate 1 — preflight + self-test (STOP on anything unexpected)

```bash
python3 -I scripts/audit-translations.py --selftest
ssh nihonarubaito 'whoami && cd www/nihonarubaito.com/public_html/laravel && \
  DB=$(grep -E "^DB_DATABASE=" .env | cut -d= -f2- | tr -d "\"" | tr -d "'"'"'" | xargs) && \
  echo "db=$DB" && test -n "$DB" && echo ok'
```

Expect `SELFTEST PASS` (every rule fires on its bad case, a clean v2 job fires none), then `u4602-zegubfmytubg`, `db=…`, `ok`. **A failed self-test means the report can't be trusted — STOP.**

## Gate 2 — export

```bash
DAYS=7   # ← replace with the window (integer only)
ssh nihonarubaito "bash -s" -- "$DAYS" <<'REMOTE'
set -euo pipefail
DAYS="$1"; case "$DAYS" in (*[!0-9]*|'') echo "bad DAYS"; exit 1;; esac
cd ~/www/nihonarubaito.com/public_html/laravel
val() { grep -E "^$1=" .env | cut -d= -f2- | sed -e 's/^["'"'"']//' -e 's/["'"'"']$//'; }
CNF=$(mktemp); chmod 600 "$CNF"; trap 'rm -f "$CNF"' EXIT
printf '[client]\nuser="%s"\npassword="%s"\nhost="%s"\n' "$(val DB_USERNAME)" "$(val DB_PASSWORD)" "$(val DB_HOST)" > "$CNF"
DB=$(val DB_DATABASE)
OUT=~/exports/translation-audit-$(date +%Y%m%d-%H%M); mkdir -p "$OUT"
mysql --defaults-extra-file="$CNF" --batch "$DB" -e "
  SELECT j.id, j.job_no, j.job_status_id, j.\`date\`, j.user_id, j.title, j.description,
         j.wage, j.wage_type_id, j.wage_detail, j.working_hours, j.working_days,
         j.requirement, j.benefits, j.station, j.apply_link, j.job_category_id,
         (SELECT GROUP_CONCAT(t.slug ORDER BY t.slug)
            FROM job_tag jt JOIN tags t ON t.id = jt.tag_id
           WHERE jt.job_id = j.id) AS tags
    FROM jobs j
   WHERE j.lang_id = 1 AND j.job_status_id IN (2,3,4,5,6)
     AND j.\`date\` >= NOW() - INTERVAL $DAYS DAY" > "$OUT/jobs.tsv"
mysql --defaults-extra-file="$CNF" --batch "$DB" -e "
  SELECT id, first_name, last_name FROM users
   WHERE id IN (SELECT DISTINCT user_id FROM jobs WHERE \`date\` >= NOW() - INTERVAL $DAYS DAY)" > "$OUT/users.tsv"
mysql --defaults-extra-file="$CNF" --batch "$DB" -e "SELECT NOW() AS server_now,
  (SELECT COUNT(*) FROM job_tag) AS job_tag_rows"
wc -l "$OUT"/*.tsv
echo "OUT=$OUT"
REMOTE
mkdir -p ~/nihon-reports && scp -r nihonarubaito:<OUT path> ~/nihon-reports/
```

Expect `jobs.tsv` ≈ 80 × DAYS lines. **Header only → STOP** (the window is wrong, not "all clean"). `job_tag_rows` 0 → tags aren't stored; say so and read the tag rules as "not measurable".

## Gate 3 — analyse

```bash
python3 -I scripts/audit-translations.py --jobs ~/nihon-reports/<folder>/jobs.tsv \
  --users ~/nihon-reports/<folder>/users.tsv --out-dir ~/nihon-reports --days <DAYS>
```

Show the printed report in full.

**Positive control (blocking):** pick 3 flagged v2 jobs (prefer one ERROR from each of two different rules, plus one CHECK) and show the stored fields that triggered each flag. Say for each whether the flag is right. Also pick 2 v2 jobs with **no** flags and read their title + "What you will do" + working_hours by eye: is anything wrong that the script missed? A heuristic rule that was wrong on 2 of 3 controls gets reported as unreliable, not as a count.

## Gate 4 — report

Plainly, in this order:
1. **Adoption:** v2 / partial / old by day and by translator. Anyone still on old format after 27 Oct?
2. **ERRORs on v2 jobs**, per rule, with counts and 3 job numbers each. These are mistakes to fix (list them for the team; don't fix them).
3. **CHECKs on v2 jobs** that the controls showed are real, with counts. Say which CHECK rules turned out noisy.
4. **v2 vs old** on the rules both can be judged by (title wage, payment hook, `&amp;`, no-Japanese claim, 24h notation, overnight, tags, old default working days). Did v2 reduce them?
5. **What to tell the translator(s):** at most 3 short points, from the most frequent real ERRORs.
6. **Rule changes to consider** in the v2 instructions, only where the same mistake repeats (≥ 3 jobs).

If `deep` was not asked for, STOP here.

## Gate 5 — deep check against the source (only with `deep`; Japan VPN ON)

```bash
python3 -I scripts/audit-translations-sources.py --jobs ~/nihon-reports/<folder>/jobs.tsv \
  --out-dir ~/nihon-reports/<folder>/sources --sample 10 --delay 6
```

It refuses to run off a Japan IP and skips closed listings. For each saved pair (`<job_no>.source.txt` vs `<job_no>.ours.txt`), read both and score six checks, ✅ / ❌ with the evidence quoted (Japanese source line vs our line):

| # | Check |
|---|---|
| 1 | Title role matches the tasks in 仕事内容 (all roles of the one offer named; none added) |
| 2 | Every shift in the source is listed separately, with its own conditions (minimum hours, age, wage) |
| 3 | Wage exact: base, title wage = starting wage, other slots/premiums in wage_detail |
| 4 | Nothing invented: walk times, working days, benefits, stations, payment method all present in the source |
| 5 | Japanese level copied as stated (or absent if the source says nothing); no "no Japanese needed" unless the source says 日本語不要 |
| 6 | Tags supported by the source (night-shift, weekend, hand-cash, near-station ≤ 5 min) |

Report a 10 × 6 table, the total ✅ per check, and every ❌ with both quotes. The same six checks are the yardstick for a model comparison (Sonnet vs Sonnet + thinking vs Opus): the owner pastes the saved `source.txt` files into each model and the outputs are scored on this table.

STOP. Don't edit any job.
