#!/usr/bin/env python3
"""
dedupe-plan.py -- plan the merge of PUBLISHED duplicate jobs. Generates only;
applies nothing.

For every group of published jobs that are certainly the same listing:
  KEEP   the OLDEST job (its page has the history and the ranking)
  LINK   give it the NEWEST job's apply link (the most recently sourced one),
         old value recorded in job_apply_link_history
  DATE   keep the later review date (delete_at) of the group
  TRASH  the newer copies: status 5, Expire_Date = now, updated_at = now,
         one job_status_history row each

CERTAIN (planned)  both published, same normalised company, same category, and
                     - the SAME source listing (same offer id / same link) and
                       the same wage, OR
                     - same wage AND the same place (same specific address, or
                       same station when neither has a specific address) AND
                       the same ROLE: the title part before " at ", compared
                       as word sets, similarity >= 0.80 -- or >= 0.90 when the
                       two jobs point at DIFFERENT source listings.
                   Never two specific addresses that differ (two shops).
                   Why the role check on the address path too: for staffing
                   agencies the stored address is often the agency office,
                   so one address covers many different placements
                   (2026-10-27 plan: 182 of 389 trashes were such placements).
REVIEW  (listed)   same place and company, but role, wage or a one-sided
                   specific address differs. Never planned.

INPUT   published.tsv  (mysql --batch export, see /dedupe-published)
OUTPUT  <out>/plan.csv        one row per job in a planned group
        <out>/review.csv      pairs a human should look at
        <out>/backup.csv      every column the apply step changes, as it is now
        <out>/precheck.sql    lists rows that no longer match the plan (expect 0)
        <out>/apply.sql       guarded statements, one batch id
        <out>/rollback.sql    restores backup values for this batch only

Every UPDATE is guarded on the row still being published with the exact
apply_link seen at export time, so anything that moved since is skipped,
not overwritten. A keeper is relinked only after all of its duplicates were
actually trashed by this batch.

USAGE
  python3 -I scripts/dedupe-plan.py --published P.tsv --out-dir DIR [--batch dd-...]
"""

import argparse
import csv
import datetime as dt
import importlib.util
import os
import re
import sys
import unicodedata
from collections import defaultdict

HERE = os.path.dirname(os.path.abspath(__file__))
_spec = importlib.util.spec_from_file_location('dupcheck', os.path.join(HERE, 'translator-dup-check.py'))
dc = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(dc)          # reuse read_tsv, norm_addr, norm_company, norm_link

TITLE_MIN = 0.80
TITLE_SAME_LISTING_DIFFERS = 0.90
STATION = re.compile(r'([一-龯々ぁ-んァ-ヶー・]{1,12}駅)')


KANJI_NUM = {'一': 1, '二': 2, '三': 3, '四': 4, '五': 5, '六': 6, '七': 7, '八': 8, '九': 9}


def kanji_chome(addr):
    """北栄一丁目13-17 -> 北栄1丁目13-17 (1-99), before the shared normaliser."""
    def conv(m):
        t = m.group(1)
        if t == '十':
            n = 10
        elif t.startswith('十'):
            n = 10 + KANJI_NUM[t[1]]
        elif '十' in t:
            n = KANJI_NUM[t[0]] * 10 + (KANJI_NUM[t[2]] if len(t) == 3 else 0)
        else:
            n = KANJI_NUM[t]
        return f'{n}丁目'
    return re.sub(r'([一二三四五六七八九]?十?[一二三四五六七八九]?)丁目',
                  lambda m: conv(m) if m.group(1) else m.group(0), addr or '')


def station_key(s):
    s = unicodedata.normalize('NFKC', s or '')
    m = STATION.search(s)
    return m.group(1) if m else ''


def wage_key(w):
    """The starting wage. '1,150円〜1,500円' and '1,150円' are the same job's
    wage written two ways; joining every digit ('11501500') made them differ."""
    w = unicodedata.normalize('NFKC', w or '').replace(',', '')
    m = re.search(r'\d{3,}', w)
    return m.group(0) if m else ''


def role_part(t):
    """The role, not the place: titles are '<role> at <place>'."""
    t = unicodedata.normalize('NFKC', t or '')
    i = t.lower().rfind(' at ')
    return t[:i] if i > 0 else t


