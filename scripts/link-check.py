#!/usr/bin/env python3
"""
link-check.py -- report-only apply-link checker for job-source sites.

WHERE TO RUN: on the Mac, with the Japan VPN ON. Never on the server --
shigotoin returns 403 to the SiteGround IP (it egresses via Singapore), and
baitoru blocks it by geo/ASN as well.

WHAT IT DOES: reads a TSV of published jobs, fetches each apply_link with
redirects followed, and classifies the destination. It writes a CSV and
NOTHING ELSE -- no database writes, no edits, no deploys.

FIVE HOSTS, FIVE SIGNATURES. --host picks the profile (see HOSTS below). The
hosts do not fail the same way, so a single shared control set would be a
decoration rather than a guard:
  shigotoin  a closed listing is HTTP 200 + 「この求人の募集は終了しました」
  baitoru    a closed listing is HTTP 404 (~96.8 KB, no <title>, 見つかりません)
Running shigotoin's controls against a baitoru file would pass while proving
nothing about baitoru, so guard 4 refuses a file that does not match --host.

GUARDS (run before any job is fetched; a failure aborts the run):
  1. ipinfo.io country must be JP, else we are not on the VPN.
  2. A known-OPEN page on THIS host must classify OPEN or MOVED.
  3. A known-CLOSED page on THIS host must classify as the host's closed
     verdict (shigotoin CLOSED, baitoru GONE).
  4. Every input row's apply_link must belong to THIS host.
Guard 3 is the one that matters. On 2026-10-21 the phrase "お探しの" fired on
both open and closed pages (it is ordinary body copy, and also sits on the 404
page); without a closed control that would have been read as a wave of closures.
It also catches the failure that would quietly ruin a baitoru run: if baitoru
ever soft-404s (200 + "not found" body), every row would read OPEN, the error
rate would stay at zero, and nothing else here would notice.

CLASSIFICATION, in this order:
  GONE    404 or 410
  CLOSED  200 AND body contains one of the host's closed phrases
  MOVED   200, final URL != requested URL, no closed phrase
  OPEN    200, final URL == requested URL, no closed phrase
  ERROR   timeout, 403, 5xx, maintenance page, undersized body, anything else

Rules learned the hard way and encoded above:
  - A closed shigotoin page is HTTP 200, not an error. Never infer CLOSED
    from a status code.
  - A redirect is NOT a closed signal. On 2026-10-21, 8 of 11 *published*
    control jobs also 301'd to a fresh offer ID. shigotoin rotates IDs.
  - Never use "お探しの" as a marker. See above.
  - A baitoru MOVED is usually not a move at all. Its legacy apply form
    entry/form/jobNNN/ 301s to entry/NNN?obo_tab_kbn= with the SAME numeric
    id -- a URL-shape rewrite, not shigotoin-style offer rotation. The note
    column records id_preserved vs id_changed so the two are never confused
    downstream, where "MOVED" means "verify this is still our job".

USAGE
  python3 scripts/link-check.py --guards-only
  python3 scripts/link-check.py --host baitoru --guards-only
  python3 scripts/link-check.py --input ~/nihon-linkcheck/jobs.tsv \
                                --output ~/nihon-linkcheck/results.csv \
                                --limit 1300 --delay 6
"""

import argparse
import atexit
import csv
import json
import os
import re
import subprocess
import sys
import tempfile
import time
from datetime import datetime, timezone

UA = ("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36")

# ---------------------------------------------------------------------------
# TLS trust
#
# 2026-10-22: shigotoin rotated onto a Let's Encrypt chain anchored in
# "ISRG Root YR", which is newer than every CA bundle on this machine (system
# 2025-11, homebrew 2026-01, anaconda 2022, certifi 2022). Every fetch started
# failing with curl exit 60, unknown CA. Not a block -- google, ipinfo and
# baitoru all verified fine at the same moment.
#
# Fix: Let's Encrypt publishes Root YR CROSS-SIGNED by ISRG Root X1, which the
# system already trusts. We append only that one certificate to the system
# roots, so trust stays anchored in X1 rather than in a new root taken on faith.
#
#   leaf *.shigotoin.com -> ISRG YR2 -> Root YR (cross-signed by X1) -> X1 ✓
#
# NEVER add --insecure/-k here. This scan's verdicts drive expiry writes: with
# verification off, anything able to intercept the connection could serve a
# fake 「掲載終了」 and we would retire live jobs.
# ---------------------------------------------------------------------------
CA_EXTRA = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                        "ca", "letsencrypt-root-yr-x1.pem")

