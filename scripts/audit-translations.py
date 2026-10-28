#!/usr/bin/env python3
"""
audit-translations.py -- do the jobs created in the last N days follow the
translation rules (translation-instructions-v2.2, 2026-10-28 11:05 UTC)?

READ-ONLY. Reads TSV exports (mysql --batch) and writes a report. Touches no
database.

  jobs.tsv   jobs created in the window. Columns (header row, any order):
             id, job_no, job_status_id, date, user_id, title, description,
             wage, wage_type_id, wage_detail, working_hours, working_days,
             requirement, benefits, station, apply_link, job_category_id, tags
             (tags = comma-separated tags.slug from job_tag, may be NULL)
  users.tsv  id, first_name, last_name (optional, for names)

SEVERITY
  ERROR  a rule is clearly broken (wrong wage in the title, "&amp;" in the
         title, "no Japanese needed", an unconverted 25:00 time, a night-shift
         tag on a day job, a "minutes walk" line that is really a bicycle or
         bus ride, ...).
  CHECK  a heuristic says "look at this by eye" (the title's role is not in
         the task list, the tasks name a second role the title leaves out,
         the old default working-days sentence, ...). Not every CHECK is a
         mistake.

  The script judges only what is stored on our side. Whether a field matches
  the SOURCE listing (nothing invented, every shift copied) needs the source
  text; the command's optional deep gate does that on a sample.

USAGE
  python3 -I audit-translations.py --jobs jobs.tsv [--users users.tsv] \
      --out-dir ~/nihon-reports --days 7
  python3 -I audit-translations.py --selftest
"""

import argparse
import csv
import datetime as dt
import os
import re
import sys
import unicodedata
from collections import Counter, defaultdict
from urllib.parse import urlsplit

# --------------------------------------------------------------------------
# reading mysql --batch output
# --------------------------------------------------------------------------

_ESC = {'n': '\n', 't': '\t', '0': '\0', '\\': '\\'}


def _unescape(field):
    if field == 'NULL':
        return ''
    return re.sub(r'\\(.)', lambda m: _ESC.get(m.group(1), m.group(1)), field)


def read_tsv(path):
    """mysql --batch escapes \\n, \\t and \\\\ but NOT \\r, so read bytes,
    drop CR, and refuse any row whose field count differs from the header."""
    with open(path, 'rb') as fh:
        text = fh.read().decode('utf-8').replace('\r', '')
    lines = [ln for ln in text.split('\n') if ln != '']
    if not lines:
        return []
    header = lines[0].split('\t')
    rows = []
    for n, ln in enumerate(lines[1:], start=2):
        parts = ln.split('\t')
        if len(parts) != len(header):
            sys.exit(f'{path}: line {n} has {len(parts)} fields, header has '
                     f'{len(header)} -- refusing to guess')
        rows.append({h: _unescape(v) for h, v in zip(header, parts)})
    return rows


# --------------------------------------------------------------------------
# helpers
# --------------------------------------------------------------------------

HEADINGS = ('What you will do', 'Good to know', 'Key Japanese words')
CJK = re.compile(r'[぀-ヿ㐀-鿿ｦ-ﾟ]')
TAG_RE = re.compile(r'<[^>]+>')
BR_RE = re.compile(r'<br\s*/?>', re.I)


def plain(html):
    return TAG_RE.sub(' ', BR_RE.sub('\n', html or ''))


def nfkc(s):
    return unicodedata.normalize('NFKC', s or '')


def role_part(title):
    t = nfkc(title)
    i = t.lower().find(' part time job')
    return (t[:i] if i >= 0 else t.split(' at ')[0]).strip()


PAY_HOOKS = [  # priority order, first match wins (v2 STEP 2)
    ('Hand Cash', ['手渡し', 'tewatashi', 'hand cash', 'cash in hand',
                   'paid in cash', 'cash by hand']),
    ('Daily Payment', ['日払い', 'hibarai', 'daily pay']),
    ('Advance Payment', ['前払い', '仮払い', 'advance pay']),
    ('Weekly Payment', ['週払い', 'shuubarai', 'weekly pay']),
]


def title_hook(title):
    t = nfkc(title).lower()
    for name, _ in PAY_HOOKS:
        if t.startswith(name.lower()):
            return name
    return None


def text_hooks(text):
    t = nfkc(text).lower()
    return [name for name, sigs in PAY_HOOKS if any(s.lower() in t for s in sigs)]


def first_number(s):
    m = re.search(r'(\d[\d,]*)', nfkc(s))
    return int(m.group(1).replace(',', '')) if m else None


def title_wage(title):
    nums = re.findall(r'(\d[\d,]*)\s*円', nfkc(title))
    return int(nums[-1].replace(',', '')) if nums else None


TIME_RANGE = re.compile(r'(\d{1,2}):(\d{2})\s*(?:-|~|～|〜|–|to)\s*(?:翌)?(\d{1,2}):(\d{2})')


