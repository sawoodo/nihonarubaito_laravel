#!/usr/bin/env bash
#
# verify-noindex-guards.sh — truth table for noindex logic
#
# RUN AFTER: deploy + SiteGround cache purge
#
# Expected results:
#   Case 1 (prefecture with jobs)  → NO robots tag
#   Case 2 (busy area)              → NO robots tag
#   Case 3 (nonsense query)         → noindex, follow + cache MISS
#   Case 4 (empty area)             → noindex, follow
#
# If Case 1 returns a robots tag, ROLL BACK IMMEDIATELY.

set -u

DOMAIN="https://nihonarubaito.com"
UA="Mozilla/5.0"

hr() { printf '\n%s\n' "----------------------------------------"; }

echo "noindex guard verification — $(date -u '+%Y-%m-%d %H:%M UTC')"

hr
echo "CASE 1: Prefecture with jobs (Tokyo) — MUST NOT be noindexed"
curl -s -A "$UA" "$DOMAIN/part-time-jobs-in-tokyo" | grep -i 'name="robots"' || echo "  ✓ No robots tag (correct)"

hr
echo "CASE 2: Busy area page (Edogawa) — MUST NOT be noindexed"
curl -s -A "$UA" "$DOMAIN/part-time-jobs-in-edogawa" | grep -i 'name="robots"' || echo "  ✓ No robots tag (correct)"

hr
echo "CASE 3: Nonsense query, zero results — MUST be noindexed + cache MISS"
RESP=$(curl -sI -A "$UA" "$DOMAIN/chocolate-making-jobs-in-tokyo")
echo "$RESP" | grep -i "x-proxy-cache" || echo "  (no cache header)"
curl -s -A "$UA" "$DOMAIN/chocolate-making-jobs-in-tokyo" | grep -i 'name="robots"'
echo "  ^ Expected: <meta name=\"robots\" content=\"noindex, follow\" />"

hr
echo "CASE 4: Empty area (substitute real slug) — MUST be noindexed"
echo "  TODO: Replace <EMPTY-AREA> with actual empty area slug from DB"
# curl -s -A "$UA" "$DOMAIN/part-time-jobs-in-<EMPTY-AREA>" | grep -i 'name="robots"'

hr
echo "Prefecture job count check (requires MySQL running):"
echo "  SELECT prefecture_id, COUNT(*) AS n"
echo "  FROM jobs WHERE job_status_id = 3"
echo "  GROUP BY prefecture_id ORDER BY n ASC LIMIT 5;"
echo ""
echo "^ Shows how close any prefecture runs to zero in normal operation."

hr
echo "DONE"
