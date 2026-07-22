# Engineering Ledger

Running log of completed work, bugs fixed, and lessons learned. Entries in reverse chronological order.

---

## Part G — Hamamatsu ward merge (COMPLETE, 2026-07-21)

7 defunct wards → 3. Deleted areas 705–710; inserted 1898 (中央区/Chuo-ku), 1899 (浜名区/Hamana-ku); renamed 711 english→Tenryu-ku plus 3 municipalities (421 丹波篠山市, 670 那珂川市, 923 富谷市). Migrated 365 jobs (321 Chuo / 44 Hamana), 32 preferences deduped to 15. Six retired slugs given explicit 301s in RedirectLeakedPaths middleware; sitemap regenerated; production dumps re-exported and swapped into the translator. Import guard deployed (log-only) at CreateJobFromXmlController line ~352.

**Backup:** `/home/customer/partg_backup_20260721_132931.json`

---

## Redirect fallback mechanism (learned during Part G)

When an area row is deleted, the area-slug fallback redirects the orphaned slug to the lowest-id surviving area in the same `town_id` — NOT a similarity match. All six Hamamatsu retired slugs 301'd to tenryu-ku (lowest id) until explicit overrides were added. This means the earlier Miyakojima merge's "correct" redirect was luck (121 was sole survivor), not intelligence.

**Takeaway:** Every future area deletion needs explicit 301 overrides in RedirectLeakedPaths middleware.

---

## Import pipeline: area_id is unvalidated and the feed is wrong independent of the reorg

CreateJobFromXmlController assigns `area_id` straight from the XML feed. The `join('areas')` in `Job::scopeWithLocalizedNames` is an INNER join, so a bad area_id makes the job silently vanish — no listing, no detail page, no 404. This hid 1,035 orphaned jobs for ~3 years. Audit found 8 live-adjacent jobs whose addresses read 中央区/南区 but sat on 北区 — the feed mis-assigns area_id regardless of the reorg.

**Durable fix:** Address-based validation at import; log-only guard is the interim backstop.

**Note:** `area_id=0` is the legitimate "no area" case (skip), not a resolution failure.

---

## Four instrument-failure lessons

Each looked like a real defect; each was the measuring tool failing:

1. **Bare curl → 403** — site blocks no-UA requests; read as a broken page. Always use a browser UA.
2. **Redirect-stub greps** — grepping a 301 response for content finds nothing; read as missing feature. Follow the redirect first.
3. **localhost-on-nginx** — `http://127.0.0.1` 301'd to HTTPS with empty body; read as origin failure. Use `--resolve host:443:127.0.0.1`.
4. **Pretty-print grep miss** — `"postalCode":"..."` never matched because JSON is pretty-printed (`"postalCode": "..."`); read as field absent. Grep the bare key first, check formatting.

**Takeaway:** Verification stack now has four named layers: prose review, byte/hash gates, runtime-load confirmation (opcache/CLI-vs-web), and instrument-validation-before-trusting-a-negative.

---

## Bug #11 (fixed 2026-07-21)

Area 711's vietnamese column still read "Hamamatsu chuo-ku," after Step A fixed only english. Multilingual columns need per-column checking on renames. Corrected to "Hamamatsu Tenryu-ku,".

**Lesson:** Multilingual tables need explicit per-column verification on rename operations.
