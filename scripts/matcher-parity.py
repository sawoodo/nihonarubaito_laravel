#!/usr/bin/env python3
"""
matcher-parity.py -- the Python half of the JobMatcher parity test.

Reads a published.tsv export, reproduces exactly the pairs
scripts/dedupe-plan.py compares (group by normalised company, bucket by
address / station / listing, every pair inside a bucket, deduplicated), runs
judge() on each, and writes one sorted TSV line per pair.

scripts/matcher-parity.php writes the same TSV from App\\Services\\JobMatcher.
`diff` the two files: any difference is a divergence between the planner that
has already been applied to production and the matcher the admin screens will
use. Nothing here touches a database.

USAGE
  python3 -I scripts/matcher-parity.py --published P.tsv --out pairs-python.tsv
"""

import argparse
import importlib.util
import os
import sys
from collections import defaultdict

HERE = os.path.dirname(os.path.abspath(__file__))


def load(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


dp = load('dedupe_plan', os.path.join(HERE, 'dedupe-plan.py'))
dc = dp.dc


def sid_str(sid):
    """The tuple ('shigotoin','123') as PHP writes it: 'shigotoin:123'."""
    return '' if sid is None else f'{sid[0]}:{sid[1]}'


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--published', required=True)
    ap.add_argument('--out', required=True)
    args = ap.parse_args()

    rows = [r for r in dc.read_tsv(args.published) if r['job_status_id'] == '3']
    for r in rows:
        r['_co'] = dc.norm_company(r['company_name'])
        r['_addr'] = dc.norm_addr(dp.kanji_chome(r['address']))
        r['_link'] = dc.norm_link(r['apply_link'])
        r['_sid'] = dc.source_id(r['apply_link'])
        r['_st'] = dp.station_key(r['station'])
        r['_wage'] = dp.wage_key(r['wage'])
        r['_cat'] = r['job_category_id']

    by_co = defaultdict(list)
    for r in rows:
        if r['_co']:
            by_co[r['_co']].append(r)

    out = {}
    for co, js in by_co.items():
        if len(js) < 2:
            continue
        buckets = defaultdict(list)
        for j in js:
            for k in {('a', j['_addr']) if dp.specific(j['_addr']) else None,
                      ('s', j['_st']) if j['_st'] else None,
                      ('l', sid_str(j['_sid']) or j['_link']) if (j['_sid'] or j['_link']) else None} - {None}:
                buckets[k].append(j)
        for b in buckets.values():
            for i in range(len(b)):
                for k in range(i + 1, len(b)):
                    x, y = b[i], b[k]
                    key = tuple(sorted((x['job_no'], y['job_no'])))
                    if key in out:
                        continue
                    out[key] = dp.judge(x, y) or 'none'

    with open(args.out, 'w', encoding='utf-8') as fh:
        fh.write('job_a\tjob_b\tverdict\n')
        for (x, y) in sorted(out):
            fh.write(f'{x}\t{y}\t{out[(x, y)]}\n')

    counts = defaultdict(int)
    for v in out.values():
        counts[v] += 1
    print(f'published rows : {len(rows)}')
    print(f'companies       : {len(by_co)}')
    print(f'pairs compared  : {len(out)}')
    for v in sorted(counts):
        print(f'  {v:<8} {counts[v]}')
    print(f'written         : {args.out}')


if __name__ == '__main__':
    main()