SYSTEM_CA_CANDIDATES = [
    "/etc/ssl/cert.pem",                              # macOS
    "/opt/homebrew/etc/ca-certificates/cert.pem",     # homebrew (arm)
    "/usr/local/etc/ca-certificates/cert.pem",        # homebrew (intel)
    "/etc/ssl/certs/ca-certificates.crt",             # debian/ubuntu
    "/etc/pki/tls/certs/ca-bundle.crt",               # rhel
]

_ca_bundle = None


def ca_bundle():
    """
    system roots + the cross-signed Root YR, written once to a temp file.
    Returns the path. Exits rather than continuing unverified.
    """
    global _ca_bundle
    if _ca_bundle:
        return _ca_bundle

    base = next((p for p in SYSTEM_CA_CANDIDATES if os.path.isfile(p)), None)
    if not base:
        sys.exit("FATAL: no system CA bundle found. Refusing to run without "
                 "certificate verification.")
    if not os.path.isfile(CA_EXTRA):
        sys.exit(f"FATAL: {CA_EXTRA} is missing. Refusing to run without it — "
                 "shigotoin's chain will not verify and every fetch would ERROR.")

    fh = tempfile.NamedTemporaryFile("wb", suffix=".pem", delete=False)
    for src in (base, CA_EXTRA):
        with open(src, "rb") as f:
            fh.write(f.read())
            fh.write(b"\n")
    fh.close()
    atexit.register(lambda p=fh.name: os.path.exists(p) and os.unlink(p))
    _ca_bundle = fh.name
    print(f"TLS: {base} + {os.path.basename(CA_EXTRA)}")
    return _ca_bundle

# Markers meaning "the host is unhappy", never "the job is closed".
BLOCKED_MARKERS = ["緊急メンテナンス", "Request blocked", "Access Denied", "アクセスが制限"]

# ---------------------------------------------------------------------------
# Host profiles
#
# Each host gets its own closed signature and its own controls, because the
# two sites do not agree on what "closed" looks like and a control from one
# proves nothing about the other.
#
# The open control is a LIST, not one URL, and we only need one of them to be
# live. A single hardcoded "known-open" page is hostage to its own lifecycle:
# on 2026-10-21 offer/121304887 (job 163561) was verified OPEN with zero closed
# markers at 14:53Z and was CLOSED by 16:25Z the same afternoon. That is a true
# positive, not a detector fault -- but it would block every future run.
#
# The closed control is the opposite: it must be a page that will STAY closed.
# shigotoin's is an offer id that was already 掲載終了 on 2026-10-21; baitoru's
# is a purged legacy form (job 152684, retired) which 404s permanently.
# ---------------------------------------------------------------------------
# ---------------------------------------------------------------------------
# Per-host classifiers
#
# shigotoin and baitoru share one rule: a closed marker, else a redirect means
# MOVED, else OPEN. The three direct-employer sites do not fit that shape --
# each needed its own function, verified against live pages on 2026-10-30
# (survey output: scratchpad/hostprobe/probe.csv).
#
# A profile carrying "classifier" uses it INSTEAD of the generic path. The
# maintenance/WAF check still runs first in every case: a blocked site must
# never be read as a site full of closed jobs.
# ---------------------------------------------------------------------------

FAMILYMART_CLOSED = "ただいま、この店舗は募集を行っておりません"


def classify_familymart(url, res):
    """
    staff.family.co.jp -- the apply form is the signal, the status code is not.

    An open store answers 200 with the 応募入力 form at ~38 KB (3 of 3 live
    jobs, 2026-10-30). A store that has stopped hiring answers with the
    sentence above, and the code CANNOT be trusted with it: the probe saw it on
    a 404 of 6,745 bytes, while the 2026-10-24 browser check saw a closed store
    answer 200 at ~7 KB. So the sentence is read before the code.

    Do NOT shorten the marker. 「ございません」 on its own appears in the
    boilerplate of every OPEN page -- it matched all three live jobs in the
    survey, and using it would expire the entire estate.

    The 20 KB floor measures the body WE RECEIVED, not res["size"]: curl's
    size_download is the transfer size, which on a host that gzips is the
    compressed length. staff.family.co.jp does not gzip today (38,330 bytes
    either way), so the two agree -- but if it ever switches compression on,
    size_download would drop under the floor and every open job would read
    ERROR. Nothing would be expired, yet the whole FamilyMart scan would stop
    until somebody noticed. The decompressed length cannot drift that way.
    """
    body, code = res["body"], res["code"]
    if FAMILYMART_CLOSED in body:
        return "CLOSED", FAMILYMART_CLOSED, f"http_{code}"
    if code in ("404", "410"):
        return "GONE", "", f"http_{code}_without_the_sentence"
    if code != "200":
        return "ERROR", "", f"http_{code}"
    title, _ = parse_title(body)
    received = len(body.encode("utf-8", "replace"))
    if "応募入力" in title and received > 20 * 1024:
        return "OPEN", "", ""
    return "ERROR", "", f"unrecognised_{received}b_received_title:{title[:40]}"


