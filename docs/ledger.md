# Nihon Arubaito — Engineering Ledger

Decisions and hard-won lessons for nihonarubaito.com. This records why things are the way they are and what already went wrong, so a future session (or the terminal agent) doesn't relearn it the hard way. Keep entries lean — reusable lessons only, not a changelog. Newest at top.

---

## 2026-08-27 — Empty homepage cache poisoning: never cache 0-job renders

**Symptom:** Homepage shows "No job found" on cookieless first load (Googlebot, private browsers, first-time visitors); refresh shows jobs. AdSense Auto Ads placed 0 in-page ads (scanned the empty page).

**Root cause:** CDN cached a stale empty/Japanese render (likely from Aug 12 language outage). ApplyCacheHeaders set `Cache-Control: max-age=1800, public` on ANY English response, including empty ones. If the app momentarily returned 0 jobs, CDN served that empty page to cookieless visitors for 30 minutes.

**Diagnosis evidence:**
- Gate 1 query: 5,661 published English jobs exist (lang_id=1) — app layer clean, query correct
- Gate 2 cookieless curl: Cache EXPIRED, fresh response contains 90 job links — currently working, but risk of regression
- Verdict: (B) Cache-layer bug, not app-layer data mismatch

**Fix (deployed 2026-08-27):**
Two guards in ApplyCacheHeaders, following existing non-English-poisoning pattern (early return → Set-Cookie survives → nginx refuses to cache):

1. `ListingController::index()` sets `skip_cache_empty` flag when `$totalRows === 0`
2. `ApplyCacheHeaders` checks flag and returns early if set

Result: Empty renders stay uncached (Set-Cookie preserved); populated pages still cache 30 min. Driven by actual query count ($totalRows), not fragile HTML parsing.

**Regression test (run after every deploy touching homepage/cache):**
```bash
curl -s -A 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15' \
  https://nihonarubaito.com/ | grep -c "detail/english"
# Expected: 50-90 (page size dependent)
# Failure: 0 or <10 indicates empty homepage cached
```

**Post-fix verification:** 90 job links on cookieless request (2026-08-27)

**Note:** FrontendComposer emergency override (forces English) remains in place — its removal is a separate task pending language path verification.

---

## 2026-07-22 — Bulk-edit lessons (visa-hours correction, 10 pages)