def ranges(text):
    out = []
    for m in TIME_RANGE.finditer(nfkc(text)):
        h1, m1, h2, m2 = map(int, m.groups())
        out.append((h1 * 60 + m1, h2 * 60 + m2, m.start(), m.end()))
    return out


def bracket_spans(text):
    spans, stack = [], []
    for i, ch in enumerate(text):
        if ch == '(':
            stack.append(i)
        elif ch == ')' and stack:
            spans.append((stack.pop(), i))
    return spans


def shift_ranges(text):
    """The time ranges that are SHIFTS.

    A range inside brackets counts only when that bracket holds two or more of
    them: "(front desk also: 08:30-16:30 / 16:30-00:30 / 00:30-08:30
    overnight)" is a second shift list, while "(22:00-22:30 for staff aged 18
    and over only)" is a note about an age limit or a premium window and must
    not make an evening job look like a night shift."""
    t = nfkc(text)
    rs = ranges(t)
    spans = bracket_spans(t)
    out = []
    for s, e, a, b in rs:
        inner = [sp for sp in spans if sp[0] < a < sp[1]]
        if not inner:
            out.append((s, e, a, b))
            continue
        lo, hi = max(inner, key=lambda sp: sp[0])
        if sum(1 for _, _, x, _ in rs if lo < x < hi) >= 2:
            out.append((s, e, a, b))
    return out


def covers_night(start, end):
    """v2.2: night = the shift STARTS at 22:00 or later, or it includes any
    minute between 00:00 and 05:00. A shift that ENDS at 22:00, 23:00 or
    24:00 is evening, not night -- even when a 深夜手当 is paid."""
    if start == end:            # 00:00-00:00 = round the clock
        return True
    if end > 24 * 60 or start >= 24 * 60:   # unconverted 25:00 style
        return True
    if start >= 22 * 60:        # v2.2: 22:00-23:30 is a night shift
        return True
    if end < start and end != 0:            # crosses midnight (24:00 does not)
        return True
    return start < 5 * 60       # starts after midnight, e.g. 01:00-05:00


def walk_minutes(station):
    s = nfkc(station)
    mins = [int(x) for x in re.findall(r'(\d+)\s*minutes?\s*walk', s, re.I)]
    mins += [int(x) for x in re.findall(r'徒歩\s*(\d+)\s*分', s)]
    return mins


def norm_link(link):
    """One key per SOURCE OFFER.

    shigotoin and baitoru carry the offer id in the path and tracking junk in
    the query, so those two are keyed on the id alone. Every other host keeps
    its query string: sftworks.jp identifies the offer ONLY in ?work_id=, and
    dropping it collapsed 17 different offers into one (2026-10-28)."""
    link = (link or '').strip()
    if not link:
        return ''
    p = urlsplit(link)
    host = p.netloc.lower().removeprefix('www.')
    m = re.search(r'/offer/(\d+)', p.path)
    if m and 'shigotoin' in host:
        return f'shigotoin:{m.group(1)}'
    if 'baitoru' in host:
        m = re.search(r'/entry/(?:form/)?(?:job)?(\d+)', p.path)
        if m:
            return f'baitoru:{m.group(1)}'
    base = host + p.path.rstrip('/')
    return (base + ('?' + p.query if p.query else '')).lower()


CUTOFF_DEFAULT = '2026-10-28 11:05:00'   # v2.2 instructions, UTC


def parse_dt(value):
    """jobs.date as stored by MySQL (server and MySQL both run UTC)."""
    try:
        return dt.datetime.strptime((value or '')[:19], '%Y-%m-%d %H:%M:%S')
    except ValueError:
        return None


def in_brackets(text, pos):
    """True if text[pos] sits inside ( ... ). A time range inside a note --
    "(overnight, 22:00-05:00 for staff aged 18 and over only)" -- is an
    explanation of a premium window, not a shift."""
    depth = 0
    for ch in text[:pos]:
        if ch == '(':
            depth += 1
        elif ch == ')' and depth:
            depth -= 1
    return depth > 0


def section(desc_html, heading):
    """Text of one v2 section: from its <b>heading</b> to the next heading."""
    text = plain(desc_html)
    i = text.find(heading)
    if i < 0:
        return None
    rest = text[i + len(heading):]
    ends = [rest.find(h) for h in HEADINGS if h != heading and rest.find(h) >= 0]
    return rest[:min(ends)] if ends else rest


def list_lines(sec):
    out = []
    for ln in (sec or '').split('\n'):
        ln = ln.strip()
        if ln.startswith('-'):
            ln = ln.lstrip('- ').strip()
            if ln:
                out.append(ln)
    return out


