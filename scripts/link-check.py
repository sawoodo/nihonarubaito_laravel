#!/usr/bin/env python3
"""
link-check.py -- report-only apply-link checker for shigotoin.com jobs.

WHERE TO RUN: on the Mac, with the Japan VPN ON. Never on the server --
shigotoin returns 403 to the SiteGround IP (it egresses via Singapore).

WHAT IT DOES: reads a TSV of published jobs, fetches each apply_link with
redirects followed, and classifies the destination. It writes a CSV and
NOTHING ELSE -- no database writes, no edits, no deploys.

GUARDS (run before any job is fetched; a failure aborts the run):
  1. ipinfo.io country must be JP, else we are not on the VPN.
  2. A known-OPEN offer must classify OPEN or MOVED.
  3. A known-CLOSED offer must classify CLOSED.
Guard 3 is the one that matters. On 2026-10-21 the phrase "お探しの" fired on
both open and closed pages (it is ordinary body copy, and also sits on the 404
page); without a closed control that would have been read as a wave of closures.

CLASSIFICATION, in this order:
  GONE    404 or 410
  CLOSED  200 AND body contains この求人の募集は終了しました / 掲載終了しています
  MOVED   200, final URL != requested URL, no closed phrase
  OPEN    200, final URL == requested URL, no closed phrase
  ERROR   timeout, 403, 5xx, maintenance page, undersized body, anything else

Three rules learned the hard way and encoded above:
  - A closed shigotoin page is HTTP 200, not an error. Never infer CLOSED
    from a status code.
  - A redirect is NOT a closed signal. On 2026-10-21, 8 of 11 *published*
    control jobs also 301'd to a fresh offer ID. shigotoin rotates IDs.
  - Never use "お探しの" as a marker. See above.

USAGE
  python3 scripts/link-check.py --guards-only
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

# Phrases that appear ONLY on a closed listing. Verified against a known-closed
# page on 2026-10-21: "この求人の募集は終了しました。表示中の求人は掲載終了しています。"
CLOSED_MARKERS = [
    "この求人の募集は終了しました",
    "掲載終了しています",
    "募集を終了しました",
    "募集終了",
    "受付終了",
]

# Markers meaning "the host is unhappy", never "the job is closed".
BLOCKED_MARKERS = ["緊急メンテナンス", "Request blocked", "Access Denied", "アクセスが制限"]

# Controls. The closed one is the load-bearing guard.
#
# The open control is a LIST, not one URL, and we only need one of them to be
# live. A single hardcoded "known-open" offer is hostage to its own lifecycle:
# on 2026-10-21 offer/121304887 (job 163561) was verified OPEN with zero closed
# markers at 14:53Z and was CLOSED by 16:25Z the same afternoon. That is a true
# positive, not a detector fault -- but it would block every future run.
CONTROL_OPEN_CANDIDATES = [
    "https://shigotoin.com/offer/121273633",   # job 163581, OPEN 2026-10-21
    "https://shigotoin.com/offer/121257099",   # job 163579, OPEN 2026-10-21
    "https://shigotoin.com/offer/121403405",   # final of 162711 + 163516
    "https://shigotoin.com/offer/121396489",   # final of 161727
]
CONTROL_CLOSED = "https://shigotoin.com/offer/121000000"  # 200 + 掲載終了 on 2026-10-21

MIN_BODY_BYTES = 3000   # a real job page was 8.9-14.4 KB; the 404 page is ~4.8 KB
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

    code, body = res["code"], res["body"]

    if code in ("404", "410"):
        return "GONE", "", ""

    blocked = [m for m in BLOCKED_MARKERS if m in body]
    if blocked:
        return "ERROR", "", "blocked_or_maintenance:" + blocked[0]

    if code != "200":
        return "ERROR", "", f"http_{code}"

    if res["size"] < MIN_BODY_BYTES:
        return "ERROR", "", f"body_too_small_{res['size']}"

    markers = [m for m in CLOSED_MARKERS if m in body]
    if markers:
        return "CLOSED", "|".join(markers), ""

    req = url.rstrip("/")
    fin = res["final"].rstrip("/")
    if fin != req:
        return "MOVED", "", ""
    return "OPEN", "", ""


def run_guards(verbose=True):
    """Return True only if every guard passes."""
    ok = True

    if verbose:
        print("=" * 64)
        print("GUARDS")
        print("=" * 64)

    jp, country, org = check_jp()
    print(f"[1] route      country={country}  org={org}")
    if not jp:
        print("    FAIL: not exiting in Japan -- turn the VPN on. Aborting.")
        return False
    print("    PASS")

    print("[2] open ctrl  (need >=1 of "
          f"{len(CONTROL_OPEN_CANDIDATES)} to be live)")
    live = 0
    for url in CONTROL_OPEN_CANDIDATES:
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

    print(f"[3] closed ctrl {CONTROL_CLOSED}")
    res = fetch(CONTROL_CLOSED)
    cls, mk, note = classify(CONTROL_CLOSED, res)
    print(f"    -> {cls}  http={res.get('code','-')} bytes={res.get('size','-')}")
    print(f"       markers: {mk or '(none)'}")
    if cls == "CLOSED":
        print("    PASS (the closed-marker detector fires)")
    else:
        print(f"    FAIL: expected CLOSED, got {cls} ({note}). "
              "Detector is blind -- every result would read as open. Aborting.")
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
    ap = argparse.ArgumentParser()
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

    settled, retry = (set(), set()) if args.fresh else load_done(args.output)

    if args.plan:
        rows = load_rows(args.input, args.limit, settled, retry)
        all_rows = load_rows(args.input, 0, settled, retry)
        print("RESUME PLAN (nothing fetched)")
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

    if not run_guards():
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
