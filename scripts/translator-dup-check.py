#!/usr/bin/env python3
"""
translator-dup-check.py -- were the jobs published in the last N days new,
or did a translator re-translate a job we already had?

READ-ONLY. Reads two TSV exports (mysql --batch) and writes a report.
Touches no database.

  recent.tsv  jobs created in the last N days (status 3/4/5/6)
  pool.tsv    every job they could duplicate: all live/pending/quota-full
              jobs, plus retired jobs from the last ~400 days
  users.tsv   id, first_name, last_name  (optional, for names)

VERDICTS (one per recent job, strongest first)
  DUPLICATE_LIVE      an OLDER job for the same listing is still live
                      (published, pending or quota full). One of the two
                      should not exist.
  REPOST_RECENT       the same listing was retired <= 60 days ago. The old
                      job should have been revived with the new link
                      instead of translated again.
  REPEAT_SHOP_OLD     same shop, but the old job was retired > 60 days ago.
                      Informational: a new vacancy at a known shop is
                      normal.
  UNIQUE              no match.

  Each match also says whether the apply link is the SAME or CHANGED
  between the two jobs. CHANGED means the source moved the listing to a
  new URL -- the case where the existing job should have had its link
  updated instead of a new job being created.

MATCH BASIS (how two jobs were judged to be the same listing)
  link       identical apply link after removing tracking parameters
  source-id  same source site and same listing id
             (shigotoin offer, baitoru job, FamilyMart entry)
  shop       same normalised address AND same company name
             (address must contain a street number; town-only addresses
             never produce a shop match)
  shop~      same normalised address AND same job category, different
             company name (agencies) -- medium confidence, check by eye

USAGE
  python3 -I translator-dup-check.py --recent recent.tsv --pool pool.tsv \
      [--users users.tsv] --out-dir ~/nihon-reports --days 2
"""

import argparse
import csv
import datetime as dt
import os
import re
import sys
import unicodedata
from collections import Counter, defaultdict
from urllib.parse import urlsplit, parse_qsl, urlencode, urlunsplit

REVIVE_WINDOW_DAYS = 60
LIVE = {'2', '3', '6'}
RETIRED = {'4', '5'}
STATUS_NAME = {'2': 'pending', '3': 'published', '4': 'expired',
               '5': 'trashed', '6': 'quota full'}
TRACKING = re.compile(r'^(utm_|fbclid|gclid|src$|ref$|aff|vc_|sid$)', re.I)

csv.field_size_limit(sys.maxsize)


# ── reading mysql --batch output ─────────────────────────────────────────

def unescape(v):
    if v == 'NULL':
        return ''
    return (v.replace('\\n', '\n').replace('\\t', '\t')
             .replace('\\r', '\r').replace('\\\\', '\\'))


def read_tsv(path):
    """mysql --batch escapes \\n, \\t and \\\\ but NOT \\r. A raw CR inside a
    field (e.g. '<br>\\r\\n' in station) would be read by csv as a line break
    and split the row. Real row ends are always \\n, so every raw CR is data:
    drop it before parsing. Then refuse any row whose field count is wrong,
    at the line it occurs on, instead of building a silently short dict."""
    with open(path, 'rb') as f:
        text = f.read().replace(b'\r', b'').decode('utf-8')
    lines = text.split('\n')
    head = lines[0].split('\t')
    rows = []
    for n, line in enumerate(lines[1:], start=2):
        if line == '':
            continue
        cells = line.split('\t')
        if len(cells) != len(head):
            sys.exit(f'{path}: line {n} has {len(cells)} fields, expected {len(head)} — '
                     f'export is malformed, nothing analysed')
        rows.append(dict(zip(head, map(unescape, cells))))
    return rows


def when(v):
    v = (v or '').strip()
    if not v or v.startswith('0000'):
        return None
    for fmt in ('%Y-%m-%d %H:%M:%S', '%Y-%m-%d'):
        try:
            return dt.datetime.strptime(v[:19], fmt)
        except ValueError:
            pass
    return None


# ── normalisation ────────────────────────────────────────────────────────

def norm_link(url):
    url = (url or '').strip()
    if not url:
        return ''
    p = urlsplit(url)
    q = [(k, v) for k, v in parse_qsl(p.query, keep_blank_values=True)
         if not TRACKING.match(k)]
    host = p.netloc.lower().removeprefix('www.')
    path = p.path.rstrip('/')
    return urlunsplit(('', host, path, urlencode(sorted(q)), ''))


def source_id(url):
    """(site, listing id) or None. Only ids that identify ONE listing."""
    u = url or ''
    m = re.search(r'shigotoin\.com/.*?offer/(\d+)', u)
    if m:
        return ('shigotoin', m.group(1))
    m = re.search(r'baitoru\.com/.*?(?:job|entry/)(\d{5,})', u)
    if m:
        return ('baitoru', m.group(1))
    m = re.search(r'family\.co\.jp/.*?new_entry/(\d+)', u)
    if m:
        return ('familymart', m.group(1))
    return None