# Role families: title words -> family, and task keywords per family.
TITLE_WORDS = [
    ('kitchen', r'\b(kitchen|cook|cooking|prep)\b'),
    # "service" alone is counter/customer service (Deli Service Staff), not
    # restaurant hall work -- it flagged correct titles (2026-10-28).
    ('hall', r'\b(hall|server|serving|waiter|waitress|floor)\b'),
    ('dish', r'\b(dishwasher|dishwashing|dish)\b'),
    ('cashier', r'\bcashier\b'),
    ('stocking', r'\b(shelf|stocking)\b'),
    ('picking', r'\bpicking\b'),
    ('packing', r'\bpacking\b'),
    ('sorting', r'\bsorting\b'),
    ('inspection', r'\binspection\b'),
    ('line', r'\b(line|assembly|processing)\b'),
    ('bed', r'\bbed\b'),
    ('cleaning', r'\b(cleaning|housekeeping|cleaner)\b'),
    ('delivery', r'\b(delivery|driver)\b'),
]
TASK_WORDS = {
    'kitchen': r'cook|kitchen|prepar|\bprep\b|plat(e|ing)|fry|grill|chop|ingredient|調理|仕込',
    'hall': r'serv(e|ing)|take orders|taking orders|\border(s)?\b|seat|guide customers|'
            r'\btables?\b|carry (food|dishes)|greet|drinks|hall|接客|ホール',
    'dish': r'wash|dish|洗い場',
    'cashier': r'cashier|register|checkout|レジ',
    'stocking': r'shelf|shelves|stock|品出し',
    'picking': r'\bpick',
    'packing': r'\bpack|\bbox',
    'sorting': r'\bsort',
    'inspection': r'inspect|check (the )?(products|items|quality)|検品',
    'line': r'\bline\b|assembl|manufactur|process|factory|製造',
    'bed': r'\bbed|linen|sheets?\b|ベッド',
    'cleaning': r'clean|vacuum|mop|bathroom|toilet|wipe|清掃',
    'delivery': r'deliver|driv|配達|配送',
}
PAIRS = [('kitchen', 'hall', 'Kitchen and Hall Staff'),
         ('picking', 'packing', 'Picking and Packing Staff'),
         ('bed', 'cleaning', 'Bed Making and Room Cleaning Staff')]
VAGUE_ERROR = {'staff', 'restaurant staff', 'light work', 'light work staff',
               'general staff', 'part time staff', 'store staff', 'shop staff'}
VAGUE_CHECK = {'warehouse staff', 'factory staff', 'hotel staff', 'cleaning staff',
               'restaurant hall staff and kitchen staff'}


def title_families(role):
    r = role.lower()
    return {fam for fam, pat in TITLE_WORDS if re.search(pat, r)}


def task_families(lines):
    fams = defaultdict(int)
    for ln in lines:
        low = ln.lower()
        for fam, pat in TASK_WORDS.items():
            if re.search(pat, low):
                fams[fam] += 1
    return fams


NO_JAPANESE = re.compile(
    r'no japanese( language)? (skills? )?(is )?(needed|required|necessary)|'
    r'japanese (is )?not (required|needed|necessary)|'
    r'without (any )?japanese|no japanese ability|'
    r'japanese (skills? )?(is |are )?(not|un)necessary', re.I)
MENTIONS_LISTING = re.compile(
    r'\b(the|this) (listing|posting|job ad|job advertisement|advertisement)\b|'
    r'according to the (job|listing|posting|ad)\b|the (listing|posting) (says|mentions|states|notes|flags)',
    re.I)
BANNED_CHARS = re.compile(
    '[‘’“”•★☆♥❤→←➡'
    '\U0001F300-\U0001FAFF☀-⛿✀-➿]')
OLD_DEFAULT_DAYS = re.compile(r'^\s*minimum 4 hours a day and 2 days a week\s*$', re.I)
WALK_EN = re.compile(r'\d+\s*minutes?\s*walk', re.I)
# A bus RIDE (バスで / バス15分), never バス停 or a bus company in a stop name:
# "...バス停から徒歩4分" is a walk FROM a bus stop and must not be flagged.
VEHICLE_JA = re.compile(r'自転車|バイク|車で|バスで|バス\s*\d+\s*分')


# --------------------------------------------------------------------------
# the audit
# --------------------------------------------------------------------------

