# Nihon Arubaito — Engineering Ledger

Decisions and hard-won lessons for nihonarubaito.com. This records why things are the way they are and what already went wrong, so a future session (or the terminal agent) doesn't relearn it the hard way. Keep entries lean — reusable lessons only, not a changelog. Newest at top.

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