SEJ_CLOSED = "募集を行っておりません"


def classify_sej(url, res):
    """
    ptj.sej.co.jp -- the entry URL redirects, and where it lands is the answer.

    OPEN   two redirects to /entry/entryform  (title ...応募シート 記入)
    CLOSED a redirect back to the store's 募集詳細 page carrying 募集を行って
           おりません -- job 161860 on 2026-10-30, 24,570 bytes
    GONE   404 (5,064-byte "404 Not Found")

    The landing URL carries a per-request jsessionid
    (/entry/entryform;jsessionid=1249CE41...?fontSize=fontM), so this host
    never compares final_url with the requested url -- every row would read
    MOVED. Only the path and the closed sentence decide.
    """
    body, code = res["body"], res["code"]
    if code in ("404", "410"):
        return "GONE", "", f"http_{code}"
    if code != "200":
        return "ERROR", "", f"http_{code}"
    if SEJ_CLOSED in body:
        return "CLOSED", SEJ_CLOSED, "landed_on_the_store_detail_page"
    if "/entry/entryform" in res["final"].split("?", 1)[0]:
        return "OPEN", "", ""
    title, _ = parse_title(body)
    return "ERROR", "", f"no_entry_form_{res['size']}b_title:{title[:40]}"


SFT_CLOSED = ["募集終了", "募集を終了", "掲載終了"]
SFT_GONE = "お探しの求人が見つかりません"


def classify_sftworks(url, res):
    """
    sftworks.jp -- a ?work_id= either still opens an entry form or it does not.

    OPEN   200 with title 応募入力. Size is no help on this host twice over:
           uncompressed, open forms ran 92-119 KB and one still-open detail
           page 249 KB; and sftworks is the one host here that gzips, so
           res["size"] is the compressed length (10,937 for the same page the
           survey measured at 98,680). The title decides, never the size.
    CLOSED the form is gone and we land on /detail/ carrying 募集終了
           -- job 157917
    HELD   we land on /detail/ with no closed phrase -- job 159251, where the
           work_id stopped resolving to a form while the job is still
           advertised. NEVER expired; a human decides whether it is our job.
    GONE   404 + お探しの求人が見つかりません
    """
    body, code = res["body"], res["code"]
    title, _ = parse_title(body)
    if code in ("404", "410"):
        return "GONE", (SFT_GONE if SFT_GONE in body else ""), f"http_{code}"
    if code != "200":
        return "ERROR", "", f"http_{code}"
    if "応募入力" in title:
        return "OPEN", "", ""
    if "/detail/" in res["final"]:
        mk = [m for m in SFT_CLOSED if m in body]
        if mk:
            return "CLOSED", "|".join(mk), "entry_form_gone_detail_says_closed"
        return "HELD", "", "entry_form_gone_but_the_listing_is_still_open"
    return "ERROR", "", f"unrecognised_{res['size']}b_title:{title[:40]}"


LAWSON_CLOSED = "現在公開されていません"
LAWSON_STORE = re.compile(r'<h1 class="title">(.*?)</h1>', re.S)