def audit_job(j, cutoff=None):
    """Return (format, [(severity, rule, detail), ...]) for one job.

    `cutoff` is when the v2.2 instructions took effect; rules that v2.2
    introduced are ERROR after it and CHECK before it."""
    flags = []

    def add(sev, rule, detail=''):
        flags.append((sev, rule, detail))

    title = j.get('title', '')
    desc = j.get('description', '')
    role = role_part(title)
    hook = title_hook(title)
    role_wo_hook = role[len(hook):].strip() if hook else role
    tags = {t.strip() for t in (j.get('tags') or '').split(',') if t.strip()}
    created = parse_dt(j.get('date'))
    post_v22 = bool(cutoff and created and created >= cutoff)

    present = [h for h in HEADINGS if h in desc]
    fmt = 'v2' if len(present) == 3 else ('partial' if present else 'old')
    if fmt == 'partial':
        add('ERROR', 'desc_sections_incomplete',
            'has ' + ', '.join(present) + '; missing ' +
            ', '.join(h for h in HEADINGS if h not in present))

    # ---- title ----
    if '&amp;' in title:
        add('ERROR', 'title_amp', '"&amp;" stored in the title')
    elif '&' in title:
        add('CHECK', 'title_ampersand', 'v2 says "and", not "&"')
    if re.search(r'円\s*(～|〜|~|以上)|(～|〜|~)\s*\d[\d,]*\s*円', nfkc(title)):
        add('ERROR', 'title_wage_suffix', 'range, ～ or 以上 in the title wage')
    tw, bw = title_wage(title), first_number(j.get('wage', ''))
    if tw is None:
        add('ERROR', 'title_no_wage', 'no "N円" in the title')
    elif bw is not None and tw != bw:
        add('ERROR', 'title_wage_mismatch', f'title {tw:,}円 vs wage field "{j.get("wage")}"')
    rl = role_wo_hook.lower()
    if rl in VAGUE_ERROR:
        add('ERROR', 'title_vague_role', f'role is "{role_wo_hook}"')
    elif rl in VAGUE_CHECK or rl.endswith(' light work'):
        add('CHECK', 'title_vague_role', f'role is "{role_wo_hook}"')

    # ---- payment hook ----
    body = ' '.join(j.get(k, '') for k in
                    ('wage_detail', 'description', 'benefits', 'requirement',
                     'working_hours', 'wage'))
    found = text_hooks(body)
    if hook and hook not in found:
        add('CHECK', 'title_hook_unsupported',
            f'title says "{hook}" but the job text never mentions it')
    if found:
        best = found[0]
        if hook != best and (hook is None or
                             [n for n, _ in PAY_HOOKS].index(best) <
                             [n for n, _ in PAY_HOOKS].index(hook)):
            add('CHECK', 'title_hook_missed',
                f'text mentions {best}; title prepend is {hook or "none"}')

    # ---- role vs tasks (v2 only) ----
    if fmt == 'v2':
        tasks = list_lines(section(desc, 'What you will do'))
        if not tasks:
            add('ERROR', 'tasks_missing', '"What you will do" has no "- " lines')
        else:
            tf = task_families(tasks)
            ttl = title_families(role_wo_hook)
            for fam in sorted(ttl):
                if fam in TASK_WORDS and tf.get(fam, 0) == 0:
                    add('CHECK', 'title_role_not_in_tasks',
                        f'title role "{fam}" not found in the task lines')
            for a, b, label in PAIRS:
                if tf.get(a) and tf.get(b) and (a in ttl) != (b in ttl):
                    if (a in ttl and tf.get(b, 0) >= 2) or (b in ttl and tf.get(a, 0) >= 2):
                        add('CHECK', 'title_missing_role',
                            f'tasks cover {a} and {b}; title names only '
                            f'{a if a in ttl else b} (v2: "{label}")')
        kw = list_lines(section(desc, 'Key Japanese words'))
        if not kw:
            add('CHECK', 'keywords_missing', 'no lines under "Key Japanese words"')
        else:
            bad = [k for k in kw if not CJK.search(k) or '(' not in k]
            if bad:
                add('CHECK', 'keywords_format',
                    f'{len(bad)} of {len(kw)} lines lack "English (kanji romaji)": '
                    + ' | '.join(bad[:2]))
    if 'higabarai' in desc.lower():
        add('ERROR', 'romaji_higabarai', 'should be hibarai')
    if MENTIONS_LISTING.search(plain(desc)):
        add('CHECK', 'desc_mentions_listing',
            MENTIONS_LISTING.search(plain(desc)).group(0))

    # ---- characters ----
    for k in ('title', 'description', 'requirement', 'benefits', 'wage_detail',
              'working_hours', 'working_days', 'station'):
        m = BANNED_CHARS.search(j.get(k, ''))
        if m:
            add('ERROR', 'banned_char', f'{k}: U+{ord(m.group(0)):04X}')
            break

    # ---- Japanese level ----
    jt = plain(j.get('requirement', '')) + ' ' + plain(desc)
    m = NO_JAPANESE.search(jt)
    if m:
        add('ERROR', 'no_japanese_claim',
            f'"{m.group(0)}" -- allowed only if the source says 日本語不要 word for word')

    # ---- working hours / days ----
    wh = j.get('working_hours', '')
    if not wh.strip():
        add('CHECK', 'hours_empty', 'working_hours is empty')
    if re.search(r'\b(2[5-9]|3[0-5]):\d\d', nfkc(wh)):
        add('ERROR', 'hours_24h_notation',
            'a 25:00-35:59 time was not converted (24:00 is allowed)')
    whn = nfkc(wh)
    if 'overnight' not in whn.lower():
        for s, e, a, b in ranges(wh):
            if e < s and e != 0 and s < 24 * 60 and not in_brackets(whn, a):
                add('ERROR', 'hours_overnight_missing',
                    f'{whn[a:b]} crosses midnight without "(overnight)"')
                break
    if OLD_DEFAULT_DAYS.match(j.get('working_days', '')):
        add('CHECK', 'days_old_default',
            'exactly "Minimum 4 hours a day and 2 days a week" -- the old invented default?')

    # ---- tags ----
    rs = shift_ranges(wh)
    night = any(covers_night(s, e) for s, e, _, _ in rs)
    if 'night-shift-jobs' in tags and rs and not night:
        add('ERROR', 'tag_night_shift_wrong', f'tagged night-shift; hours "{wh}"')
    if 'night-shift-jobs' not in tags and night:
        add('CHECK', 'tag_night_shift_missing',
            f'hours "{wh}" start at 22:00 or later, or cover 00:00-05:00')
    if 'near-station-jobs' in tags:
        mins = walk_minutes(j.get('station', ''))
        near = f'; nearest walk {min(mins)} min' if mins else ''
        add('ERROR' if post_v22 else 'CHECK', 'tag_near_station_used',
            f'near-station is not a tag in v2.2{near}')
    for line in BR_RE.split(j.get('station', '')):
        if WALK_EN.search(line) and VEHICLE_JA.search(line):
            add('ERROR', 'station_walk_by_vehicle',
                f'"{" ".join(TAG_RE.sub(" ", line).split())[:90]}"')
            break
    if 'hand-cash-jobs' in tags and 'Hand Cash' not in found:
        add('CHECK', 'tag_hand_cash_unsupported', 'tagged hand-cash; text never mentions it')

    return fmt, flags