- **Protected-page flags belong in the exclusion list.** Kanagawa carried a DO-NOT-MODIFY flag and was still swept into a 10-page bulk edit, because the exclusion list only covered content-based exceptions (Saitama's earnings line). Standing instructions need checking *before* writing a multi-page update.
- **Splice replaces eat conjunctions.** Replacing a mid-sentence clause consumed the preceding "and", producing a run-on on 9 pages. Conservation counts passed; the grammar didn't. Check rendered output, not just match counts.
- **Prefecture pages are template-*similar*, not identical.** Three heading variants exist for the same section ("How Foreigners Find Jobs in {X}", "How Foreigners Find Part-Time Jobs", "How Foreign Residents Find Part-Time Jobs"). A single shared anchor silently skips pages — enumerate variants first.

---

## 2026-07-22 — Widget (job-listing rich result) baseline restated

**The old metric is void.** The pre-registered schema-fix success measure — "0.18% CTR on 181,725 impressions/28d" — describes a surface that no longer exists at that size.

**What happened.** Rich-result impressions were *rising* through mid-July (Jul 13: 1,663 → Jul 14: 3,723 → Jul 15: 4,807), then collapsed: Jul 17: 144, Jul 18: 117, Jul 19: 179. A ~97% drop against the Jul-15 peak, same window as the desktop-impression deflation. Cause unverified; effect clear.

**Markup is exonerated** — valid items held 6,431–6,725 throughout, critical issues 0, warnings decaying (postalCode 6,546→4,767; maxValue 1,502→326), and position *improved* 2.18 → 1.48. Nothing about the fix caused this.

**New baseline:** ~150 impressions/day at position ~1.5. Clicks TBD — the 7-day click figure (23) spans the discontinuity and can't be split by day.

**Measure schema-fix success by:**
1. Warning decay (already effectively proven — both classes falling fast)
2. Valid-item count holding
3. CTR and position computed on post-Jul-17 data only

**Do not use:** any impression or CTR comparison spanning Jul 16–17. The Jul 13–19 aggregate (12,981 impressions) exceeds the prior week's 6,883 purely because it includes the pre-collapse days — a week-over-week read shows +89% growth on a channel that actually fell 97%.

---

## 2026-07-21 — Part G: Hamamatsu ward merge (COMPLETE)

Hamamatsu reorganized 7 wards → 3 on 2024-01-01; the areas table still held the 6 defunct wards. Resolved:

- Deleted areas 705–710; inserted 1898 (中央区 Chuo-ku), 1899 (浜名区 Hamana-ku).
- Renamed 711 english → "Hamamatsu Tenryu-ku" (was mistranslated "Hamamatsu chuo-ku").
- Renamed 3 municipalities: 421 篠山市→丹波篠山市, 670 筑紫郡那珂川町→那珂川市, 923 黒川郡富谷町→富谷市 (all with postal codes).
- Migrated 365 jobs (321 Chuo / 44 Hamana), including 8 address-corrected off 北区.
- Deduped 32 preferences → 15 (dedup was load-bearing: no unique index on user_id+area_id).
- Six retired slugs given explicit 301 overrides (see redirect note below).
- Sitemap regenerated (5,915 URLs); production dumps re-exported and swapped into translator.
- Import guard deployed (log-only) — see import-pipeline note.

**Backup:** `/home/customer/partg_backup_20260721_132931.json`

**Routing authority:** 中央区 gets old 中区/東区/西区/南区 + Mikatahara district of 北区 (初生町/三方原町/東三方町/豊岡町/三幸町/大原町/根洗町, verified vs city.hamamatsu.shizuoka.jp). 浜名区 gets 浜北区 + all other 北区. 711 天竜区 unchanged.

---

## 2026-07-21 — Area-deletion redirect fallback = lowest-id-in-town (NOT similarity)

When an area row is deleted, the area-slug fallback redirects the orphaned slug to the lowest-id surviving area in the same `town_id` — not a similarity match. All six Hamamatsu retired slugs 301'd to tenryu-ku (lowest id) until explicit overrides were added to RedirectLeakedPaths middleware.

**Consequence:** the earlier Miyakojima merge's "correct" redirect was luck (121 was the sole survivor in its town), not intelligence. Every future area deletion needs explicit 301 overrides — do not assume the fallback routes correctly.

---

## 2026-07-21 — Import pipeline: area_id is unvalidated; the feed is wrong independently of any reorg

CreateJobFromXmlController assigns `area_id` straight from the XML feed with no validation. The `join('areas')` in `Job::scopeWithLocalizedNames` is an INNER join, so a bad area_id makes the job silently vanish — no listing, no detail page, no 404. This hid 1,035 orphaned jobs for ~3 years.

Audit found 8 live-adjacent jobs whose addresses read 中央区/南区 but sat on 北区 — the feed mis-assigns area_id regardless of the reorg. Durable fix is address-based validation at import. Interim: log-only guard deployed at CreateJobFromXmlController (~line 352) — fires on a present-but-unresolvable id, skips `area_id=0` (legitimate "no area" case). Check `storage/logs/laravel.log` for `unresolvable area_id`.

---

## 2026-07 — Four instrument-failure lessons: a negative from an unvalidated instrument is NOT evidence

Each of these looked like a real defect; each was the measuring tool failing:

1. **Bare curl → 403.** Site blocks no-UA requests; read as a broken page. → Always use a browser User-Agent when curling the site.
2. **Redirect-stub greps.** Grepping a 301 response for content finds nothing; read as a missing feature. → Follow the redirect (`curl -L`) first.
3. **localhost-on-nginx.** `http://127.0.0.1` 301'd to HTTPS with empty body; read as origin failure. → Use `curl --resolve host:443:127.0.0.1`.
4. **Pretty-print grep miss.** `"postalCode":"..."` never matched because JSON is pretty-printed (`"postalCode": "..."`); read as field absent. → Grep the bare key first; check for whitespace/formatting.

**Verification stack** now has four named layers: (1) prose review, (2) byte/hash gates, (3) runtime-load confirmation (opcache disabled on host; CLI/tinker sees new code, web may not — md5 proves the FILE deployed, not that the RUNTIME loaded it), (4) instrument-validation-before-trusting-a-negative.

---

## 2026-07-21 — Bug #11: multilingual columns need per-column checks on rename

Area 711's vietnamese column still read "Hamamatsu chuo-ku," after the english-only rename fixed just english. The areas table has `chinese`/`english`/`japanese`/`korean`/`vietnamese` columns; a rename must check all language columns, not just english. (Observed pattern: chinese=kanji copy, korean=empty string, vietnamese=romaji with trailing comma.) Corrected to "Hamamatsu Tenryu-ku,".

---

## Standing environment notes

- **SSH:** ssh.nihonarubaito.com port 18765, user u4602-zegubfmytubg, Laravel root `~/www/nihonarubaito.com/public_html/laravel/`. All production DB work runs via SSH — never local XAMPP (a wrong-target near-miss occurred when a step ran against the empty local DB).
- **Opcache is disabled on this host** — file identity (md5) is sufficient to confirm a deploy; do not generalize this assumption to other environments.
- **SSH heredoc form** (`<< 'REMOTE'`) avoids nested-quote escape mangling that breaks inline tinker commands with `$` variables.
- **Sitemap regen:** `rm -f storage/app/sitemap.xml` FIRST (24h file cache makes `sitemap:generate` a no-op loop otherwise), then regenerate.
- **Translator dumps** (areas, prefectures) live as knowledge files in the separate "Nihon Arubaito Translation" project, resolved by grep. Re-export from production after any area edit. As of 2026-07-22 both are clean UTF-8 (PHP-written) — the old cp1252→`/tmp/areas_fixed.sql` encoding workaround is retired.