def classify_lawson(url, res):
    """
    crew.lawson.co.jp -- needs a cookie jar, see "cookies" in the profile.

    Without one, every entry URL lands on /errors/408, open or closed. With
    one, an open job redirects to the session page /entry/entryform, with the
    form and the store name; a job taken down answers 200 on the same URL with
    「この案件は現在公開されていません」 in the title and 「この求人情報は現在
    公開されていません」 in the body. Both share the marker. A nonexistent id
    is a plain 404.

    An open job is OPEN, never MOVED: /entry/entryform belongs to this one
    session, so it must not reach apply_link. The store goes in the note, to
    compare with our company_name. /jobfind-pc/job/<id> 404s for live jobs too:
    never use it.
    """
    body, code = res["body"], res["code"]
    if LAWSON_CLOSED in body:
        return "CLOSED", LAWSON_CLOSED, f"http_{code}"
    if code in ("404", "410"):
        return "GONE", "", f"http_{code}"
    title, _ = parse_title(body)
    if code != "200" or "/errors/" in res["final"]:
        return "ERROR", "", f"http_{code}_final:{res['final'][-30:]}"
    store = LAWSON_STORE.search(body)
    if "応募シート" in title and 'name="entrySheet1Form"' in body and store:
        return "OPEN", "", "store:" + store.group(1).strip()
    return "ERROR", "", f"unrecognised_title:{title[:40]}"


HOSTS = {
    "lawson": {
        "url_match": "crew.lawson.co.jp",
        "closed_markers": [LAWSON_CLOSED],
        "cookies": True,
        # 3 live jobs verified 200 + 応募シート 記入 + store name on 2026-10-31
        "open_controls": [
            "https://crew.lawson.co.jp/jobfind-pc/entry/job/84972",    # job 159905
            "https://crew.lawson.co.jp/jobfind-pc/entry/job/8544",     # job 159916
            "https://crew.lawson.co.jp/jobfind-pc/entry/job/106992",   # job 159947
        ],
        # job 162694: Machida, retired 2026-10-08. A real listing, not a made-up id.
        "closed_control": "https://crew.lawson.co.jp/jobfind-pc/entry/job/138849",
        "closed_verdict": "CLOSED",
        "min_body_bytes": 3000,
        "classifier": classify_lawson,
    },
    "shigotoin": {
        "url_match": "shigotoin.com",
        # Verified against a known-closed page on 2026-10-21:
        # "この求人の募集は終了しました。表示中の求人は掲載終了しています。"
        "closed_markers": [
            "この求人の募集は終了しました",
            "掲載終了しています",
            "募集を終了しました",
            "募集終了",
            "受付終了",
        ],
        "open_controls": [
            "https://shigotoin.com/offer/121273633",   # job 163581, OPEN 2026-10-21
            "https://shigotoin.com/offer/121257099",   # job 163579, OPEN 2026-10-21
            "https://shigotoin.com/offer/121403405",   # final of 162711 + 163516
            "https://shigotoin.com/offer/121396489",   # final of 161727
        ],
        "closed_control": "https://shigotoin.com/offer/121000000",
        "closed_verdict": "CLOSED",     # 200 + 掲載終了 on 2026-10-21
        # a real job page was 8.9-14.4 KB; the 404 page is ~4.8 KB
        "min_body_bytes": 3000,
    },
    # --- direct employers, added 2026-10-30 -------------------------------
    "familymart": {
        "url_match": "staff.family.co.jp",
        "closed_markers": [FAMILYMART_CLOSED],
        # 3 live jobs verified 200 + 応募入力 at ~38 KB on 2026-10-30
        "open_controls": [
            "https://staff.family.co.jp/dsaiyo/cofm/rec_applicants/new_entry/77901",   # job 164489
            "https://staff.family.co.jp/dsaiyo/cofm/rec_applicants/new_entry/26642",   # job 164484
            "https://staff.family.co.jp/dsaiyo/cofm/rec_applicants/new_entry/56207",   # job 164455
        ],
        # a store id that does not exist: 404 + the sentence, permanently
        "closed_control": "https://staff.family.co.jp/dsaiyo/cofm/rec_applicants/new_entry/99999999",
        "closed_verdict": "CLOSED",
        "min_body_bytes": 3000,
        "classifier": classify_familymart,
    },
    "sej": {
        "url_match": "ptj.sej.co.jp",
        "closed_markers": [SEJ_CLOSED],
        # 3 live jobs verified 200 + 応募シート 記入 at ~28.9 KB on 2026-10-30
        "open_controls": [
            "https://ptj.sej.co.jp/arbeit/recruitment/jobfind-pc/entry/job/77515",     # job 164507
            "https://ptj.sej.co.jp/arbeit/recruitment/jobfind-pc/entry/job/1129460",   # job 164480
            "https://ptj.sej.co.jp/arbeit/recruitment/jobfind-pc/entry/job/1320825",   # job 164463
        ],
        # job 161860: lands on the 伊丹御願塚３丁目店 detail page, 募集を行って
        # おりません. A real closed store, not a synthetic id.
        "closed_control": "https://ptj.sej.co.jp/arbeit/recruitment/jobfind-pc/entry/job/sub/49282",
        "closed_verdict": "CLOSED",
        "min_body_bytes": 3000,
        "classifier": classify_sej,
    },
    "sftworks": {
        "url_match": "sftworks.jp",
        "closed_markers": SFT_CLOSED,
        # 3 live jobs verified 200 + 応募入力 on 2026-10-30
        "open_controls": [
            "https://sftworks.jp/entry/input?work_id=W015747595",   # job 164502
            "https://sftworks.jp/entry/input?work_id=W015747593",   # job 164488
            "https://sftworks.jp/entry/input?work_id=W015834060",   # job 164471
        ],
        # job 157917: ヤオコー 野木店, redirects to /detail/ with 募集終了
        "closed_control": "https://sftworks.jp/entry/input?work_id=W015679054",
        "closed_verdict": "CLOSED",
        "min_body_bytes": 3000,
        "classifier": classify_sftworks,
    },
    "baitoru": {
        "url_match": "baitoru.com",
        # baitoru signals closure with a 404, so these are a soft-404 backstop
        # only. 見つかりません sits on the 404 page, which has no <title> at all.
        # NOT "お探しの" -- same trap as shigotoin, it is ordinary body copy.
        "closed_markers": [
            "この求人の募集は終了しました",
            "掲載は終了しました",
            "募集を終了しました",
        ],
        # 4 legacy forms verified 200 + 「応募フォーム - バイトル」 on 2026-10-23
        "open_controls": [
            "https://www.baitoru.com/entry/form/job160917922/",  # job 156428
            "https://www.baitoru.com/entry/form/job158971620/",  # job 156396
            "https://www.baitoru.com/entry/form/job160456488/",  # job 156387
            "https://www.baitoru.com/entry/form/job53156757/",   # job 156386
        ],
        # job 152684, retired: 404, 96,828 bytes, no title, 見つかりません
        "closed_control": "https://www.baitoru.com/entry/form/job136496130/",
        "closed_verdict": "GONE",
        # the 404 page is ~96.8 KB, far LARGER than a real 68 KB form, so the
        # size floor cannot separate them here -- the status code does.
        "min_body_bytes": 3000,
    },
}