def title_tokens(t):
    t = role_part(t).lower().replace('&', ' and ')
    t = re.sub(r'[\d,]+円', ' ', t)
    t = re.sub(r'[^a-z0-9\s]', ' ', t)
    stop = {'part', 'time', 'job', 'jobs', 'at', 'in', 'the', 'a', 'an', 'and', 'for', 'of', 'station'}
    return {w for w in t.split() if w not in stop}


def title_sim(a, b):
    ta, tb = title_tokens(a), title_tokens(b)
    if not ta or not tb:
        return 0.0
    return len(ta & tb) / len(ta | tb)


def specific(addr):
    return bool(re.search(r'\d', addr)) and len(addr) >= 6


def same_listing(a, b):
    """True / False when both links identify a source listing, else None."""
    if a['_link'] and a['_link'] == b['_link']:
        return True
    if a['_sid'] and b['_sid']:
        return a['_sid'] == b['_sid']
    return None


def judge(a, b):
    """'certain', 'review' or None for two published jobs of one company."""
    if a['_cat'] != b['_cat']:
        return None
    listing = same_listing(a, b)
    if listing is True:
        # The source says one listing. A different wage on the same link
        # usually means a translator pasted the wrong link -- two real jobs.
        return 'certain' if (a['_wage'] and a['_wage'] == b['_wage']) else 'review'
    sa, sb = specific(a['_addr']), specific(b['_addr'])
    if sa and sb and a['_addr'] != b['_addr']:
        return None                                   # two shops of one agency
    # Without street numbers, the same station is only the same place if the
    # town-level addresses agree too (one may be a shorter form of the other):
    # 東大阪市荒本新町 and 東大阪市菱江 share 荒本駅 but are different towns.
    town_ok = a['_addr'].startswith(b['_addr']) or b['_addr'].startswith(a['_addr'])
    same_place = (sa and sb) or (not sa and not sb and a['_st'] and a['_st'] == b['_st'] and town_ok)
    if not same_place:
        return 'review' if (a['_st'] and a['_st'] == b['_st']) else None
    same_wage = bool(a['_wage']) and a['_wage'] == b['_wage']
    need = TITLE_SAME_LISTING_DIFFERS if listing is False else TITLE_MIN
    if same_wage and title_sim(a['title'], b['title']) >= need:
        return 'certain'
    return 'review'


