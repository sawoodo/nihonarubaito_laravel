#!/usr/bin/env python3
"""
build-linkcheck-files.py -- turn a link-check results CSV into the two files
the apply steps consume. Generates only; applies nothing.

  1. upload-<host>-<ts>.csv   the actionable rows, for the admin Link Check
                              page (preview -> Apply). Same columns as the
                              results file, so it parses with no translation.
  2. expire-<host>-<ts>-*.sql precheck / apply / rollback for the jobs whose
                              source page is gone, plus a backup CSV.

WHAT COUNTS AS ACTIONABLE
  OPEN                     confirmed live               -> extend
  MOVED, id preserved      same listing, new URL        -> relink + extend
  MOVED, id changed        may be a different job       -> HELD, staff review
  MOVED, no id to compare  unverifiable                 -> HELD, staff review
  CLOSED / GONE            source page is gone          -> expire list
  ERROR                    the CHECK failed, not the job -> HELD, never expired

Every row is re-checked against a LIVE snapshot before it reaches either file:
the job must still be status 3, and its stored apply_link must still be the
exact URL the scan fetched. A scan takes hours, and anything that moved in the
meantime is stale evidence. The same two conditions are repeated as SQL guards
inside the expire statements, so a row that drifts between generating and
applying updates nothing rather than the wrong thing.

The ERROR exclusion is the one that matters. 404 means the employer's page is
gone; a timeout means our own request failed. Expiring on a timeout retires
live jobs and loses revenue, and the 2026-10-23 run produced 4 timeouts purely
because the laptop slept mid-request.

USAGE
  build-linkcheck-files.py --results R.csv --snapshot S.tsv --host baitoru
  ... --out-dir ~/nihon-linkcheck [--require-complete 1360]
"""

import argparse
import csv
import os
import sys
from collections import Counter
from datetime import datetime

EXTEND_DAYS = 7
STATUS_PUBLISHED = '3'
STATUS_EXPIRED = 4

UPLOAD_COLUMNS = [
    'checked_at', 'job_no', 'delete_at', 'requested_url', 'http_code',
    'num_redirects', 'final_url', 'bytes', 'classification',
    'closed_markers', 'employer_token', 'source_title', 'note',
]


def sql_str(v):
    """Single-quoted SQL literal. Doubles quotes and escapes backslashes."""
    s = '' if v is None else str(v)
    return "'" + s.replace('\\', '\\\\').replace("'", "''") + "'"


def sql_or_null(v):
    """NULL for empty / NULL exports, else a quoted literal."""
    s = '' if v is None else str(v).strip()
    return 'NULL' if s in ('', 'NULL') else sql_str(s)


def sql_ts(v):
    """'2026-10-23T03:20:00Z' (link-check, UTC) -> '2026-10-23 03:20:00'.
    The server and MySQL both run in UTC, so no shift is needed."""
    s = '' if v is None else str(v).strip()
    m = __import__('re').match(r'^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2}:\d{2})', s)
    return sql_str(f'{m.group(1)} {m.group(2)}') if m else 'NULL'


def ids_in(url):
    import re
    return set(re.findall(r'\d{5,}', url or ''))


def id_preserved(requested, final):
    """Mirrors MovedTargetMatcher::sameListingId so both agree."""
    a, b = ids_in(requested), ids_in(final)
    if not a or not b:
        return None          # nothing to compare
    return bool(a & b)


def load_latest(path):
    """Last row per job_no wins; the results file is append-only."""
    latest = {}
    with open(path, encoding='utf-8', newline='') as fh:
        for row in csv.DictReader(fh):
            job_no = (row.get('job_no') or '').strip()
            cls = (row.get('classification') or '').strip()
            if job_no and cls:
                latest[job_no] = row
    return latest