# Set by main() from --host; every classification reads it.
HOST = HOSTS["shigotoin"]

RECHECK_IP_EVERY = 200  # the VPN can drop during a multi-hour run
ERROR_ABORT_RATIO = 0.30
ERROR_ABORT_MIN = 50

FIELDS = [
    "checked_at", "job_no", "delete_at", "requested_url", "http_code",
    "num_redirects", "final_url", "bytes", "classification",
    "closed_markers", "employer_token", "source_title", "note",
]


def now_iso():
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def check_jp():
    """Return (ok, country, org). Abort the run unless country == JP."""
    try:
        r = subprocess.run(
            ["curl", "-s", "--max-time", "20", "--cacert", ca_bundle(),
             "https://ipinfo.io/json"],
            capture_output=True, text=True, check=False)
        d = json.loads(r.stdout)
        return d.get("country") == "JP", d.get("country", "?"), d.get("org", "?")
    except Exception as e:                                    # noqa: BLE001
        return False, f"lookup-failed ({e.__class__.__name__})", "?"


def fetch(url, timeout=40):
    """Fetch following redirects. Returns a dict; never raises."""
    try:
        r = subprocess.run([
            "curl", "-sL", "--max-time", str(timeout), "-A", UA,
            "--cacert", ca_bundle(),
            "-H", "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "-H", "Accept-Language: ja,en-US;q=0.9,en;q=0.8",
            "--compressed",
            # An empty -b turns cookies on for this one fetch: no jar is kept,
            # so each job gets its own session.
            *(["-b", ""] if HOST.get("cookies") else []),
            "-w", "\n__META__%{http_code}|%{num_redirects}|%{url_effective}|%{size_download}",
            url,
        ], capture_output=True, text=True, check=False)
    except Exception as e:                                    # noqa: BLE001
        return {"ok": False, "err": e.__class__.__name__}

    out = r.stdout
    i = out.rfind("\n__META__")
    if r.returncode != 0 or i < 0:
        return {"ok": False, "err": f"curl_rc_{r.returncode}"}

    body = out[:i]
    try:
        code, nred, final, size = out[i + 9:].strip().split("|")
    except ValueError:
        return {"ok": False, "err": "meta_parse"}

    return {"ok": True, "body": body, "code": code,
            "nred": int(nred or 0), "final": final, "size": int(size or 0)}