def sql_str(v):
    if v is None or v == '':
        return 'NULL'
    return "'" + str(v).replace('\\', '\\\\').replace("'", "\\'") + "'"


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--published', required=True)
    ap.add_argument('--out-dir', required=True)
    ap.add_argument('--batch', default='dd-' + dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
    a = ap.parse_args()

    rows = [r for r in dc.read_tsv(a.published) if r['job_status_id'] == '3']
    for r in rows:
        r['_co'] = dc.norm_company(r['company_name'])
        r['_addr'] = dc.norm_addr(kanji_chome(r['address']))
        r['_link'] = dc.norm_link(r['apply_link'])
        r['_sid'] = dc.source_id(r['apply_link'])
        r['_st'] = station_key(r['station'])
        r['_wage'] = wage_key(r['wage'])
        r['_cat'] = r['job_category_id']
        r['_date'] = dc.when(r['date']) or dt.datetime.min

    by_co = defaultdict(list)
    for r in rows:
        if r['_co']:
            by_co[r['_co']].append(r)

    # union-find over CERTAIN pairs
    parent = {}

    def find(x):
        parent.setdefault(x, x)
        while parent[x] != x:
            parent[x] = parent[parent[x]]
            x = parent[x]
        return x

    review = []
    for co, js in by_co.items():
        if len(js) < 2:
            continue
        # bucket by station or address to keep big agencies cheap
        buckets = defaultdict(list)
        for j in js:
            for k in {('a', j['_addr']) if specific(j['_addr']) else None,
                      ('s', j['_st']) if j['_st'] else None,
                      ('l', j['_sid'] or j['_link']) if (j['_sid'] or j['_link']) else None} - {None}:
                buckets[k].append(j)
        seen = set()
        for b in buckets.values():
            for i in range(len(b)):
                for k in range(i + 1, len(b)):
                    x, y = b[i], b[k]
                    key = tuple(sorted((x['job_no'], y['job_no'])))
                    if key in seen:
                        continue
                    seen.add(key)
                    v = judge(x, y)
                    if v == 'certain':
                        parent[find(x['job_no'])] = find(y['job_no'])
                    elif v == 'review':
                        review.append((x, y))

    by_no = {r['job_no']: r for r in rows}
    comps = defaultdict(list)
    for no in list(parent):
        comps[find(no)].append(by_no[no])

    groups = []
    for members in comps.values():
        if len(members) < 2:
            continue
        members.sort(key=lambda r: (r['_date'], int(r['job_no']) if r['job_no'].isdigit() else 0))
        keep = members[0]
        # every trashed copy must be CERTAIN against the keeper itself
        dups = [m for m in members[1:] if judge(keep, m) == 'certain']
        if not dups:
            continue
        newest = max([keep] + dups, key=lambda r: r['_date'])
        groups.append({'keep': keep, 'dups': dups, 'new_link': newest['apply_link'],
                       'new_delete_at': max((r['delete_at'] or '') for r in [keep] + dups) or keep['delete_at']})

    os.makedirs(a.out_dir, exist_ok=True)
    B = a.batch
    out = lambda n: os.path.join(a.out_dir, n)

    with open(out('plan.csv'), 'w', encoding='utf-8-sig', newline='') as f:
        w = csv.writer(f)
        w.writerow(['group', 'role', 'job_no', 'created', 'company', 'address', 'station', 'wage',
                    'title', 'apply_link', 'new_apply_link', 'delete_at', 'new_delete_at'])
        for g_i, g in enumerate(groups, 1):
            k = g['keep']
            w.writerow([g_i, 'KEEP', k['job_no'], k['date'][:10], k['company_name'], k['address'], k['_st'],
                        k['wage'], k['title'], k['apply_link'],
                        g['new_link'] if g['new_link'] != k['apply_link'] else '', k['delete_at'], g['new_delete_at']])
            for d in g['dups']:
                w.writerow([g_i, 'TRASH', d['job_no'], d['date'][:10], d['company_name'], d['address'], d['_st'],
                            d['wage'], d['title'], d['apply_link'], '', d['delete_at'], ''])

    with open(out('review.csv'), 'w', encoding='utf-8-sig', newline='') as f:
        w = csv.writer(f)
        w.writerow(['job_a', 'job_b', 'company', 'station_a', 'station_b', 'address_a', 'address_b',
                    'wage_a', 'wage_b', 'title_a', 'title_b'])
        for x, y in review:
            w.writerow([x['job_no'], y['job_no'], x['company_name'], x['_st'], y['_st'], x['address'], y['address'],
                        x['wage'], y['wage'], x['title'], y['title']])

    touched = [g['keep'] for g in groups] + [d for g in groups for d in g['dups']]
    with open(out('backup.csv'), 'w', encoding='utf-8-sig', newline='') as f:
        w = csv.writer(f)
        w.writerow(['job_no', 'job_status_id', 'apply_link', 'delete_at', 'Expire_Date', 'updated_at'])
        for r in touched:
            w.writerow([r['job_no'], r['job_status_id'], r['apply_link'], r['delete_at'],
                        r.get('Expire_Date', ''), r['updated_at']])

    with open(out('precheck.sql'), 'w', encoding='utf-8') as f:
        f.write('SET NAMES utf8mb4;\n-- rows that no longer match the plan; expect an empty result\n')
        conds = [f"(job_no = {sql_str(r['job_no'])} AND NOT (job_status_id = 3 AND apply_link <=> {sql_str(r['apply_link'])}))"
                 for r in touched]
        f.write('SELECT job_no, job_status_id, apply_link FROM jobs WHERE\n  ' + '\n  OR '.join(conds or ['0']) + ';\n')

    with open(out('apply.sql'), 'w', encoding='utf-8') as f:
        f.write(f"-- batch {B}: {len(groups)} groups, {sum(len(g['dups']) for g in groups)} jobs trashed\n")
        f.write('SET NAMES utf8mb4;\nSTART TRANSACTION;\n')
        for g in groups:
            k = g['keep']
            for d in g['dups']:
                f.write(f"UPDATE jobs SET job_status_id = 5, Expire_Date = NOW(), updated_at = NOW()\n"
                        f"  WHERE job_no = {sql_str(d['job_no'])} AND job_status_id = 3 AND apply_link <=> {sql_str(d['apply_link'])};\n"
                        f"SET @r = ROW_COUNT();\n"
                        f"INSERT INTO job_status_history (job_no, old_status, new_status, old_delete_at, new_delete_at, source, actor, reason, batch, created_at)\n"
                        f"  SELECT {sql_str(d['job_no'])}, 3, 5, {sql_str(d['delete_at'])}, {sql_str(d['delete_at'])}, 'dedupe', 'claude-code', "
                        f"{sql_str('duplicate of job ' + k['job_no'])}, {sql_str(B)}, NOW() FROM DUAL WHERE @r = 1;\n")
            n = len(g['dups'])
            dup_list = ', '.join(sql_str(d['job_no']) for d in g['dups'])
            all_trashed = (f"(SELECT c FROM (SELECT COUNT(*) AS c FROM job_status_history WHERE batch = {sql_str(B)} "
                           f"AND new_status = 5 AND job_no IN ({dup_list})) AS x) = {n}")
            new_link = g['new_link']
            date_set = (f",\n  delete_at = GREATEST(COALESCE(delete_at, '1970-01-01'), {sql_str(g['new_delete_at'])})"
                        if g['new_delete_at'] else '')   # never GREATEST(x, NULL): that is NULL
            f.write(f"UPDATE jobs SET apply_link = {sql_str(new_link)}{date_set}\n"
                    f"  WHERE job_no = {sql_str(k['job_no'])} AND job_status_id = 3 AND apply_link <=> {sql_str(k['apply_link'])}\n"
                    f"  AND {all_trashed};\n"
                    f"SET @r = ROW_COUNT();\n")
            if new_link != k['apply_link']:
                f.write(f"INSERT INTO job_apply_link_history (job_no, old_apply_link, new_apply_link, source, actor, batch, created_at)\n"
                        f"  SELECT {sql_str(k['job_no'])}, {sql_str(k['apply_link'])}, {sql_str(new_link)}, 'dedupe', 'claude-code', {sql_str(B)}, NOW() "
                        f"FROM DUAL WHERE @r = 1;\n")
            f.write(f"INSERT INTO job_status_history (job_no, old_status, new_status, old_delete_at, new_delete_at, source, actor, reason, batch, created_at)\n"
                    f"  SELECT {sql_str(k['job_no'])}, 3, 3, {sql_str(k['delete_at'])}, {sql_str(g['new_delete_at'])}, 'dedupe', 'claude-code', "
                    f"{sql_str('kept; merged ' + ', '.join(d['job_no'] for d in g['dups']))}, {sql_str(B)}, NOW() FROM DUAL WHERE @r = 1;\n")
        f.write(f"SELECT 'trashed' AS what, COUNT(*) AS n FROM job_status_history WHERE batch = {sql_str(B)} AND new_status = 5\n"
                f"UNION ALL SELECT 'kept', COUNT(*) FROM job_status_history WHERE batch = {sql_str(B)} AND new_status = 3\n"
                f"UNION ALL SELECT 'relinked', COUNT(*) FROM job_apply_link_history WHERE batch = {sql_str(B)};\n"
                f"COMMIT;\n")

    with open(out('rollback.sql'), 'w', encoding='utf-8') as f:
        f.write(f'-- undo batch {B}\nSET NAMES utf8mb4;\nSTART TRANSACTION;\n')
        for g in groups:
            k = g['keep']
            f.write(f"UPDATE jobs SET apply_link = {sql_str(k['apply_link'])}, delete_at = {sql_str(k['delete_at'])}\n"
                    f"  WHERE job_no = {sql_str(k['job_no'])} AND job_no IN (SELECT job_no FROM job_status_history WHERE batch = {sql_str(B)});\n")
            for d in g['dups']:
                f.write(f"UPDATE jobs SET job_status_id = 3, Expire_Date = {sql_str(d.get('Expire_Date'))}, updated_at = {sql_str(d['updated_at'])}\n"
                        f"  WHERE job_no = {sql_str(d['job_no'])} AND job_status_id = 5 "
                        f"AND job_no IN (SELECT job_no FROM job_status_history WHERE batch = {sql_str(B)});\n")
        f.write(f"DELETE FROM job_apply_link_history WHERE batch = {sql_str(B)};\n"
                f"DELETE FROM job_status_history WHERE batch = {sql_str(B)};\nCOMMIT;\n")

    trashed = sum(len(g['dups']) for g in groups)
    relink = sum(1 for g in groups if g['new_link'] != g['keep']['apply_link'])
    print(f'batch            {B}')
    print(f'published jobs   {len(rows)}')
    print(f'groups planned   {len(groups)}')
    print(f'  jobs kept      {len(groups)}  (relinked to the newest link: {relink})')
    print(f'  jobs trashed   {trashed}')
    print(f'review pairs     {len(review)}  (not planned)')
    print(f'files in         {a.out_dir}')


if __name__ == '__main__':
    main()