# --------------------------------------------------------------------------
# report
# --------------------------------------------------------------------------

GROUPS = ('v2.2', 'v2', 'partial', 'old')


def group_of(fmt, j, cutoff):
    """old / partial stay as they are; a v2 job splits on the cutoff."""
    if fmt != 'v2':
        return fmt
    created = parse_dt(j.get('date'))
    return 'v2.2' if (cutoff and created and created >= cutoff) else 'v2'


def run(jobs, users, days, out_dir, cutoff=None):
    names = {u['id']: f"{u.get('first_name', '')} {u.get('last_name', '')}".strip()
             for u in users}
    results = []
    for j in jobs:
        fmt, flags = audit_job(j, cutoff)
        results.append((j, group_of(fmt, j, cutoff), flags))

    # split offers: one source offer -> several jobs inside the window
    by_link = defaultdict(list)
    for j, fmt, flags in results:
        k = norm_link(j.get('apply_link', ''))
        if k:
            by_link[k].append(j)
    for k, js in by_link.items():
        if len(js) > 1:
            others = {x['job_no'] for x in js}
            for j, fmt, flags in results:
                if j in js:
                    flags.append(('CHECK', 'same_offer_several_jobs',
                                  'same source offer as ' +
                                  ', '.join(sorted(others - {j['job_no']}))))

    stamp = dt.datetime.now().strftime('%Y%m%d-%H%M')
    os.makedirs(out_dir, exist_ok=True)
    csv_path = os.path.join(out_dir, f'translation-audit-{stamp}.csv')
    md_path = os.path.join(out_dir, f'translation-audit-{stamp}.md')

    with open(csv_path, 'w', newline='', encoding='utf-8') as fh:
        w = csv.writer(fh)
        w.writerow(['job_no', 'id', 'date', 'user_id', 'translator', 'status',
                    'format', 'severity', 'rule', 'detail', 'title'])
        for j, fmt, flags in results:
            for sev, rule, detail in flags:
                w.writerow([j.get('job_no'), j.get('id'), j.get('date'), j.get('user_id'),
                            names.get(j.get('user_id'), ''), j.get('job_status_id'),
                            fmt, sev, rule, detail, j.get('title')])

    n = len(results)
    fmt_c = Counter(fmt for _, fmt, _ in results)
    rule_c = Counter((sev, rule) for _, _, fl in results for sev, rule, _ in fl)
    rule_ex = defaultdict(list)
    for j, _, fl in results:
        for sev, rule, detail in fl:
            if len(rule_ex[rule]) < 3:
                rule_ex[rule].append(f"{j.get('job_no')} ({detail})")
    v2 = [(j, fl) for j, fmt, fl in results if fmt in ('v2', 'v2.2')]
    v2_clean = sum(1 for _, fl in v2 if not fl)
    v2_err = sum(1 for _, fl in v2 if any(s == 'ERROR' for s, _, _ in fl))

    per = defaultdict(lambda: Counter())
    for j, fmt, fl in results:
        u = j.get('user_id')
        per[u]['jobs'] += 1
        per[u][fmt] += 1
        if any(s == 'ERROR' for s, _, _ in fl):
            per[u]['with_error'] += 1
        if not fl:
            per[u]['clean'] += 1

    by_day = defaultdict(Counter)
    for j, fmt, _ in results:
        by_day[(j.get('date') or '')[:10]][fmt] += 1

    L = []
    L.append(f'# Translation audit -- last {days} days ({n} jobs)\n')
    L.append(f'Generated {dt.datetime.now():%Y-%m-%d %H:%M}. Read-only. '
             'ERROR = rule clearly broken; CHECK = look by eye.')
    L.append(f'v2.2 instructions took effect {cutoff:%Y-%m-%d %H:%M} UTC.\n'
             if cutoff else '')
    L.append('## Format\n\n| format | jobs |\n|---|---|')
    for f in GROUPS:
        L.append(f'| {f} | {fmt_c.get(f, 0)} |')
    L.append(f'\nv2-format jobs (v2 + v2.2) with no flag at all: **{v2_clean} of '
             f'{len(v2)}**; with at least one ERROR: **{v2_err}**.\n')
    L.append('## By day\n\n| day | v2.2 | v2 | partial | old |\n|---|---|---|---|---|')
    for d in sorted(by_day, reverse=True):
        c = by_day[d]
        L.append(f'| {d} | {c.get("v2.2", 0)} | {c.get("v2", 0)} | '
                 f'{c.get("partial", 0)} | {c.get("old", 0)} |')
    L.append('\n## By translator\n\n| user | name | jobs | v2.2 | v2 | old | clean | with ERROR |\n'
             '|---|---|---|---|---|---|---|---|')
    for u, c in sorted(per.items(), key=lambda kv: -kv[1]['jobs']):
        L.append(f"| {u} | {names.get(u, '')} | {c['jobs']} | {c.get('v2.2', 0)} | "
                 f"{c.get('v2', 0)} | {c.get('old', 0)} | {c.get('clean', 0)} | "
                 f"{c.get('with_error', 0)} |")
    L.append('\n## Rules\n\n| severity | rule | jobs | examples |\n|---|---|---|---|')
    for (sev, rule), cnt in sorted(rule_c.items(), key=lambda kv: (kv[0][0] != 'ERROR', -kv[1])):
        L.append(f'| {sev} | {rule} | {cnt} | {"; ".join(rule_ex[rule])} |')
    L.append(f'\nFull list: `{csv_path}`\n')
    with open(md_path, 'w', encoding='utf-8') as fh:
        fh.write('\n'.join(L) + '\n')

    print('\n'.join(L))
    print(f'\nWROTE {md_path}\nWROTE {csv_path}')