def parse_title(body):
    m = re.search(r"<title[^>]*>(.*?)</title>", body, re.S | re.I)
    if not m:
        return "", ""
    import html as _html
    title = re.sub(r"\s+", " ", _html.unescape(m.group(1))).strip()
    token = title.split(" ")[0] if title else ""
    return title[:255], token[:80]


def classify(url, res):
    """Return (classification, markers_found, note)."""
    if not res["ok"]:
        return "ERROR", "", res.get("err", "fetch_failed")

    own = HOST.get("classifier")
    if own is not None:
        # The maintenance/WAF check runs for every host: a blocked site must
        # never be read as a site full of closed jobs.
        blocked = [m for m in BLOCKED_MARKERS if m in res["body"]]
        if blocked:
            return "ERROR", "", "blocked_or_maintenance:" + blocked[0]
        return own(url, res)

    code, body = res["code"], res["body"]

    if code in ("404", "410"):
        return "GONE", "", ""

    blocked = [m for m in BLOCKED_MARKERS if m in body]
    if blocked:
        return "ERROR", "", "blocked_or_maintenance:" + blocked[0]

    if code != "200":
        return "ERROR", "", f"http_{code}"

    if res["size"] < HOST["min_body_bytes"]:
        return "ERROR", "", f"body_too_small_{res['size']}"

    markers = [m for m in HOST["closed_markers"] if m in body]
    if markers:
        return "CLOSED", "|".join(markers), ""

    req = url.rstrip("/")
    fin = res["final"].rstrip("/")
    if fin != req:
        return "MOVED", "", redirect_note(url, res["final"])
    return "OPEN", "", ""


def redirect_note(requested, final):
    """
    Say whether a redirect kept the job id, because MOVED means two different
    things depending on the answer.

    baitoru rewrites entry/form/job160917922/ -> entry/160917922?obo_tab_kbn=
    for every legacy link: same job, new URL shape. shigotoin instead rotates
    to a genuinely different offer id, which is the case that needs a human to
    confirm it is still our job. Comparing the longest digit run in each URL
    separates them without hardcoding either site's path layout.
    """
    def ids(u):
        return set(re.findall(r"\d{5,}", u))

    a, b = ids(requested), ids(final)
    if not a or not b:
        return ""
    if a & b:
        return "id_preserved"
    return "id_changed"


def check_input_host(path, host_name):
    """
    Guard 4: refuse an input file that is not the host we have controls for.

    Without this, pointing the shigotoin profile at a baitoru file passes
    guards 1-3 (they fetch shigotoin's own controls) and then classifies 1,360
    baitoru pages with the wrong closed signature. Returns (ok, total, matched).
    """
    needle = HOSTS[host_name]["url_match"]
    total = matched = 0
    try:
        with open(path, encoding="utf-8") as fh:
            for r in csv.DictReader(fh, delimiter="\t"):
                link = (r.get("apply_link") or "").strip()
                if not link:
                    continue
                total += 1
                if needle in link:
                    matched += 1
    except OSError as e:                                      # noqa: BLE001
        print(f"    FAIL: cannot read {path} ({e.__class__.__name__})")
        return False, 0, 0
    return total > 0 and matched == total, total, matched


