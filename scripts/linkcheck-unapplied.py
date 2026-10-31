#!/usr/bin/env python3
"""
linkcheck-unapplied.py -- did every upload file from the daily runs reach the
Link Check page? Reads local files only; the two TSVs come from Gate 1 of
/daily-link-check.

  --jobs     job_no, delete_at of every published job
  --history  job_no, source_checked_at of the Link Check history

A row was applied if its job has a history row checked at or after the row's
own checked_at. A row is still waiting if its job is published with the same
delete_at and was not applied.

  NEVER APPLIED  no row applied, at least one waiting  -> exit 1, apply it first
  TOO OLD        the same, but scanned over MAX_AGE_DAYS ago -> notice only: don't
                 upload it, today's export scans those jobs again
  (partly)       some rows applied: the rest were held on the page, not a miss

Row-by-row rather than by batch id, because a batch doesn't record its file: the
two 7-Eleven files of 2026-10-30 are one scan, and only one needs a batch.
"""
import argparse
import csv
import glob
import os
import sys
from datetime import datetime, timezone

MAX_AGE_DAYS = 3


def read_tsv(path):
    with open(path, encoding='utf-8', newline='') as f:
        rows = [line.rstrip('\n').split('\t') for line in f]
    return rows[1:]  # header


def utc(checked_at):
    return checked_at.replace('T', ' ').replace('Z', '')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--jobs', required=True)
    parser.add_argument('--history', required=True)
    parser.add_argument('--root', default=os.path.expanduser('~/nihon-linkcheck'))
    args = parser.parse_args()

    published = dict(read_tsv(args.jobs))
    last_checked = {}
    for job_no, checked in read_tsv(args.history):
        last_checked[job_no] = max(checked, last_checked.get(job_no, ''))

    if not published:
        sys.exit('ERROR: no published jobs in --jobs, the export failed')

    now = datetime.now(timezone.utc).replace(tzinfo=None)
    never, old = [], []
    files = sorted(glob.glob(os.path.join(args.root, 'daily-*', 'upload-*.csv')))
    for path in files:
        with open(path, encoding='utf-8', newline='') as f:
            rows = list(csv.DictReader(f))
        applied = [r for r in rows if last_checked.get(r['job_no'], '') >= utc(r['checked_at'])]
        waiting = [r for r in rows if published.get(r['job_no']) == r['delete_at'] and r not in applied]
        if applied or not waiting:
            continue
        newest = max(utc(r['checked_at']) for r in rows)
        age = (now - datetime.strptime(newest, '%Y-%m-%d %H:%M:%S')).days
        (old if age > MAX_AGE_DAYS else never).append((path, len(rows), len(waiting), newest))

    print(f'{len(files)} upload files checked')
    for path, total, waiting, newest in old:
        print(f'TOO OLD, do not upload: {path} ({waiting} of {total} waiting, scanned {newest} UTC)')
    if never:
        print('APPLY THESE FIRST:')
        for path, total, waiting, newest in never:
            print(f'  {path} ({waiting} of {total} waiting, scanned {newest} UTC)')
        sys.exit(1)
    print('OK: no upload file is waiting')


if __name__ == '__main__':
    main()