DASHES = '‐‑‒–—―−ー－─'


def norm_addr(a):
    a = unicodedata.normalize('NFKC', a or '')
    a = re.sub(r'\s+', '', a)
    a = re.sub(r'^〒?\d{3}-?\d{4}', '', a)            # postal code
    a = re.sub(r'^日本', '', a)
    for d in DASHES:
        a = a.replace(d, '-')
    a = re.sub(r'(\d)丁目', r'\1-', a)
    a = re.sub(r'(\d)番地?', r'\1-', a)
    a = re.sub(r'(\d)号', r'\1', a)
    a = re.sub(r'-+', '-', a).strip('-')
    # keep up to the last number of the street address; drop building names
    m = re.match(r'^(.*?\d+(?:-\d+)*)', a)
    return m.group(1) if m else a


CORP = re.compile(r'(株式会社|有限会社|合同会社|\(株\)|\(有\)|㈱|㈲)')


def norm_company(c):
    c = unicodedata.normalize('NFKC', c or '')
    c = CORP.sub('', c)
    return re.sub(r'[\s・/／()（）「」【】\-]', '', c).lower()


# ── matching ─────────────────────────────────────────────────────────────

def index_pool(pool):
    by_link, by_sid, by_addr = defaultdict(list), defaultdict(list), defaultdict(list)
    for j in pool:
        j['_link'] = norm_link(j['apply_link'])
        j['_sid'] = source_id(j['apply_link'])
        j['_addr'] = norm_addr(j['address'])
        j['_co'] = norm_company(j['company_name'])
        j['_date'] = when(j['date'])
        j['_retired'] = when(j.get('Expire_Date')) or when(j.get('updated_at'))
        if j['_link']:
            by_link[j['_link']].append(j)
        if j['_sid']:
            by_sid[j['_sid']].append(j)
        if len(j['_addr']) >= 6:
            by_addr[j['_addr']].append(j)
    return by_link, by_sid, by_addr


def candidates(r, idx):
    by_link, by_sid, by_addr = idx
    found = {}

    def add(j, basis):
        if j['job_no'] == r['job_no']:
            return
        # only jobs that existed BEFORE this one count as the original
        if j['_date'] and r['_date'] and j['_date'] > r['_date']:
            return
        if j['job_no'] not in found:
            found[j['job_no']] = (j, basis)

    for j in by_link.get(r['_link'], []) if r['_link'] else []:
        add(j, 'link')
    for j in by_sid.get(r['_sid'], []) if r['_sid'] else []:
        add(j, 'source-id')
    # A shop match needs a SPECIFIC address. Without a street number
    # ('岐阜県大垣市久瀬川町') "same address" only means "same town", and one
    # agency's different vacancies in that town would all look identical.
    if len(r['_addr']) >= 6 and re.search(r'\d', r['_addr']):
        for j in by_addr.get(r['_addr'], []):
            if r['_co'] and j['_co'] == r['_co']:
                add(j, 'shop')
            elif j['job_category_id'] == r['job_category_id']:
                add(j, 'shop~')
    return list(found.values())


def verdict(r, matches, now):
    strength = {'link': 0, 'source-id': 1, 'shop': 2, 'shop~': 3}
    best = None
    for j, basis in matches:
        st = j['job_status_id']
        if st in LIVE:
            v, rank = 'DUPLICATE_LIVE', 0
        elif st in RETIRED:
            days = (r['_date'] - j['_retired']).days if (r['_date'] and j['_retired']) else 9999
            if days <= REVIVE_WINDOW_DAYS:
                v, rank = 'REPOST_RECENT', 1
            else:
                v, rank = 'REPEAT_SHOP_OLD', 2
        else:
            continue
        key = (rank, strength[basis])
        if best is None or key < best[0]:
            best = (key, v, j, basis)
    if best is None:
        return 'UNIQUE', None, ''
    return best[1], best[2], best[3]