# --------------------------------------------------------------------------
# self-test: every rule must fire on its bad case, and a clean v2 job on none
# --------------------------------------------------------------------------

CLEAN_V2 = {
    'id': '1', 'job_no': '1', 'job_status_id': '3', 'date': '2026-10-27 10:00:00',
    'user_id': '9',
    'title': 'Hand Cash Ramen Kitchen and Hall Staff Part Time Job at Shinjuku '
             'Station 新宿駅 in Tokyo 1,300円',
    'description': '<b>What you will do</b><br/>- Kitchen: cook ramen and prepare toppings'
                   '<br/>- Hall: take orders, serve food and clear tables<br/><br/>'
                   '<b>Good to know</b><br/>A small counter shop.<br/><br/>'
                   '<b>Key Japanese words</b><br/>- staff meals (まかない makanai)<br/>'
                   '- cash in hand (手渡し tewatashi)',
    'wage': '1,300円', 'wage_type_id': '1',
    'wage_detail': '22:00-05:00 1,625円 / Paid in cash by hand (手渡し tewatashi)',
    'working_hours': '11:00-15:00 / 17:00-22:00 / 22:00-05:00 (overnight, 18 and over only)',
    'working_days': 'Minimum 3 hours a day (Days negotiable)',
    'requirement': 'No experience required<br />Daily conversation level Japanese',
    'benefits': 'Staff meals', 'station': 'Shinjuku Station 新宿駅 (JR Yamanote Line)',
    'apply_link': 'https://shigotoin.com/offer/111', 'job_category_id': '2',
    'tags': 'hand-cash-jobs,night-shift-jobs',
}