def load_snapshot(path):
    snap = {}
    with open(path, encoding='utf-8', newline='') as fh:
        for row in csv.DictReader(fh, delimiter='\t'):
            job_no = (row.get('job_no') or '').strip()
            if job_no:
                snap[job_no] = row
    return snap


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--results', required=True)
    ap.add_argument('--snapshot', required=True)
    ap.add_argument('--host', required=True)
    ap.add_argument('--out-dir', default=os.path.expanduser('~/nihon-linkcheck'))
    ap.add_argument('--require-complete', type=int, default=0,
                    help='refuse to generate unless the results file has at '
                         'least this many rows (guards against a part-done scan)')
    ap.add_argument('--dry-run', action='store_true',
                    help='print the numbers, write nothing')
    args = ap.parse_args()

    results = load_latest(args.results)
    snap = load_snapshot(args.snapshot)
    ts = datetime.now().strftime('%Y%m%d-%H%M%S')

    print(f'results : {args.results}  ({len(results)} jobs)')
    print(f'snapshot: {args.snapshot}  ({len(snap)} jobs)')

    if args.require_complete and len(results) < args.require_complete:
        sys.exit(f'\nREFUSING: results file has {len(results)} rows, '
                 f'expected at least {args.require_complete}. The scan is not '
                 f'finished. Re-run when it is, or lower --require-complete '
                 f'deliberately.')

    extend, relink, expire = [], [], []
    held = []
    verdicts = Counter()
    excluded = Counter()

    for job_no, r in sorted(results.items()):
        cls = (r.get('classification') or '').strip()
        requested = (r.get('requested_url') or '').strip()
        final = (r.get('final_url') or '').strip()
        verdicts[cls] += 1

        job = snap.get(job_no)
        if not job:
            excluded['job not in snapshot'] += 1
            continue
        if (job.get('job_status_id') or '').strip() != STATUS_PUBLISHED:
            excluded[f"no longer published (status {job.get('job_status_id')})"] += 1
            continue
        if (job.get('apply_link') or '').strip() != requested:
            excluded['apply_link changed since the scan'] += 1
            continue

        if cls == 'ERROR':
            held.append((job_no, 'ERROR', r.get('note', '')))
            continue

        if cls in ('CLOSED', 'GONE'):
            expire.append((job_no, r, job))
            continue

        if cls == 'OPEN':
            extend.append((job_no, r, job))
            continue

        if cls == 'MOVED':
            keep = id_preserved(requested, final)
            if keep is True:
                relink.append((job_no, r, job))
            elif keep is False:
                held.append((job_no, 'MOVED id_changed', 'redirect changed the listing id'))
            else:
                held.append((job_no, 'MOVED no id', 'no listing id to compare'))
            continue

        held.append((job_no, cls or '(blank)', 'unrecognised classification'))

    actionable = extend + relink

    print('\nscan verdicts (all rows):')
    for k, v in verdicts.most_common():
        print(f'  {k:<10} {v:>5}')

    print('\nafter re-checking against the live snapshot:')
    print(f'  extend only (OPEN)            {len(extend):>5}')
    print(f'  relink + extend (MOVED, id kept) {len(relink):>5}')
    print(f'  -> upload file total          {len(actionable):>5}')
    print(f'  expire (CLOSED/GONE)          {len(expire):>5}')
    print(f'  held for staff / never auto   {len(held):>5}')
    if held:
        for k, v in Counter(h[1] for h in held).most_common():
            print(f'       {k:<22} {v}')
    if excluded:
        print('  excluded as stale:')
        for k, v in excluded.most_common():
            print(f'       {k:<40} {v}')

    tot = len(actionable) + len(expire) + len(held) + sum(excluded.values())
    print(f'\n  reconciliation: {tot} accounted for of {len(results)} scanned '
          + ('OK' if tot == len(results) else '*** MISMATCH ***'))

    if args.dry_run:
        print('\n--dry-run: nothing written.')
        return

    os.makedirs(args.out_dir, exist_ok=True)
    out = lambda n: os.path.join(args.out_dir, n)                   # noqa: E731

    # ---- 1. upload CSV -----------------------------------------------------
    upload_path = out(f'upload-{args.host}-{ts}.csv')
    with open(upload_path, 'w', newline='', encoding='utf-8') as fh:
        w = csv.DictWriter(fh, fieldnames=UPLOAD_COLUMNS, extrasaction='ignore')
        w.writeheader()
        for _job_no, r, _job in actionable:
            w.writerow({k: r.get(k, '') for k in UPLOAD_COLUMNS})

    # ---- 2. expire set -----------------------------------------------------
    batch = f'be-{ts}'
    backup_path = out(f'expire-{args.host}-{ts}-backup.csv')
    with open(backup_path, 'w', newline='', encoding='utf-8') as fh:
        w = csv.writer(fh)
        w.writerow(['job_no', 'job_status_id', 'apply_link', 'delete_at',
                    'updated_at', 'expire_date', 'title', 'http_code', 'checked_at'])
        for job_no, r, job in expire:
            w.writerow([job_no, job.get('job_status_id'), job.get('apply_link'),
                        job.get('delete_at'), job.get('updated_at'),
                        job.get('expire_date'), job.get('title'),
                        r.get('http_code'), r.get('checked_at')])

    guard = ' OR '.join(
        f"(job_no={sql_str(j)} AND job_status_id=3 AND apply_link={sql_str(job.get('apply_link'))})"
        for j, _r, job in expire)

    precheck_path = out(f'expire-{args.host}-{ts}-precheck.sql')
    with open(precheck_path, 'w', encoding='utf-8') as fh:
        fh.write(f'-- {batch}: must return exactly {len(expire)}\n')
        fh.write('SELECT COUNT(*) AS guard_matches FROM jobs WHERE '
                 + (guard or '1=0') + ';\n')

    apply_path = out(f'expire-{args.host}-{ts}-apply.sql')
    with open(apply_path, 'w', encoding='utf-8') as fh:
        fh.write(f'-- {batch}: expire {len(expire)} {args.host} jobs whose '
                 f'source page returned 404/410.\n')
        fh.write('-- Guarded on status 3 AND the exact apply_link the scan fetched.\n')
        fh.write('-- updated_at is bumped because the expired-page tier (noindex /\n')
        fh.write('-- 301 / 410) is computed from Expire_Date ?? updated_at ?? date;\n')
        fh.write('-- a stale updated_at sends a just-expired page straight to 410.\n\n')
        fh.write('-- Expire_Date = NOW() starts the expiry clock on the real retirement\n')
        fh.write('-- day; one job_status_history row per expired job, same batch id.\n')
        fh.write('-- Self-contained: runs in one transaction, prints its counts, commits.\n\n')
        fh.write('SET NAMES utf8mb4;\nSTART TRANSACTION;\n\n')
        for job_no, r, job in expire:
            reason = f"link-check {r.get('classification', '')}: source page closed"
            fh.write(f'UPDATE jobs SET job_status_id={STATUS_EXPIRED}, Expire_Date=NOW(), updated_at=NOW() '
                     f'WHERE job_no={sql_str(job_no)} AND job_status_id=3 '
                     f'AND apply_link={sql_str(job.get("apply_link"))};\n'
                     f'SET @r = ROW_COUNT();\n'
                     f'INSERT INTO job_status_history (job_no, old_status, new_status, old_delete_at, '
                     f'new_delete_at, source, actor, reason, batch, source_checked_at, created_at) '
                     f'SELECT {sql_str(job_no)}, 3, {STATUS_EXPIRED}, {sql_or_null(job.get("delete_at"))}, '
                     f'{sql_or_null(job.get("delete_at"))}, \'bulk-expire\', \'claude-code\', {sql_str(reason)}, '
                     f'{sql_str(batch)}, {sql_ts(r.get("checked_at"))}, NOW() FROM DUAL WHERE @r = 1;\n')
        fh.write(f"\nSELECT COUNT(*) AS expired_in_batch FROM job_status_history "
                 f"WHERE batch={sql_str(batch)} AND new_status={STATUS_EXPIRED};\n")
        fh.write(f'-- expected: {len(expire)}. Fewer = rows changed since the scan; they were skipped.\n')
        fh.write('COMMIT;\n')

    rollback_path = out(f'expire-{args.host}-{ts}-rollback.sql')
    with open(rollback_path, 'w', encoding='utf-8') as fh:
        fh.write(f'-- ROLLBACK for {batch}. Restores status 3, the original Expire_Date and\n')
        fh.write('-- updated_at of each row this batch expired, and deletes its history rows.\n\n')
        fh.write('SET NAMES utf8mb4;\nSTART TRANSACTION;\n\n')
        for job_no, _r, job in expire:
            u = (job.get('updated_at') or '').strip()
            setu = f', updated_at={sql_str(u)}' if u else ''
            fh.write(f'UPDATE jobs SET job_status_id=3, Expire_Date={sql_or_null(job.get("expire_date"))}{setu} '
                     f'WHERE job_no={sql_str(job_no)} AND job_status_id={STATUS_EXPIRED} '
                     f'AND job_no IN (SELECT job_no FROM job_status_history WHERE batch={sql_str(batch)});\n')
        fh.write(f'\nDELETE FROM job_status_history WHERE batch={sql_str(batch)};\nCOMMIT;\n')

    held_path = out(f'held-{args.host}-{ts}.csv')
    with open(held_path, 'w', newline='', encoding='utf-8') as fh:
        w = csv.writer(fh)
        w.writerow(['job_no', 'reason', 'detail'])
        w.writerows(held)

    print(f'\nbatch id: {batch}')
    for p in (upload_path, backup_path, precheck_path, apply_path,
              rollback_path, held_path):
        print(f'  {p}  ({os.path.getsize(p)}B)')
    print('\nNothing applied. The upload CSV goes to the admin Link Check page; '
          'the expire SQL runs precheck -> apply (it commits and prints its count).')


if __name__ == '__main__':
    main()