def run_guards(host_name, input_path=None, verbose=True):
    """Return True only if every guard passes."""
    ok = True

    if verbose:
        print("=" * 64)
        print(f"GUARDS  (host profile: {host_name})")
        print("=" * 64)

    jp, country, org = check_jp()
    print(f"[1] route      country={country}  org={org}")
    if not jp:
        print("    FAIL: not exiting in Japan -- turn the VPN on. Aborting.")
        return False
    print("    PASS")

    print("[2] open ctrl  (need >=1 of "
          f"{len(HOST['open_controls'])} to be live)")
    live = 0
    for url in HOST["open_controls"]:
        res = fetch(url)
        cls, mk, note = classify(url, res)
        title, _ = parse_title(res.get("body", "")) if res["ok"] else ("", "")
        print(f"    {cls:<7} http={res.get('code','-')} "
              f"bytes={res.get('size','-')} redirects={res.get('nred','-')}  {url}")
        if title:
            print(f"            title: {title[:84]}")
        if note:
            print(f"            note: {note}")
        if cls in ("OPEN", "MOVED"):
            live += 1
            break          # one live page is all the guard needs
        time.sleep(5)
    if live:
        print("    PASS (a live job page is reachable and parses)")
    else:
        print("    FAIL: not one candidate came back live. Either every control "
              "has closed, or fetching/parsing is broken. Aborting.")
        ok = False

    time.sleep(5)

    expect = HOST["closed_verdict"]
    print(f"[3] closed ctrl {HOST['closed_control']}")
    print(f"    expecting {expect}")
    res = fetch(HOST["closed_control"])
    cls, mk, note = classify(HOST["closed_control"], res)
    print(f"    -> {cls}  http={res.get('code','-')} bytes={res.get('size','-')}")
    print(f"       markers: {mk or '(none)'}")
    if cls == expect:
        print("    PASS (the closed detector fires on this host)")
    else:
        print(f"    FAIL: expected {expect}, got {cls} ({note}). "
              "Detector is blind -- every result would read as open. Aborting.")
        ok = False

    if input_path:
        print(f"[4] input host  must all be {HOSTS[host_name]['url_match']}")
        hok, total, matched = check_input_host(input_path, host_name)
        print(f"    {matched}/{total} row(s) match")
        if hok:
            print("    PASS")
        else:
            print(f"    FAIL: {total - matched} row(s) are not {host_name}. "
                  "Wrong --host for this file, or a mixed file. Aborting.")
            ok = False

    print("=" * 64)
    print("GUARDS: " + ("ALL PASS" if ok else "FAILED"))
    print("=" * 64)
    return ok


def load_done(output_path):
    """
    Read an existing results file and decide what not to fetch again.

    Returns (settled, retry):
      settled -- job_no we have a usable verdict for; skip these
      retry   -- job_no whose last verdict was ERROR; fetch these again,
                 because an ERROR says the check failed, not that the job did.

    The file is append-only and a retried job appears more than once, so the
    LAST row for a job_no wins. A row truncated by a kill is ignored.
    """
    settled, retry = set(), set()
    if not os.path.exists(output_path):
        return settled, retry

    latest = {}
    with open(output_path, encoding="utf-8", newline="") as fh:
        for row in csv.DictReader(fh):
            job_no = (row.get("job_no") or "").strip()
            cls = (row.get("classification") or "").strip()
            if not job_no or not cls:
                continue          # header-only, blank, or half-written line
            latest[job_no] = cls

    for job_no, cls in latest.items():
        (retry if cls == "ERROR" else settled).add(job_no)
    return settled, retry


def load_rows(path, limit, settled=frozenset(), retry=frozenset()):
    rows = []
    with open(path, encoding="utf-8") as fh:
        rd = csv.DictReader(fh, delimiter="\t")
        for r in rd:
            if not r.get("apply_link"):
                continue
            job_no = (r.get("job_no") or "").strip()
            if job_no in settled:
                continue          # already have a verdict
            rows.append(r)
    # Retry the failed ones first: they are the gaps in an otherwise done run.
    rows.sort(key=lambda r: (
        0 if (r.get("job_no") or "").strip() in retry else 1,
        r.get("delete_at") or "",
        r.get("job_no") or "",
    ))
    return rows[:limit] if limit else rows