BAD_CASES = {
    'title_amp': {'title': CLEAN_V2['title'].replace(' and ', ' &amp; ')},
    'title_ampersand': {'title': CLEAN_V2['title'].replace(' and ', ' & ')},
    'title_wage_suffix': {'title': CLEAN_V2['title'].replace('1,300円', '1,300円～')},
    'title_wage_mismatch': {'wage': '1,250円'},
    'title_no_wage': {'title': CLEAN_V2['title'].replace(' 1,300円', '')},
    'title_vague_role': {'title': 'Hand Cash Staff Part Time Job at Shinjuku Station 新宿駅 in Tokyo 1,300円'},
    'title_hook_unsupported': {'wage_detail': '22:00-05:00 1,625円',
                               'description': CLEAN_V2['description'].replace(
                                   '<br/>- cash in hand (手渡し tewatashi)', ''),
                               'tags': 'night-shift-jobs'},
    'title_hook_missed': {'title': CLEAN_V2['title'].replace('Hand Cash ', 'Daily Payment ')},
    'title_role_not_in_tasks': {'title': CLEAN_V2['title'].replace('Ramen Kitchen and Hall Staff',
                                                                   'Ramen Kitchen and Hall Cashier Staff')},
    'title_missing_role': {'title': CLEAN_V2['title'].replace('Kitchen and Hall Staff', 'Kitchen Staff'),
                           'description': CLEAN_V2['description'].replace(
                               '- Hall: take orders, serve food and clear tables',
                               '- Take orders from customers<br/>- Serve food and clear tables')},
    'tasks_missing': {'description': CLEAN_V2['description'].replace('- Kitchen', 'Kitchen')
                      .replace('- Hall', 'Hall')},
    'keywords_format': {'description': CLEAN_V2['description'].replace(
        '- staff meals (まかない makanai)', '- staff meals makanai')},
    'keywords_missing': {'description': CLEAN_V2['description'].split('<b>Key Japanese words</b>')[0]
                         + '<b>Key Japanese words</b><br/>'},
    'desc_sections_incomplete': {'description': CLEAN_V2['description'].replace('Good to know', 'Notes')},
    'romaji_higabarai': {'description': CLEAN_V2['description'] + '<br/>- daily payment (日払い higabarai)'},
    'desc_mentions_listing': {'description': CLEAN_V2['description'].replace(
        'A small counter shop.', 'The listing says it is a small counter shop.')},
    'banned_char': {'benefits': 'Staff meals ★'},
    'no_japanese_claim': {'requirement': 'No Japanese needed'},
    'hours_empty': {'working_hours': ''},
    'hours_24h_notation': {'working_hours': '25:00-29:00'},
    'hours_overnight_missing': {'working_hours': '22:00-05:00'},
    'days_old_default': {'working_days': 'Minimum 4 hours a day and 2 days a week'},
    'tag_night_shift_wrong': {'working_hours': '09:00-15:00 / 17:00-22:00',
                              'wage_detail': 'Paid in cash by hand (手渡し tewatashi)'},
    'tag_night_shift_missing': {'tags': 'hand-cash-jobs'},
    'tag_near_station_used': {'tags': CLEAN_V2['tags'] + ',near-station-jobs'},
    'station_walk_by_vehicle': {
        'station': '5 minutes walk from Shinjuku Station 新宿駅から徒歩5分 自転車5分'},
    'tag_hand_cash_unsupported': {'title': CLEAN_V2['title'].replace('Hand Cash ', ''),
                                  'wage_detail': '22:00-05:00 1,625円',
                                  'description': CLEAN_V2['description'].replace(
                                      '<br/>- cash in hand (手渡し tewatashi)', '')},
}


# v2.2 cases that need their own job, and cases that must fire NOTHING.
SELFTEST_CUTOFF = parse_dt(CUTOFF_DEFAULT)

EXTRA_CASES = [
    # (name, patch, rule that must fire)
    ('tag_night_shift_missing 22:00-23:30',
     {'working_hours': '22:00-23:30', 'tags': 'hand-cash-jobs'},
     'tag_night_shift_missing'),
    ('tag_night_shift_wrong 17:00-23:00',
     {'working_hours': '17:00-23:00'},
     'tag_night_shift_wrong'),
    ('station_walk_by_vehicle on a real bus ride',
     {'station': '15 minutes walk from Chofu Station 調布駅からバス15分'},
     'station_walk_by_vehicle'),
    ('tag_near_station_used is ERROR after the cutoff',
     {'tags': CLEAN_V2['tags'] + ',near-station-jobs',
      'date': '2026-10-28 12:00:00'},
     'tag_near_station_used'),
]

MUST_NOT_FIRE = [
    ('a walk from a bus stop',
     {'station': '4 minutes walk from Dotonbori Bus Stop 道頓堀バス停から徒歩4分'},
     ['station_walk_by_vehicle']),
    ('a bracketed shift list is still a shift list',
     {'working_hours': '10:00-22:00 (shift system - 4 to 8 hour blocks; front desk '
                       'also: 08:30-16:30 / 16:30-00:30 / 00:30-08:30 overnight)'},
     ['tag_night_shift_wrong']),
    ('a night window quoted inside a note',
     {'working_hours': '11:30-20:30 (early shift) / 13:30-22:30 (late shift, '
                       '22:00-22:30 for staff aged 18 and over only)',
      'tags': 'hand-cash-jobs'},
     ['tag_night_shift_missing']),
    # (name, patch, rules that must NOT fire)
    ("164270's real working_hours",
     {'working_hours': '07:00-11:00 / 14:00-18:00 / 17:00-22:00 / 22:00-07:00 '
                       '(overnight, 22:00-05:00 for staff aged 18 and over only) '
                       '(shift system)'},
     ['hours_overnight_missing']),
    ('Supermarket Deli Service Staff',
     {'title': 'Supermarket Deli Service Staff Part Time Job at Himeji Station '
               '姫路駅 in Hyogo 1,300円',
      'description': '<b>What you will do</b><br/>- Measure and weigh portions of '
                     'prepared foods<br/>- Wrap and package the items<br/>'
                     '- Process payment at the cashier<br/><br/>'
                     '<b>Good to know</b><br/>A deli counter.<br/><br/>'
                     '<b>Key Japanese words</b><br/>- prepared foods (お惣菜 o-souzai)'},
     ['title_role_not_in_tasks', 'title_missing_role']),
    ('17:00-24:00 ends at midnight',
     {'working_hours': '17:00-24:00', 'tags': 'hand-cash-jobs'},
     ['hours_24h_notation', 'hours_overnight_missing',
      'tag_night_shift_missing', 'tag_night_shift_wrong']),
]


