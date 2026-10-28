#!/usr/bin/env python3
"""
audit-translations-sources.py -- fetch the source pages for a sample of jobs
so a reviewer can compare our translation with the original listing.

READ-ONLY. Needs the Japan VPN (shigotoin blocks non-JP IPs). Reuses
link-check.py's fetch() and CA bundle, so certificates are verified the same
way. Writes one text file per job: <out-dir>/<job_no>.source.txt (the page
text, scripts/styles removed) and <job_no>.ours.txt (our stored fields).

USAGE
  python3 -I audit-translations-sources.py --jobs jobs.tsv --out-dir DIR \
      [--sample 10] [--delay 6] [--seed 1]
Sample = v2-format, published or pending, shigotoin jobs, picked at random
(seeded, so a rerun picks the same jobs).
"""

import argparse
import html
import importlib.util
import os
import random
import re
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))


def load(name, file):
    spec = importlib.util.spec_from_file_location(name, os.path.join(HERE, file))
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


lc = load('link_check', 'link-check.py')
at = load('audit_translations', 'audit-translations.py')


def page_text(body):
    body = re.sub(r'(?is)<(script|style|noscript)\b.*?</\1>', ' ', body)
    body = re.sub(r'(?i)<br\s*/?>|</(p|div|li|tr|h\d|dt|dd)>', '\n', body)
    body = re.sub(r'<[^>]+>', ' ', body)
    body = html.unescape(body)
    lines = [re.sub(r'[ \t　]+', ' ', ln).strip() for ln in body.split('\n')]
    return '\n'.join(ln for ln in lines if ln)


OURS = ['title', 'wage', 'wage_detail', 'working_hours', 'working_days',
        'station', 'requirement', 'benefits', 'tags', 'description']


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--jobs', required=True)
    ap.add_argument('--out-dir', required=True)
    ap.add_argument('--sample', type=int, default=10)
    ap.add_argument('--delay', type=float, default=6.0)
    ap.add_argument('--seed', type=int, default=1)
    a = ap.parse_args()

    ok, country, org = lc.check_jp()
    print(f'VPN check: country={country} org={org}')
    if not ok:
        sys.exit('STOP: not on a Japan IP. Turn the Japan VPN on and rerun.')

    jobs = at.read_tsv(a.jobs)
    pool = [j for j in jobs
            if 'shigotoin' in (j.get('apply_link') or '')
            and j.get('job_status_id') in ('2', '3')
            and all(h in (j.get('description') or '') for h in at.HEADINGS)]
    print(f'candidates: {len(pool)} v2 shigotoin jobs (published/pending)')
    if not pool:
        sys.exit('STOP: no v2 shigotoin jobs in the export')
    random.Random(a.seed).shuffle(pool)
    os.makedirs(a.out_dir, exist_ok=True)

    got = 0
    for j in pool:
        if got >= a.sample:
            break
        res = lc.fetch(j['apply_link'])
        status = res.get('code') if res.get('ok') else res.get('err')
        text = page_text(res.get('body', '')) if res.get('ok') else ''
        closed = any(p in text for p in ('この求人の募集は終了しました', '掲載終了しています'))
        print(f"{j['job_no']}: {status} {len(text)} chars"
              f"{' CLOSED' if closed else ''} {res.get('final', '')}")
        if res.get('ok') and res.get('code') == '200' and not closed and len(text) > 500:
            with open(os.path.join(a.out_dir, f"{j['job_no']}.source.txt"), 'w',
                      encoding='utf-8') as fh:
                fh.write(f"URL {res.get('final')}\n\n{text}\n")
            with open(os.path.join(a.out_dir, f"{j['job_no']}.ours.txt"), 'w',
                      encoding='utf-8') as fh:
                for k in OURS:
                    fh.write(f'== {k}\n{j.get(k, "")}\n\n')
            got += 1
        time.sleep(a.delay)
    print(f'saved {got} source/ours pairs in {a.out_dir}')


if __name__ == '__main__':
    main()