# ── main ─────────────────────────────────────────────────────────────────

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--recent', required=True)
    ap.add_argument('--pool', required=True)
    ap.add_argument('--users')
    ap.add_argument('--out-dir', default=os.path.expanduser('~/nihon-reports'))
    ap.add_argument('--days', type=int, default=2)
    a = ap.parse_args()

    recent = read_tsv(a.recent)
    pool = read_tsv(a.pool)
    names = {}
    if a.users and os.path.exists(a.users):
        for u in read_tsv(a.users):
            names[u['id']] = f"{u.get('first_name', '')} {u.get('last_name', '')}".strip()

    # recent jobs are part of the pool too (two new copies of one listing)
    by_no = {j['job_no']: j for j in pool}
    for r in recent:
        by_no.setdefault(r['job_no'], r)
    pool = list(by_no.values())
    idx = index_pool(pool)
    for r in recent:
        src = by_no[r['job_no']]
        for k in ('_link', '_sid', '_addr', '_co', '_date', '_retired'):
            r[k] = src[k]

    now = dt.datetime.now()
    rows = []
    for r in sorted(recent, key=lambda x: x['job_no']):
        v, twin, basis = verdict(r, candidates(r, idx), now)
        link_state = ''
        if twin:
            link_state = 'SAME' if (r['_link'] and r['_link'] == twin['_link']) else 'CHANGED'
        rows.append({
            'job_no': r['job_no'],
            'verdict': v,
            'basis': basis,
            'link': link_state,
            'status': STATUS_NAME.get(r['job_status_id'], r['job_status_id']),
            'created': r['date'][:16],
            'translator': names.get(r['user_id'], r['user_id']),
            'company': r['company_name'],
            'title': r['title'],
            'apply_link': r['apply_link'],
            'twin_job_no': twin['job_no'] if twin else '',
            'twin_status': STATUS_NAME.get(twin['job_status_id'], '') if twin else '',
            'twin_created': twin['date'][:10] if twin else '',
            'twin_retired': (twin['_retired'].strftime('%Y-%m-%d')
                             if twin and twin['job_status_id'] in RETIRED and twin['_retired'] else ''),
            'twin_apply_link': twin['apply_link'] if twin else '',
        })

    os.makedirs(a.out_dir, exist_ok=True)
    stamp = now.strftime('%Y%m%d-%H%M')
    csv_path = os.path.join(a.out_dir, f'translator-dup-check-{stamp}.csv')
    md_path = os.path.join(a.out_dir, f'translator-dup-check-{stamp}.md')
    with open(csv_path, 'w', encoding='utf-8-sig', newline='') as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()) if rows else ['job_no'])
        w.writeheader()
        w.writerows(rows)

    total = len(rows)
    vc = Counter(x['verdict'] for x in rows)
    flagged = [x for x in rows if x['verdict'] != 'UNIQUE']
    order = ['DUPLICATE_LIVE', 'REPOST_RECENT', 'REPEAT_SHOP_OLD', 'UNIQUE']

    def pct(n):
        return f'{(100 * n / total):.0f}%' if total else '0%'

    out = []
    out.append(f'# Translator duplicate check — last {a.days} day(s)\n')
    out.append(f'Generated {now:%Y-%m-%d %H:%M} (server export). Read-only.\n')
    out.append(f'**{total} jobs** created in the window. '
               f'**{vc["DUPLICATE_LIVE"] + vc["REPOST_RECENT"]} ({pct(vc["DUPLICATE_LIVE"] + vc["REPOST_RECENT"])})** '
               f'should not have been translated as new jobs.\n')
    out.append('| Verdict | Jobs | Share | Link changed |')
    out.append('|---|---|---|---|')
    for v in order:
        n = vc.get(v, 0)
        ch = sum(1 for x in rows if x['verdict'] == v and x['link'] == 'CHANGED')
        out.append(f'| {v} | {n} | {pct(n)} | {ch if v != "UNIQUE" else "—"} |')

    out.append('\n## By translator\n')
    out.append('| Translator | Jobs | Duplicate live | Repost ≤60d | Unique |')
    out.append('|---|---|---|---|---|')
    by_t = defaultdict(Counter)
    for x in rows:
        by_t[x['translator']][x['verdict']] += 1
    for t, c in sorted(by_t.items(), key=lambda kv: -sum(kv[1].values())):
        out.append(f'| {t or "(none)"} | {sum(c.values())} | {c["DUPLICATE_LIVE"]} | '
                   f'{c["REPOST_RECENT"]} | {c["UNIQUE"]} |')
    out.append('\nAll work may be under one admin account; if so this table has one row.\n')

    out.append('\n## Flagged jobs\n')
    if not flagged:
        out.append('None.\n')
    else:
        out.append('| New job | Verdict | Basis | Link | Existing job | Existing status | Company | Title |')
        out.append('|---|---|---|---|---|---|---|---|')
        for x in sorted(flagged, key=lambda x: (order.index(x['verdict']), x['job_no'])):
            ex = x['twin_job_no']
            st = x['twin_status'] + (f' {x["twin_retired"]}' if x['twin_retired'] else '')
            out.append(f'| {x["job_no"]} | {x["verdict"]} | {x["basis"]} | {x["link"]} | {ex} | {st} | '
                       f'{x["company"][:24]} | {x["title"][:50]} |')
        out.append('\nBasis `shop~` = same address and category, different company name: '
                   'check these by eye before acting.\n')
        out.append('Admin links: `https://nihonarubaito.com/admin/jobs/<job_no>/edit`.\n')

    with open(md_path, 'w', encoding='utf-8') as f:
        f.write('\n'.join(out) + '\n')

    print('\n'.join(out[:3 + 2 + len(order)]))
    print(f'\nreport: {md_path}\ncsv:    {csv_path}')


if __name__ == '__main__':
    main()