def selftest():
    ok = True
    fmt, flags = audit_job(dict(CLEAN_V2), SELFTEST_CUTOFF)
    if fmt != 'v2' or flags:
        print(f'FAIL clean v2 job: format={fmt} flags={flags}')
        ok = False
    else:
        print('ok   clean v2 job: no flags')
    for rule, patch in BAD_CASES.items():
        j = dict(CLEAN_V2)
        j.update(patch)
        _, flags = audit_job(j, SELFTEST_CUTOFF)
        fired = {r for _, r, _ in flags}
        if rule in fired:
            print(f'ok   {rule}')
        else:
            print(f'FAIL {rule}: fired {sorted(fired)}')
            ok = False
    for name, patch, rule in EXTRA_CASES:
        j = dict(CLEAN_V2)
        j.update(patch)
        _, flags = audit_job(j, SELFTEST_CUTOFF)
        fired = {r for _, r, _ in flags}
        sev = {r: sv for sv, r, _ in flags}
        bad = rule not in fired
        if 'ERROR after the cutoff' in name and not bad:
            bad = sev.get(rule) != 'ERROR'
        if bad:
            print(f'FAIL {name}: fired {sorted(fired)}')
            ok = False
        else:
            print(f'ok   {name}')
    for name, patch, must_not in MUST_NOT_FIRE:
        j = dict(CLEAN_V2)
        j.update(patch)
        _, flags = audit_job(j, SELFTEST_CUTOFF)
        fired = {r for _, r, _ in flags}
        hit = sorted(set(must_not) & fired)
        if hit:
            print(f'FAIL {name}: should fire none of {must_not}, fired {hit}')
            ok = False
        else:
            print(f'ok   {name}: no {"/".join(must_not)}')
    # window-level rules
    import tempfile
    import io
    import contextlib
    with tempfile.TemporaryDirectory() as d:
        a, b = dict(CLEAN_V2), dict(CLEAN_V2)
        b['job_no'], b['id'] = '2', '2'
        buf = io.StringIO()
        with contextlib.redirect_stdout(buf):
            run([a, b], [], 1, d, SELFTEST_CUTOFF)
        if 'same_offer_several_jobs' in buf.getvalue():
            print('ok   same_offer_several_jobs')
        else:
            print('FAIL same_offer_several_jobs')
            ok = False
    with tempfile.TemporaryDirectory() as d:
        a, b = dict(CLEAN_V2), dict(CLEAN_V2)
        b['job_no'], b['id'] = '2', '2'
        a['apply_link'] = 'https://sftworks.jp/entry/input?work_id=W015869648'
        b['apply_link'] = 'https://sftworks.jp/entry/input?work_id=W015882334'
        buf = io.StringIO()
        with contextlib.redirect_stdout(buf):
            run([a, b], [], 1, d, SELFTEST_CUTOFF)
        if 'same_offer_several_jobs' in buf.getvalue():
            print('FAIL two sftworks work_ids grouped as one offer')
            ok = False
        else:
            print('ok   two sftworks work_ids are two offers')
    # mysql --batch escapes and CR
    with tempfile.TemporaryDirectory() as d:
        p = os.path.join(d, 't.tsv')
        with open(p, 'wb') as fh:
            fh.write(b'id\ttitle\r\n1\tline\\none\\ttab\r\n2\tNULL\n')
        rows = read_tsv(p)
        if rows == [{'id': '1', 'title': 'line\none\ttab'}, {'id': '2', 'title': ''}]:
            print('ok   read_tsv escapes, CR, NULL')
        else:
            print(f'FAIL read_tsv: {rows}')
            ok = False
    print('SELFTEST', 'PASS' if ok else 'FAIL')
    return ok


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--jobs')
    ap.add_argument('--users')
    ap.add_argument('--out-dir', default=os.path.expanduser('~/nihon-reports'))
    ap.add_argument('--days', type=int, default=7)
    ap.add_argument('--cutoff', default=CUTOFF_DEFAULT,
                    help='when the v2.2 instructions took effect, UTC '
                         f'(default {CUTOFF_DEFAULT})')
    ap.add_argument('--selftest', action='store_true')
    a = ap.parse_args()
    if a.selftest:
        sys.exit(0 if selftest() else 1)
    if not a.jobs:
        ap.error('--jobs is required (or --selftest)')
    jobs = read_tsv(a.jobs)
    if not jobs:
        sys.exit('jobs.tsv has no rows -- the window or the date column is wrong; '
                 'that is not a clean result')
    users = read_tsv(a.users) if a.users else []
    cutoff = parse_dt(a.cutoff)
    if cutoff is None:
        sys.exit(f'--cutoff {a.cutoff!r} is not "YYYY-MM-DD HH:MM:SS"')
    run(jobs, users, a.days, os.path.expanduser(a.out_dir), cutoff)


if __name__ == '__main__':
    main()