def main():
    global HOST

    ap = argparse.ArgumentParser()
    ap.add_argument("--host", choices=sorted(HOSTS), default="shigotoin",
                    help="which source site's signature and controls to use")
    ap.add_argument("--input", default=os.path.expanduser(
        "~/nihon-linkcheck/shigotoin-published-20261021.tsv"))
    ap.add_argument("--output", default=os.path.expanduser(
        "~/nihon-linkcheck/results-20261021.csv"))
    ap.add_argument("--limit", type=int, default=1300)
    ap.add_argument("--delay", type=float, default=6.0)
    ap.add_argument("--guards-only", action="store_true")
    ap.add_argument("--plan", action="store_true",
                    help="print the resume plan and exit; fetches nothing")
    ap.add_argument("--fresh", action="store_true",
                    help="ignore the existing results file and re-check everything")
    args = ap.parse_args()

    HOST = HOSTS[args.host]

    settled, retry = (set(), set()) if args.fresh else load_done(args.output)

    if args.plan:
        rows = load_rows(args.input, args.limit, settled, retry)
        all_rows = load_rows(args.input, 0, settled, retry)
        hok, htotal, hmatched = check_input_host(args.input, args.host)
        print("RESUME PLAN (nothing fetched)")
        print(f"  host profile      : {args.host}"
              f"  ({hmatched}/{htotal} rows match"
              f"{'' if hok else ' — MISMATCH, guard 4 would abort'})")
        print(f"  results file      : {args.output}")
        print(f"  already settled   : {len(settled)}  (skipped)")
        print(f"  ERROR to retry    : {len(retry)}  (re-fetched first)")
        print(f"  remaining to do   : {len(all_rows)}")
        print(f"  this run's limit  : {args.limit} -> {len(rows)} rows "
              f"(~{len(rows) * args.delay / 3600:.1f}h)")
        if rows:
            print(f"  first in this run : job {rows[0].get('job_no')} "
                  f"(delete_at {rows[0].get('delete_at')})")
            print(f"  last in this run  : job {rows[-1].get('job_no')} "
                  f"(delete_at {rows[-1].get('delete_at')})")
        else:
            print("  nothing left to check")
        return

    if not run_guards(args.host, args.input):
        sys.exit(2)
    if args.guards_only:
        print("\n--guards-only: stopping before the batch. No jobs fetched.")
        return

    rows = load_rows(args.input, args.limit, settled, retry)
    total = len(rows)
    if settled or retry:
        print(f"\nresuming: {len(settled)} already settled (skipped), "
              f"{len(retry)} ERROR row(s) queued for retry")
    print(f"\nbatch: {total} rows, {args.delay}s apart "
          f"(~{total * args.delay / 3600:.1f}h)")
    print(f"host  : {args.host}")
    print(f"input : {args.input}")
    print(f"output: {args.output}\n")

    if total == 0:
        print("nothing left to check — every job already has a verdict.")
        return

    new = not os.path.exists(args.output) or os.path.getsize(args.output) == 0
    counts, errors_recent = {}, []

    with open(args.output, "a", newline="", encoding="utf-8") as fh:
        w = csv.DictWriter(fh, fieldnames=FIELDS)
        if new:
            w.writeheader()
            fh.flush()
            os.fsync(fh.fileno())

        for n, row in enumerate(rows, 1):
            url = row["apply_link"].strip()
            res = fetch(url)
            cls, markers, note = classify(url, res)
            title, token = parse_title(res["body"]) if res["ok"] else ("", "")

            w.writerow({
                "checked_at": now_iso(),
                "job_no": row.get("job_no", ""),
                "delete_at": row.get("delete_at", ""),
                "requested_url": url,
                "http_code": res.get("code", ""),
                "num_redirects": res.get("nred", ""),
                "final_url": res.get("final", ""),
                "bytes": res.get("size", ""),
                "classification": cls,
                "closed_markers": markers,
                "employer_token": token,
                "source_title": title,
                "note": note,
            })
            fh.flush()
            os.fsync(fh.fileno())   # incremental: survives a kill

            counts[cls] = counts.get(cls, 0) + 1
            errors_recent.append(cls == "ERROR")
            errors_recent = errors_recent[-50:]

            if n % 25 == 0 or n == total:
                print(f"  {n}/{total}  " +
                      "  ".join(f"{k}={v}" for k, v in sorted(counts.items())),
                      flush=True)

            # A wave of ERRORs means we are blocked or the host is down. The
            # right response is to stop, not to record 1,300 bad verdicts.
            if (len(errors_recent) >= ERROR_ABORT_MIN
                    and sum(errors_recent) / len(errors_recent) > ERROR_ABORT_RATIO):
                print(f"\nABORT at {n}/{total}: error rate "
                      f"{sum(errors_recent)}/{len(errors_recent)} in the last "
                      f"{len(errors_recent)} exceeds {ERROR_ABORT_RATIO:.0%}. "
                      "Likely blocked or host down. Partial results kept.",
                      flush=True)
                break

            if n % RECHECK_IP_EVERY == 0:
                jp, country, _ = check_jp()
                if not jp:
                    print(f"\nABORT at {n}/{total}: VPN dropped "
                          f"(country={country}). Partial results kept.", flush=True)
                    break

            if n < total:
                time.sleep(args.delay)

    print("\n" + "=" * 64)
    print("DONE  " + "  ".join(f"{k}={v}" for k, v in sorted(counts.items())))
    print(f"results: {args.output}")
    print("report-only: no database writes were made.")
    print("=" * 64)


if __name__ == "__main__":
    main()
