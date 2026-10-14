# Nihon Arubaito — Project Status (living document)

**Last updated:** 2026-10-13 (merged: 2026-10-12 chat + repo records [ledger, 56 commits since Jun 1, reports, CLAUDE.md, SETUP.md] + spam-backlink chat + tag-phase1 chat + Aug 30–31 prefecture_id/listing-audit chat + Jun 17–19 collation/images/cache chat + Jul 14–22 Part G/schema chat + Jul 23–24 Vietnamese/multilingual-cache chats (2) + Jul 27 GSC-export analysis + AdSense/Lighthouse investigation (May–Aug 6) + Oct 13 シゴトin apply-data chat (`claude/status_2026-10-13_shigotoin-apply-analysis.md`) + Jul 24–Aug 25 SEO/AdSense/Facebook session (`claude/status_2026-08-25_seo-adsense-facebook-session.md`) + content-workstream chat (`claude/status_2026-10-13_content-workstream.md`, after Aug 27) + a15d8696 "Fetch nihonarbuaito home" chat (Aug 17–26; flagged, read via ~66 in-chat searches) + Oct 13 FB-image chats (2) + Oct 13 Agent Reach chat + H1 2026 affiliate/Facebook chat (`claude/status_2026-10-13_h1-affiliate-facebook-analysis.md`, supersedes the Aug-window one where they differ) [older; gaps only — detail in `claude/status_2026-07-22_partg-schema-block.md`])
**Read this first in every new chat.** Update it at the end of any session that resolves, finds or decides something: edit lines in place, move finished items from *Pending* to *Resolved* with date + commit.
Detailed fix log lives in the repo: `docs/ledger.md`. Repo setup/deploy rules: `SETUP.md`. This file is the cross-chat summary.

---

## 0. This week (from 2026-10-13) — ranked by revenue impact

**Safety first (≈15 min, not revenue but irreversible risk):**
1. ✅ **GitHub (2026-10-13):** no classic tokens; the one fine-grained token ("macbook" — the Aug 15 one) is expired; OAuth apps = Git Credential Manager (Windows) + GitHub CLI (Mac) only; security log clean; 2FA on. Local Mac scan clean. **Windows scan (Oct 14):** git remotes clean (3 repos), no tokens in configs, Git Credential Manager in use (normal); a "tokens in history" alarm was a false positive (the scan matched the regex text inside `security-check.md` and a plugin's CI workflow); no stray SSH keys; one other-project `.env` (`Desktop\nihondebaito\.env`, owner to decide); ⚠️ **full production DB dumps with applicant personal data in plain files** — `Desktop\nihonarubaito\dbxrpxuz9wqtbp.sql` (132 MB), `Downloads\dbxrpxuz9wqtbp.sql` (134 MB), `Desktop\db_dbxrpxuz9wqtbp_20260722.sql.gz`, `Downloads\secondary_applies.sql` / `application_logs.sql` / `job_applications.sql`, plus old 2024 DBs (`dbqdt2tffnej0s*.sql`, `jobs.sql`, `dbclazhfowxlce.sql`). Owner to do: check OneDrive backup of Desktop/Downloads; keep at most one dump in a 7-Zip AES-256 archive; delete the rest + empty Recycle Bin (and OneDrive recycle bin if synced). Rule: never leave plain DB dumps on a workstation.
2. 🟠 **Web exposure (2026-10-13): `laravel.log` was world-readable** (16 MB; no credential values, but DB name, MySQL user/host, paths, stack traces). **Fixed at origin `6c1c919`:** log moved to `~/log-archive/laravel-2026-10-13.log.gz`; `public_html/laravel/storage/.htaccess` = `Require all denied` (proven ours: `/laravel/storage/x` 403 vs `/laravel/x` 404); homepage, sitemap, job detail, images, admin all still 200. Not indexed by Google (checked with control). ⚠️ **CDN still served the old 16 MB file → flush Dynamic Cache and re-check for 403.** Next (read-only): list sensitive-extension files anywhere under `public_html/` — nginx serves some static types straight from disk, bypassing `.htaccess` (e.g. `/laravel/storage/app/sitemap.xml` still 200 despite the deny). Full `laravel/` deny deferred (P6). Later: `LOG_CHANNEL=daily`.
2b. ✅ **Public backup zip + WordPress removed (2026-10-13).** `public_html_2_9_2026.zip` (245 MB, in the web root since Feb 9) held the live `blog/wp-config.php` (WP DB credentials + 8 salts); no Laravel files. Both zips moved to `~/site-archives/`, URLs 404/403 (control-checked); 0 download requests in the 30 days of logs; compromise check clean (WP couldn't reach its DB, 0 PHP in uploads, all recent PHP = our deploys). **`/blog` WordPress deleted** (owner: unused — 0 human visits in 30 days): archived to `~/site-archives/blog-2026-10-13.tar.gz` (9,231 entries), 171 MB freed; its redirects moved to the **top** of `public_html/.htaccess` (`/blog/part-time-jobs-in-X` → `/part-time-jobs-in-X`, `/blog/*-jobs-in-*` → same page, everything else → `/`), every one a single 301 hop; commit `35fde50` (snapshot at `docs/server/public_html.htaccess` — live file is outside the repo; pre-change backup in `~/site-archives/`). WP DB `dbjw28gwecin7f` + user `ujvp1x2d0elgu` **already gone** from Site Tools (deleted earlier, likely when WP was decommissioned in March) — leaked WP password is dead, no rotation needed. **Owner to do:** flush Dynamic Cache · Site Scanner · remove the two old blog sitemaps in GSC · then delete `~/wpdbnames.txt`; later download `~/site-archives/` (~438 MB) locally and delete it from the server.

**Then by revenue impact:**
3. **Sourcing brief to the team** (biggest lever — conversion collapse is supply): hand-cash (手渡し) everywhere, especially Aichi, Miyagi, Fukuoka; depth at proven stations (Shinjuku, Ikebukuro, Ueno, Namba, Kashiwa, Utsunomiya, Nishi-Funabashi); zeroed high-yield roles (convenience chains, izakaya/tonkatsu/ramen hall, McDonald's, school lunch); affiliate gaps Maebashi restaurant, Nishi-Funabashi packing.
4. **Facebook discipline** (~30% of approved affiliate conversions, 100% approval): hold the 8 posts/day/page cap (Jul–Aug ran ~15–24/day); Kanto page stops reposting Tokyo headlines (territory routing); link posts over photos; drop weak stations (Hiroo, Ginza, Toyosu, Kawasaki, Sannomiya); one clean timing test (morning vs 21:00–23:00) judged on applies.
5. **Old top-ranking jobs 108421, 112252, 112112, 113067** — check status + HTTP code; if they're 301/410, decide how to keep them alive.
6. **Finish the in-progress importer work** (VS Code): category guard bundle → address-based prefecture/area lookup — stops jobs silently disappearing at import.
7. **Hand-cash prefecture pages** — decide: never-noindex floor like the 47 prefecture pages, or source jobs into them.
8. **Oct 2026 minimum-wage revision** — re-verify wage figures (content pages + any listing sitting at the old minimum), Osaka first; then deploy the Osaka rewrite.
9. **Turn off `sg-cachepress` Speed Test** on `/blog` (keeps Lighthouse floods out of AdSense/GA4 data) · **Oct 14:** GA4 shows publisher ad metrics? · **GSC Soft 404 → Validate fix**.
10. **Decide the three conflicts:** multilingual architecture (vi. subdomain vs `/ja/` `/vi/`), Kanagawa freeze vs refresh, FB timing test design.

**Background (Claude Code):** merge this file into the repo as `docs/PROJECT_STATUS.md`, backfill `docs/ledger.md` from §2 Resolved, update `CLAUDE.md` (tiers, Decisions), then re-sync this project copy.

---

## 1. Snapshot (2026-10-12)

| Metric | Value | Note |
|---|---|---|
| Published jobs (status 3) | 6,114 | equilibrium: ~80/day in (2,417 in 30d), ~2,452 expiring in same window |
| Total jobs rows | 120,821 | status 4 = 11,471 (dead since 2026-04-03); jobs now retire into status 5 |
| GSC clicks/day | ~306 (last 28d) | ~413 mid-Jul → early Aug; position steady ~5. Season: peak ~525 May, trough ~225 Dec |
| GSC indexed / not indexed | 8,250 / 24,544 | 12,834 not-indexed = noindex (Tier 1 expired + empty listings, expected) |
| AdSense ad clicks/day | ~140 | ~250 in July |
| AdSense CPC | ~¥155–165 | ¥210–280 Aug 8 → mid-Sep; back to July level since Sep 21 |
| AdSense viewability | 27–31% | chronic; ~6 ad requests per pageview |
| Crawl response time | 843 ms | best of period; 1,654 ms week of Aug 17 |
| GSC Job Postings valid items | 8.17K | expected to fall toward ~6K after Oct 12 fix |
| Soft 404s | 294 | **mostly stale**: 251 last crawled before Jul 1, i.e. before the Jul 26 fixes |
| Sitemap | 5,915 URLs | last recorded 2026-07-21 |
| **Affiliate conversions per 1,000 GSC clicks** | **15.76** (Aug 2026) | **headline recovery metric.** 46.72 in May–Sep 2025 window → 13.17 same window 2026 (−72%) |
| Inventory penetration (% of new jobs that ever convert) | 5.5% (Aug 2026) | 19.3–20.3% early 2025 |
| Converting jobs per station | 1.17–1.32 | 3.23 Jan 2025 |
| Median age of converting listing | 4.3 days (p90 46.8) | 1.2 days (p90 9.1) in 2025 |

Earnings figures are intentionally **not** recorded (owner preference).

---

## 2. Resolved ✅

| Date | Commit | What | Verified |
|---|---|---|---|
| 2026-10-12 | `4f67caa` | Admin list/search also hid prefecture_id=0 jobs (INNER JOINs in `scopeForBackend`, `scopeSearchForBackend`) → LEFT JOINs. Revealed **216 hidden orphan jobs** (211 pending since Jul 2025). **210 stale pending orphans trashed** (status 2→5, `updated_at` untouched so they fall in Tier 2 not Tier 1). Backup: `~/pref0_trash_backup_20261012_170404.json` (restore = status flip to 2). Tochigi jobs: 163034 + 163036 published (pref 19); 163035 = probable duplicate, pending | list +215; search finds pref=0 jobs; published count unchanged 6,114; public pages byte-identical |
| 2026-10-12 | `3cca9aa` | **XML import "Job not found" fixed.** Jobs with `prefecture_id=0` were created but `Job::scopeForEdit()/forView()` INNER JOIN on prefectures hid them. Now LEFT JOINs (admin-only scopes). Import shows red warning "Prefecture 'X' not recognised — job saved as pending…" + `Log::error` with raw value. Cause of 0: translators' Claude left `<prefecture_id>` empty for Tochigi (not in its quick-reference table; project SQL dump has garbled Japanese) | 163034–163036 open; control 163033 unchanged; public pages byte-identical |
| 2026-10-12 | `a5ae50c`, `1c13e80` | **JobPosting/ItemList removed from all listing pages**; WebPage wrapper kept. Also removed fabricated listing dates (updated_at / +30d) | 10/10 Googlebot checks: listings 30→0, detail 1→1, detail/expired byte-identical. `docs/reports/jobposting-{baseline,after}-2026-10-12.md` |
| 2026-10-12 | — | AdSense ↔ GA4 relinked: pub-5261166510941827 → GA4 **Nihon Arubaito 345458315**, G-8ZMLZ8JK5L. Old Apr 2024 links (other login) weren't passing data. `japananndjapanese - GA4` (365003957) is empty/unused | Check Oct 14 (see Watching) |
| 2026-10-12 | — | Expired job lifecycle verified live: Tier 1 200+noindex+no JobPosting; Tier 2 301 to own area page | curl, no -L |
| 2026-10-12 | — | GA4 tag audited: one live tag, no double-firing | page source |
| 2026-10-12 | — | Manual actions + Security issues: clean | GSC |
| 2026-10-12 | — | Spam backlinks reviewed (seoexpress `.store` PBN, created 15 May–3 Jun 2026) → **no action, no disavow** | Manual actions clean |
| 2026-10-12 | — | Tag System Phase 1 schema authored as files (not deployed) — see Pending | `claude/status_2026-10-12_tag-phase1.md` |
| 2026-10-05 | `8415e4e` | "Benifits" typo → `benefits_label` in 3 language files | live: Benefits 1, Benifits 0 |
| 2026-10-05 | `964b518`, `53f374a` | Both Japanese-level commits reverted (`06c606c`, `51716b4`) — parked, see Pending | live |
| 2026-09-23 | `f267ca6` + `config:cache` | Intermittent 500s from empty `.env` reads (host fault) → config cache | MissingAppKeyException 70 (Sep 23) → 0 (Sep 24–28); 500s 21–46/day → 0 |
| 2026-09-23 | — | Traffic spike identified: Google PageSpeed/Lighthouse (google-proxy IPs, fwd+rev DNS). 213K requests, 173K in 09:00–12:59 UTC | logs |
| Aug 2026 | — | **Lighthouse source identified (Jul 15 storm, 172,428 hits):** the `sg-cachepress` (SiteGround Speed Optimizer) WordPress plugin on the `/blog` install calls the PageSpeed Insights API (`core/Analysis/Analysis.php`; result files `blog/wp-content/uploads/pagespeed_results_*.json`). No cron found (manual trigger suspected, unverified). The Jul 15 "invalid-traffic flood" was this, not a botnet; its 16,793 × 403 were our server refusing `.js.map` files. Ledger `ledger-adsense-lighthouse.md` (Aug 3). ⚠️ Sweeps recurred Aug 12 and Sep 23 → see P6 | logs + on-server PSI result files |
| Aug 2026 | — | AdSense crawler block ruled out: Mediapartners-Google + AdsBot ~95% 200s, zero 403s over 30 days; the SiteGround bot challenge doesn't hit Google's verified crawlers | logs |
| 2026-09-17 | `b917413` | 9 production-only FB V2 files brought into git | md5 server↔git |
| 2026-09-17 | `b432dac` | Empty-render cache guard extended to prefecture/area/search | `skip_cache_empty` in index/search/bySlug |
| 2026-09-17 | `b454e7a`, `2487e00` | SETUP.md: machine setup, two-machine discipline, drift check | drift check run |
| 2026-09-17 | — | Outage = SiteGround global CDN incident (support confirmed); cert valid Aug 20–Nov 18 | — |
| 2026-08-27 | `47f4a5c` | Empty homepage cache poisoning: never cache 0-job renders | cookieless curl: 90 job links |
| 2026-08-12 | `fd01d57` | Language cache poisoning fix + title optimization. Closes the hazard found Jul 24: `JobController` l.46–47 switches the session to the job's language, and `ApplyCacheHeaders` cached every response, so one VI-session visit could cache a Vietnamese page for English visitors for 30 min. Now non-English sessions keep Set-Cookie → never cached. Also added: `FrontendComposer` emergency English override + hidden `/lang/3`, `/lang/4` switcher links (merge `039f2a2`). ⚠️ The guard itself has **never been tested** — the override makes a non-English session impossible to create | ledger (re-check with the cookie-jar test in §7 if VI work resumes) |
| 2026-08-24 | `9ae3c7a` | Accessibility: `aria-label` on search fields (positive `tabindex` removed), accordion headers → real `<button>`s, `aria-label` on pagination. Lighthouse mobile: Accessibility 89→96, Agentic Browsing 1/3→3/3, Performance 73→92 | Lighthouse |
| 2026-08-24 | — | 82 job WebP images resized to **476×317** (2× the 237×155 slot; supersedes the Jul 23 500×333). Backup `~/backups/jobs-images-20260824.tar.gz` | listing + detail display unchanged |
| 2026-08-24 | `9586890` | `docs/gsc-measurement-notes-2026-08-24.md` + rewritten GSC section in `CLAUDE.md` | — |
| 2026-08-15 | — | FB links tagged `&src=` by apply-link host (`getJobSource()`): shi 46% · bt 30% · fm 21% · sft 3% · others tiny; `x` = unmapped new source. 21,441 old `fb_posts` archived to `fb_posts_archive` (639 kept). FB admin prefecture filter + live search shipped (later broken Aug 28, see below) | new link `?utm_source=fb&src=shi` |
| 2026-07-27 | — | **Job titles location-first** (`JobController`): `{location} — {role}`, wage segment protected, CJK station kanji stripped. Fixed 627 identical FamilyMart titles from the old 42-char cut; residual duplicates 493 (legitimate same-station jobs) | `/jobs/141713/detail/` |
| 2026-07-27 | — | `Job::detail_path` accessor so FB post URL = canonical URL (`createFbPost` uses it — the May slug-less fix); homepage pagination: `/jobs/page/N` self-canonical + "- Page N" titles | live |
| 2026-07-26 | — | **Canonical fallback** `$canonical ?? url()->current()` (was the homepage — every page without a canonical claimed to duplicate `/`). Search page no longer canonicals to the prefecture page | 6-URL before/after snapshot |
| Aug–Sep 2026 | `b917413` (Sep 17) | **FB `prefecture_id` incident (Aug 28)**: uncommitted 8-file changeset; a staging bulk-overwrite (00:07:05 UTC) + stale 2-file deploy (00:07:52) removed the `createFbPost()` hook + admin dropdown → 100% NULL `fb_posts.prefecture_id` from Aug 28 (344 rows by Aug 31). Production-only FB V2 files later brought into git; `fbposts:backfill-prefectures` exists. ✅ **Closed 2026-10-12:** hook restored (`39a6076`) + `prefecture_id` in `FbPost::$fillable` (Sep 17); 826 posts Oct 5–12, **0 NULL**. Last check pending: count `prefecture_id = 0` (should be 0 — pref=0 jobs can't publish) | NULL-bucket query |
| Aug 2026 | — | **August spam update (Aug 18–21) — no hit.** GSC clicks 336/day the week before and after; manual actions clean | GSC |
| Aug 2026 | — | August "traffic drop" = seasonal step-down Aug 5–14 + Obon, **not** the speed deploy Also: GA4 organic engagement unchanged (79.8%/124 s → 80.1%/120 s); the Aug 18 "−252 indexed" was expiry churn (noindex pool rising ~57/day since May, no spike), not the language bug | GSC 336/day both weeks |
| Aug 2026 | — | Cache-guard response-time regression recovered (1,654 → 843 ms) | crawl stats |
| 2026-07-27 | — | **301 crawl growth reviewed → benign** (7.18% of crawl). Five sources, all correct: legacy `/laravel/public/index.php/…`, Tier 2 expiries, `?utm_source=fb` job links, old kanji-slug URLs, legacy `/jobs/search?query&prefecture_id=`. No action | URL sample |
| 2026-07-26 | `df41efc`, `adb4aa9` | **Noindex empty listing pages** (three-guard rule, see Decisions) + cache bypass | guards in code |
| 2026-07-26 | `c15e15f` | **`/laravel/public/index.php/*` → 301** to clean path (`public/.htaccess:12-13` + `RedirectLeakedPaths.php`) | 1,041 distinct URLs covered |
| 2026-07-26 | `fe16713`, `d26950c`, `405cd32`, `310cc5a` | Sitemap modifier pages; 8 clean-ratio stations; area-slug substring fix; nakano removed (collision) | — |
| 2026-07-23 | — | **Job images resized** 1200×800 → 500×333, later 476×317 on Aug 24 (328 files, `mogrify -resize '500x333>' -quality 85`): listing-page image weight 1,270 → 375 KB (−70.5%). Upload path capped too (`Admin/ImageController.php`: accepts WebP, resizes to 500×333). Backups `~/jobs_images_backup_20260723.tar.gz`, `ImageController.php.bak.20260723_161454` | live page; admin WebP upload → 333 px |
| 2026-07-23 | — | robots.txt: `/jobs/search` and `/jobs/areas/get` disallowed (were ~22% of crawl) in `*` **and** each named-engine group; `/laravel/public/index.php/frontend/images/` blocked; `Mediapartners-Google` group (Disallow `/admin`); `stepstoneCrawlBot` Disallow `/` (14,731 hits/30d). *Supersedes the Jul 14–16 removal of the `/jobs/search` disallow* | deployed |
| 2026-07-24 | — | Vietnamese test job **156689** (lang 3, Osaka packing) published; detail page renders and caches as Vietnamese. ⚠️ Its `apply_url` was blanked to get past the uniqueness block — restore it once the gate is fixed | live |
| 2026-07-21 | `eddc4e2` | Hamamatsu 7→3 wards, 365 jobs migrated, 6 retired slugs 301'd (Part G: new areas 1898 浜松市中央区 / 1899 浜松市浜名区, postal 432-0000; 711 renamed Tenryu-ku; also 421→丹波篠山市, 670→那珂川市, 923→富谷市). Backup `/home/customer/partg_backup_20260721_132931.json` | re-verified 2026-09-30. ⚠️ The July "precision pass" (8 misrouted expired rows 1899→1898) never returned output — one-line check: expect 1898 = 321, 1899 = 44 |
| 2026-07-20/21 | — | **JobPosting `postalCode`**: `areas.postal_code` VARCHAR(8) added, 1,865/1,896 filled from Japan Post ken_all 「以下に掲載がない場合」 base codes (31 legit NULLs); emitted only when present (key omitted on NULL). Source `/home/customer/catchall_city_zip.csv` | Rich Results Test job 156275: postalCode 160-0000 |
| 2026-07-17/18 | — | **JobPosting `baseSalary`**: min-only wages ("〜", 以上, 本採用〜) now emit `value`, not `minValue` (maxValue warnings 1,502 → 326 in 4 days). Frozen anti-reversion comment in `Job.php` parser | 5,054-job conservation exact; RRT value 1350 |
| 2026-07-17 | — | **Crawl-latency / session-cookie Dynamic Cache fix confirmed live:** `Cache-Control: max-age=1800, public`, `X-Proxy-Cache: HIT` on repeat; route cache groups 1800/3600/86400 in `routes/web.php`; TTFB ~452 ms on HIT. Remaining crawl time = trans-Pacific RTT + long-tail misses (structural) | GET MISS→HIT |
| 2026-07-17 | — | Homepage meta: description 188→151 chars (frozen string in `ListingController.php` ~l.88); `og:site_name` "Nihon Arubaito"; `og:type` website on homepage, article elsewhere | live curl |
| 2026-07-14/16 | — | Hygiene A–D: 44 macron/comma slugs fixed via `Str::slug()` (10 call sites); 410 for legacy CI3 numeric IDs; nikkyuubarai→hibarai (12 posts); area renames (167 Matsubara, 121 Miyakojima-ku Osaka, 88 Sagamihara Midori-ku, 478 Fukagawa). Miyakojima merge 1897→121 with 301s. `llms.txt` live; `/jobs/search` robots disallow removed (re-added Jul 23) | ledger |
| 2026-07-14/16 | — | **Wage audit (Part F):** 5 posts had invented wages under a false "Oct 2025" stamp → corrected from primary sources (see Decisions: verified minimum wages). Osaka surgical edits (incl. visa "40h/wk"→"8h/day"); **Hokkaido full rewrite** deployed (md5 `40a5fc00…`) | primary sources |
| 2026-07-21 | — | Import `area_id` unvalidated (INNER join hid 1,035 orphans ~3 yrs) → log-only guard | ledger |
| 2026-10-12 | — | **ValueCommerce analysis**: full 2025 + 2026 YTD (to Sep 4) + GSC cross-join (May 2025–Sep 2026). Outputs `ValueCommerce-2025-deep-analysis.md`, `NihonArubaito_ValueCommerce_2025_Analysis.xlsx` (12 sheets). Key results in §3a | zero row overlap; 363/365 days |
| May 2026 | — | FB slug-less URL defect fixed (slug-less share of FB conversions 100% Jan–Mar → 92% Apr → 0% from May) | referrer data |
| Apr 2026 | — | **Kanji-slug URL fix confirmed:** conversions on URL-encoded kanji slugs Jan 270 → Feb 83 → Mar 15 → Apr 1 → 0 (last 2026-04-04). In Jan, 230 of 231 organic conversions came in on kanji slugs | VC referrers, H1 |
| Sep–Oct 2025 | — | Postback duplication defect stopped (inflation 1.30 Sep → 1.02 Oct, 1.00–1.04 since). Fix location unknown | VC data |
| 2025-04-17 | — | ArubaitoEX advertiser ended (last order). 880 rows, 29.9% approval | VC data |
| 2026-06-18/19 | — | **SiteGround "DT:1" cache investigation closed — no problem.** `X-Proxy-Cache-Info: DT:1` is a HEAD-request marker, not a bypass flag; `curl -I` never engages the cache. GET shows MISS → HIT (SiteGround, Shirin A., confirmed twice; reproduced with Googlebot UA). `ApplyCacheHeaders` works as intended (200 → public + Set-Cookie stripped; 4xx → no-cache, private) | GET MISS→HIT |
| 2026-06-17 | — | `secondary_applies` latin1 → utf8mb4_unicode_ci (51,434 rows; backup `~/backups/secondary_applies_20260617_113927.sql.gz`). Error 3988 had been *rejecting* inserts, not garbling them → no historical corruption, but those applications were never saved (forward-only fix) | row count 51,434 = 51,434; Japanese insert test |
| 2026-06-17 | — | 81 images uploaded as WebP (IDs 679–759; 1200×800, q85 — resized to 500×333 on Jul 23; 38.8 MB → 7.0 MB). Library = 245 legacy JPG + 81 WebP = 326. Rollback: `DELETE FROM images WHERE id BETWEEN 680 AND 759` + matching `rm` | 5 spot checks HTTP 200 image/webp |
| Earlier | — | www→non-www, trailing slash, Japanese slugs, title truncation, area slug canonicalisation, BreadcrumbList, `rel="nofollow sponsored"`, duplicate prevention | — |

---

## 3a. Core diagnosis — affiliate conversion collapse (ValueCommerce, 2026-10-12)

**Traffic is not the problem; on-site conversion is.** Same window May 5–Sep 4: search clicks 43,902 → 53,064 (+21%), position 6.62 → 5.29, affiliate conversions 2,051 → 699 (−66%). Jan–Aug deduplicated: 5,255 → 1,688 (−68%).

What drives it (all supply/composition, not SEO):
- **Breadth collapse:** converting jobs 556 → 135/month through 2025 (~75% of the decline); converting jobs per station 3.23 → 1.17. Where station depth held, per-job yield rose (Shinjuku 1.91→2.29, Ginza 1.42→2.00); where it collapsed, yield fell (Omiya 2.51→1.00, Sendai, Matsudo, Hakata).
- **High-yield roles at zero in 2026:** family-mart, mcdonalds, lawson, school-lunch, tonkatsu-hall, family-restaurant, ramen, yakiniku, dishwashing; seven-eleven 486 → 36. Convenience family 585 → 37. Highest 2025 yields: izakaya-hall 2.48, tonkatsu-hall 2.35, family-mart 2.15, sorting-and-packing 2.03, mcdonalds 2.00, seven-eleven 2.00.
- **Search demand vs inventory are opposed:** hand-cash queries = 13,234 clicks at 16.8% CTR, but hand-cash share of converting jobs 3.7% → 1.4%. Daily-payment share rose 9.6% → 48.5% while its queries bring 156 clicks.
- **Listings convert later and less:** share of conversions from listings 14+ days old 7.3% → 31.4%.
- Per-job yield fell ~20% in every job family (systemic). Publishing volume unchanged (~2,400/month both years).
- **Every conversion originates on `/jobs/{id}/detail`** — listing pages convert only by sending visitors to detail pages. The unmeasured step: listing → detail click-through (needs GA4, Japan).
- **シゴトin H1 2026 (1,401 transactions, resolved statuses):** approval 89.9% (1,260 請求済み / 141 拒否); 6.96 approved/day (Jan 11.0, Feb 5.6, Mar 4.5, Apr 7.4, May 7.1, Jun 6.0). The Feb–Mar drop has two causes: organic dipped with the kanji-slug URL migration and recovered to ~92% of January by May; Facebook fell from 4.45/day (Jan, 40.5% of approved) to ~1.1–2.4/day and hasn't recovered — 56% of the Jan→Jun loss. Aug 1–10 (pending) ran 6.5/day raw.
- Flat long tail, back catalogue matters: 419 stations converted, 240 of them exactly once (23% of volume); in Aug 1–10, 37% of conversions came from listings >1,000 job IDs old. Approved by category: restaurant 529, packing/sorting 332, cleaning 106, convenience 29 (inventory is live), bed-making 29, hotel 5, delivery 1. Regional mix differs: Kanagawa 51% packing, Gunma 52% packing; Miyagi/Fukuoka/Tochigi ~63–68% restaurant; Aichi is the bed-making + cleaning market. Daily pay appears on 77% of approved packing, 72% cleaning, 69% bed-making but only 30% restaurant — it's the norm for manual roles, not a yield booster (per-job yield decision stands). Udon-soba is split across 4 slug spellings (112 approved combined — would be the #2 role).
- **Cookie-replay duplicates (H1 2026) — the main rejection cause.** 21.4% of rows share a click timestamp with another row on the same offer; collapsing them cuts 1,401 → 1,212 (~13% inflation, landing unevenly in the months *after* busy months). 90 of 141 rejections are multi-day replays (median lag 16 days, max 57.6; one Jan 2 click fired 12 conversions). Approval: clean same-day 98.8% · duplicated same-day 85.9% · late 33.3% · duplicated + late 21.7%. **Facebook traffic: 385 actions, 0 rejections** (in-app browsers sandbox cookies, nothing to replay); Safari 95.5% vs Chrome 84.5%. Double-tapping is a minor secondary effect. ⚠️ This is a different defect from the 2025 postback duplication (fixed Oct 2025, ratio 1.00–1.04): that dedup key includes `order_at`, so replays pass straight through it — the deduplicated counts above in this section likely still include ~13% replays.
- **Commission re-code:** シゴトin moved offers from group GRP07 to GRP01 in a hard cutover 2026-02-03 (76 offers under both codes). GRP03's share of approved rose to 32.6% by June. No export has a commission column, so the yen impact is unknown.
- **Email applies dwarf affiliate applies:** `/conversion-report` (120 days to Aug 23): 19,388 applies = 747 affiliate (`application_logs`) + 18,641 email (`secondary_applies`). Tokyo 30%, Osaka 19%, Fukuoka 12%. Restaurant 4.57 applies/job vs packing 2.19. Hakata 3 (Feb) → 219 (Jul). Top station × category (all applies): Kashiwa packing 112, Amagasaki packing 111, Ikebukuro restaurant 109, Akabane packing 102, Shinjuku packing 101 — but Kashiwa, Amagasaki, Akabane and Okubo have **zero** affiliate applies; affiliate share is only 4% at Shinjuku. Affiliate leaders: Shinjuku 17, Utsunomiya 11, Sakae 11, Higashi-Shinjuku 8, Shibuya 8, Maebashi 7, Nishi-Funabashi 6 (last two = sourcing gaps).
- **Facebook:** worked until **2025-11-01**, then stopped dead (Oct 2025 FB = 52% of approved conversions, peak 44/day Oct 17). **Cause now known (a15d8696):** the original page "NihonArubaito.com" (created 2016, 50K+ real followers built with 2017–18 boosts) went viral Oct 11–16 2025, then posting jumped to 50–82 posts/day (some <1 min apart, 93% with URLs, repeated headlines) and the page was banned by Nov 1. Its Oct 2025: 22,106 link clicks (21.8/post) vs the best month of the current three pages (Apr 2026) 2,212 (1.4/post). The lost asset is the follower base. Note: the H1 2026 analysis shows FB still at 4.45 approved/day in Jan 2026 (40.5% of approved), decaying to ~1.6/day by June — FB traffic continued through the replacement pages and groups, decaying; its FB proxy may also include non-FB in-app browsers. FB is 22.8% (Oct 12) / 27.5% raw, 30.6% approved (H1) of 2026 conversions. Aug 1–10: FB 0/day Aug 1–3, ~1.9/day from Aug 4 — cause unknown (not the `createFbPost` URL fix, which landed in May).

---

## 3. Pending (priority order)

### 🔴 P1 — Soft 404 cleanup (small; most already fixed Jul 26)
- **GSC → Pages → Soft 404 → "Validate fix"** to trigger a recrawl of the stale 294. Most were crawled before the Jul 26 noindex + index.php fixes. If any remain after recrawl, cross-check the list against the 192 demand-supply gap pages (suspected overlap, never checked).
- **Decide threshold:** current noindex rule fires only at **0 jobs**. Pages with 1–2 jobs stay indexed (doorway-risk zone). Option: raise to < 3 (prefecture pages stay exempt). *Owner decision.*
- **Hand-cash prefecture pages have no floor:** the never-noindex rule protects `part-time-jobs-in-{pref}` but not `hand-cash-jobs-in-{pref}`; Aichi, Miyagi, Niigata, Kochi, Saga, Tottori (and per Aug 25 also Kagawa, Yamanashi, Nagano, Shizuoka) are noindexed when their text search returns 0. The soft-404 list is 83% hand-cash + area pages. Decide: floor them, or source hand-cash jobs there (sourcing was asked to prioritise 手渡し in Aichi, Miyagi, Fukuoka)
- **Pagination past last page** — no explicit guard (unverified): out-of-range `bySlug` pages render empty → caught by noindex; but `/jobs/page/N` out of range gets a self-referencing canonical. Fix: 404 when page > last page (16 URLs in report).
- **Deep pagination is indexed** (`/jobs/page/25`, `/166` seen in Google, Oct 13) — self-canonical since Jul 27 and every page reuses the homepage description. Decide: `noindex, follow` on page 2+ (keeps link discovery) vs leave. Also: Toride area page's Google snippet starts mid-word ("ito provides part-time job listings…") — the area meta description is being cut from the front; check the meta builder and how many area pages are affected
- 1 uppercase URL `/part-time-jobs-at-Hirose-dori-station` — rule says 301 to lowercase; check why not.

### 🔴 P1a — XML importer: resolve prefecture/area from `<address>` server-side
XML carries `prefecture_id` as a number (`(int)`), so empty/non-numeric → 0. Translators' Claude can't do exact ID lookups in chat (project `prefectures (1).sql` / `areas (1).sql` Japanese is mojibake, e.g. 栃木 → `æ ƒæœ¨`; ~1,900 areas). Plan: when IDs missing/invalid, match address prefecture (strip 都/道/府/県/市; live `prefectures.japanese` is inconsistent: id 14 = 新潟市, 23 = 熊本市) and city/ward against `areas` within it (longest match); validate given IDs against address; dry-run on last 200 jobs and report match rate. Short-term: full 47-prefecture ID table added to translators' instructions (owner to do).
Stuck jobs: 163034 & 163035 → pref 19, area 1104 (Shimotsuga Mibu) — **likely duplicates, trash one**; 163036 → pref 19, area 1088 (Sano).

**Category orphans (same broken imports):** 221 jobs with no matching category — 210 at `job_category_id=0` (field omitted by translators' Claude), 9 at 6/7 (don't exist; table has 1–5). None published. Approved next: importer category guard (red warning + `Log::error`) and LEFT JOIN categories in public `scopeWithLocalizedNames` (render test first, so a NULL category can't hide a published job). Translators' prompt: add required-fields block (prefecture_id, job_category_id 1–5 only, full address).

### 🔴 P1b — Check old top-ranking job pages (quick, possibly high value)
Jobs **108421, 112252, 112112, 113067** (early-2025 hand-cash/bed-making) still rank at positions 4–7 and are 7 of the top 10 ranking detail pages. If they're past 90 days they may now be 301'd or 410'd — losing pages Google ranks well. Check status + HTTP response; decide whether strong-ranking expired pages should keep living (e.g. refreshed with current similar jobs).

### 🔴 P1c — Sourcing (the actual revenue lever — owner/sourcing team)
Restore depth at proven stations and the zeroed high-yield roles (convenience chains, izakaya/tonkatsu/ramen/yakiniku hall, McDonald's, school lunch), and hand-cash inventory. **Demand density, H1 (conversions ÷ converting listings — a relative supply-gap signal, not a rate):** Nishi-Funabashi 2.12, Ikebukuro 2.08, Utsunomiya 1.89, Nishi-Shinjuku 1.75, Sannomiya 1.70, Shinjuku 1.60, Ginza 1.58; prefectures Tochigi 1.50, Chiba 1.43, Tokyo 1.37 (Saitama lowest of the top 8 at 1.16). A live-inventory join is still needed (blocked: demand-supply dashboard uses the wrong GA4 export). Inspect what currently renders on the top hand-cash cluster pages (`/hand-cash-jobs-in-tokyo` 7,038 clicks, osaka 4,080, fukuoka 2,718, saitama 2,426, kyoto 1,809, chiba 1,641, shinjuku-ward 1,580) — are visitors finding hand-cash jobs there?

### 🟠 P2 — Expiry tier date source
`handleExpiredJob()` uses `Expire_Date ?? updated_at ?? date`; `updated_at` changes on any edit, and status-4 rows are frozen at ≤ 2026-04-03. Switch to **`delete_at`**. Also **trace what actually retires jobs into status 5** (`jobs:auto-trash` sets status 4 and has no backlog — live path differs from code description, unverified). Also check `delete_at` age vs tier status — is Tier 1 → Tier 2 promotion falling behind (11.4K noindex pool in Aug)?

### 🟠 P3 — Title/meta CTR rewrite on big pages
| Page | Impr (3mo) | CTR | +clicks/day at 3% |
|---|---|---|---|
| `/` | 259,858 | 1.33% | +48 |
| `/part-time-jobs-in-tokyo` | 111,592 | 1.06% | +24 |
| `/part-time-jobs-in-osaka` | 80,489 | 1.49% | +13 |
| `/part-time-jobs-in-kanagawa` | 45,642 | 0.60% | +12 |
| `/part-time-jobs-in-aichi` | 44,920 | 0.68% | +11 |
Hand-cash pages get 5.2% at the same position. Lead with 手渡し / daily pay / English / real wage / live job count. Test 5, compare 28 days. (Note: Kanagawa content is frozen — title/meta only.)

### 🟠 P4 — AdSense URL channels
`/hand-cash-jobs-in`, `/part-time-jobs-in`, `/jobs/`, `/bed-making`, `/daily-pay` → per-section CPC/clicks. Not retroactive.

### 🟡 P5 — Ad density / viewability (<30%, ~6 requests/PV)
**Diagnosis (Aug, May–Jul clean days):** revenue decline is monetization erosion, not traffic. Ad clicks per 100 pageviews 9.72 → 5.12 (−47%); coverage (impressions ÷ requests) 73.8% → 67.3%; impression CTR 1.97% → 1.06%; clean-day pageviews flat-to-up. Likely market-wide 2026 RPM decline + ad fatigue (~5 ads/PV, ~12 slots, 89% mobile). Next: AdSense **by Ad unit** report to find dead/zero-width mobile slots; then the deferred lever — fewer ads + header bidding, one change at a time. A re-baseline (30-day pageviews, coverage, impression CTR vs the May–Jul figures) was never pulled. Aug 1–24: Active View 26–29%, impressions/PV 5.1 → 3.3, match rate ~62% → ~72%; ~21,000 impressions/day served, ~5,700 billable. Suspected (unverified): the loader's 5-second fallback vs Facebook sessions averaging ~9 s. Not taken: shorter fallback (field INP already 227 ms), disabling anchor/vignette, lower Auto-ads density.
Don't move ad placements during a suspected seasonal RPM dip until a Page-views-vs-Page-RPM split says whether it's traffic or RPM.

### 🟡 P6 — Small fixes
- Delete 2 dead analytics blocks: malformed GA4 duplicate `frontend.blade.php:83–89` (`-->` inside `<script>`), old UA-89925520-1 `partials/scripts.blade.php`
- Remove `sourceMappingURL` from production JS (16,190 403s on Sep 23 for missing `.map` files; same 16,793 on Jul 15)
- `/laravel/public/index.php/` leak source: Aug found ~27% of these 301s carried `utm_source=fb` — confirm the Facebook-share workflow emits clean canonical URLs (the 301s catch it, but the source refills it)
- Spot-check `/part-time-jobs-in-ashigarashimo-gun-yugawara-machi` (noindexed in Aug — empty or wrongly noindexed?). Shiraoka was checked: empty, noindex correct
- Duplicate `jobs:auto-trash` cron (in scheduler + own cron; idempotent, harmless)
- `LOG_LEVEL=error` hides successful cron runs — no success trail
- **Refresh the project instructions** — they're ~8 months stale: tier text says 90–180 / 180+ (code is 91–365 / 365+), GSC figures are from Feb, wages are the 2025 revision with "Osaka… ¥1,100+", "Tokyo done, Osaka next". Same tier fix in CLAUDE.md
- 35 jobs missing `baseSalary`; WebPage description truncated "...."
- GA4 fires on **scroll + 1 s** (per a15d8696) — it undercounts people who leave without scrolling, so GA4 and AdSense count different populations. Mark the apply click as a GA4 **key event** (was 0 key events in Aug). Facebook links without UTMs leak into Direct
- Job post text: rotate the opening sentence (every listing starts "This is a [role] at [place]…" — 6–8 variants); clean `prefectures.japanese` (新潟市 / 熊本市 are city names, 若山 looks like a typo for 和歌山, 県 suffix inconsistent)
- **PageSpeed sweeps (Jul 15, Aug 12, Sep 23):** source identified in Aug as the `sg-cachepress` plugin's Speed Test on `/blog`. Disabling that feature (keep its caching) was planned but never confirmed — the Aug 12 and Sep 23 sweeps mean it's still active or re-triggered. Check the plugin setting and who runs it → **resolved by deleting `/blog` (Oct 13 plan)**; confirm no Chrome-Lighthouse spikes afterwards
- 459 "Indexed, though blocked by robots.txt"; 1,025 "Google chose different canonical" (1,556 in Jul — falling; spot-check a few examples if it grows)
- Spam-policy hygiene: trim long city/area link lists; every job page should add value beyond translation

- Two FB prune implementations coexist (closure in `routes/console.php` + unscheduled `PruneFbPosts`) — pick one
- **Per-shift conditions get flattened** (e.g. Seven-Eleven Yaesu: 18:00–01:00 slot needs min 6 h, site says "Minimum 4 hours"). Cause: `bulk-job-processing-workflow.md` Part 2 asks for natural English into one flat `working_hours` field. Add rule to that file / translators' prompt: "per-shift conditions (minimum hours, age limits, wage differences) stay attached to their shift, never merged". Correct the Yaesu listing; find others with `working_hours LIKE '%/%' AND working_hours LIKE '%Minimum%'` and check ~12 against their `apply_url` sources
- **Duplicate conversions:** raise cookie replay with ValueCommerce (not yet done — it's most of the rejections). An Apply Now debounce would only fix the smaller double-tap part (~a fifth)
- Slug defects: `&` HTML-escaped before slugging (`…-yakiniku-hall-amp-kitchen-…`); daily wages (日給) land in the hourly-wage slug (e.g. 11200jpy Shin-Matsudo) — title generation doesn't tell 時給 from 日給; 26 live jobs had their `apply_link` changed (one job → several offers), and 10 offers map to more than one job
- Proposed: `?src=` on internal links into detail pages (shows the entry surface in the VC referrer); `conversion_count` on `jobs` from `applogs:fetch` as an auto-extend signal for `delete_at`
- Literal `<br />` shows in the Station field on some job pages (render impact unverified)
- Optional: hidden-Unicode check (U+200B/200C/200D/2060/FEFF/202F) in the publish gate — prefecture content files scanned clean Aug 30
- Prefecture listing meta descriptions: cap dynamic meta with `Str::limit(~155)` (Bing "description too long" flags; homepage already fixed Jul 17)
- Backlog (Jul): slug-accessor refactor (10 `Str::slug()` call sites); in-code cache-bust in `GenerateSitemap`; GPTBot/CCBot allow/deny decision; blog subdomain dead link; pagination raw-URL glitch; redirect-chain flattening
- Backlog (Aug): one-way `en-jp` hreflang on 3,668 URLs (no return links, no x-default); "Please select" placeholder leaking into URL paths; root `favicon.ico` is 0 bytes; 8 macron slugs still 404 (`jinsekikōgen`, `shikokuchūō` …); `og:type` article on listing pages; 11 ambiguous stations in the sitemap allowlist (station pages text-search 7 fields — ambiguous stations like `tokyo`, `chiba` convert at 0.2–0.7% vs 7–12% for clean ones); raise the prefecture-modifier sitemap floor 2 → 5; consider `dish-washing` / `kitchen-staff` modifier pages; `utm_campaign=<src>` alongside `src=` so GA4 can report it
- Old WordPress sitemaps `/blog-posts-sitemap.xml` and `/blog-categories-sitemap.xml` still get requests (46 in 30 days, all 404) — remove them from GSC Sitemaps and from robots.txt / the sitemap index if referenced
- **Full `/laravel/` web deny (deferred from Oct 13):** routing is `public_html/.htaccess` l.132–138 — everything not under `/laravel/` rewrites to `/laravel/public/index.php`; anything under `/laravel/` is served from disk, so `artisan` is served as text and `config/*.php`, `routes/*.php` execute (500). Fix: one rule in `public_html/.htaccess` denying `/laravel/*` except `/laravel/public/` (or `laravel/.htaccess` deny + `laravel/public/.htaccess` grant). Test after: homepage, job detail, admin, an image, a legacy `/laravel/public/index.php/…` 301, with cache-buster. nginx-served static types bypass it either way. Then `LOG_CHANNEL=daily` (~14 days) + `config:cache`
- **robots.txt `/laravel/public/` Disallow:** Aug 25 recommends *removing* it now those URLs 301 (Google must crawl them to see the redirect) — opposite of the Aug 6 idea to add more. Newer view: remove
- **Security:** revoke the GitHub personal access token that was found in plaintext in an old `.git/config` (stripped Aug 15, revocation never confirmed) — github.com/settings/tokens
- **Security:** check that nothing under `public_html/laravel/` is web-reachable (`.env`, `storage/`) — curl with a browser UA, expect 403/404

### Blocked / parked
- **SiteGround ticket #5141798** (opened 2026-09-30, escalated): intermittent empty `.env` reads. Config cache protects web only — **cron and artisan still read `.env` directly** and can fail.
- **Japanese-level redo** — parked until after ~Oct 8 (now unblocked). Feed `japanese_level` is ~constant 3 (2,292/2,417 = 94.8%). Agreed map: level = JLPT number. One pass: labels + new-job assignment + optional ~3,358-row bulk fix.
- **Tag system:** Phase 1 schema exists in repo (`e384f17`) + Phase 1 files authored 2026-10-12. Before migrate: run precheck (`jobs.id` bigint vs int unsigned → FK error 150; DB default collation suspected latin1; Laravel version; no `job_tag%` tables); **rename `2026_06_17_*` migration prefix to current timestamp**; verify `utf8mb4_unicode_ci` via `SHOW TABLE STATUS`; Japanese round-trip test. Phases 2–4 not started. ⚠️ **Phase 4 landing pages conflict with Weekend NO-BUILD — see Decisions.** CLAUDE.md lists tags as "priority 1 / highest ROI" citing weekend 164K impressions/28d → ~1 click; that's impressions, not demand. Re-rank. ⚠️ **The June draft `JobTagSeeder.php` seeds weekend / night-shift / summer with `has_landing_page=true` — set weekend and night-shift to `false` before seeding** (NO-BUILD). `summer` (`/summer-jobs`, 8,303 impr, pos 3.95 in June) hasn't been assessed against click evidence yet. Precheck script: `tag_phase1_precheck.md` (read-only).
- **Area slug migration** `310cc5a`: `PopulateAreaSlugs.php` + `2026_07_26_000000_add_slug_to_areas.php` never uploaded; production `areas` has no slug column; live code doesn't use it. Aug 25: `findAreaByName()` substring `LIKE` still sent **175 of 1,892 areas (9.2%)** to the wrong area (`hino` → Musashino, `ito` → Taito); 21 exact collisions (mino, konan, miyoshi). Next: `PopulateAreaSlugs --dry-run`, review the 21 collisions, diff against the snapshot (`/tmp/area-slug-snapshot.txt` on prod — may be gone). Check what the Jul 26 `d26950c` substring fix already covers first.
- **Vietnamese expansion (plan from Jul 24 `vietnamese-multilingual-findings.md`; nothing deployed).** Fixed order, nothing skips ahead:
  1. **Gate:** scope the publish uniqueness check to `(apply_url, lang_id)` — today an EN and VI version of one source job can't both be published ("already published as Job #…"). Blocks everything else.
  2. Dedup `lang_id` filter on all 3 levels + the apply-URL match (`JobDeduplicator.php`) — otherwise every VI import flags as a duplicate.
  3. Importer: make XML `<lang_id>` authoritative (`(int) ($xml->lang_id ?? $langId)`, ~l.93 — today the admin dropdown wins); dropzone path hardcodes `lang_id = 1` (~l.217).
  4. VI template fixes: English disclaimer, raw Japanese boilerplate, JLPT in title from category instead of `japanese_level` (check whether Oct 5 `benefits_label` fix already covers "Phúc lợi").
  5. Prerequisite checks: middleware order in `bootstrap/app.php` (the host middleware must run **after** `StartSession`); `SELECT slug FROM prefectures WHERE slug LIKE '%-vi'`; absolute-URL / form-action audit of views; `SESSION_DOMAIN`; `.htaccess` www-rule scope. Already answered (Jul 23): homepage has no POST forms or CSRF meta; POST forms (`/contact`, `/subscribe`, login, admin) are on uncached routes; the search form is GET with an **absolute** action `https://nihonarubaito.com/jobs/search` — needs fixing for vi. in the URL audit.
  6. Build **`vi.nihonarubaito.com`**: same docroot, ~10-line host-forcing middleware, 301 `lang_id != 1` job URLs off the main domain (incl. Tier 2 redirect target), noindex the vi. host, exclude `lang_id != 1` from sitemap. `/lang/{id}` 301 → 302.
  Set the language in middleware via the session (read in ~25 controller places), not a request attribute. Restore job 156689's `apply_url`.
  7. Pilot: 30–50 translated jobs (packing/restaurant; Aichi, Osaka, Saitama, Tokyo) via Facebook groups, own AdSense channel + GA4 stream; judge on VC apply clicks over 2–4 weeks.
  DB (Jul): 107,572 EN · 7,779 VI (pipeline stopped Apr 2025; 1 published test) · ZH 5 · JA 3. VI job detail pages already cache correctly (unique slugs). `resources/lang/vietnamese/content.php` at parity with English. Vietnamese XML spec v2 written (`vietnamese-xml-spec.md`; full-depth XML, Facebook-format titles; 2 test translations) — not yet turned into translator-project instructions.
- **Kanagawa prefecture content:** do not deploy or modify (decision 2026-07-16).
- **Homepage CLS ~1.061** (field 0.15): async stylesheet in shared layout. Dedicated measured session only.
- **FrontendComposer English override** + hidden `/lang/3`, `/lang/4` links still in place; removal waits on language-path verification. Before removing: run the cookie-jar test (`/lang/4` → homepage must stay uncached) — the `ApplyCacheHeaders` guard has never been exercised.

### Analysis gaps (not measured yet)
- Listing → detail click-through rate (GA4, Japan) — the step where the −72% sits
- **Landing-page attribution for applies:** every VC referrer is a detail page, so no apply can be traced to the SEO page that brought the visitor. Proposed: server-side apply-click log (job_id, timestamp, session ID, session entry page), joinable to VC on job_id + timestamp
- シゴトin **commission rate per 商品グループ** — only in the VC console (プログラム詳細); needed to price the Feb 3 re-code and any finding in yen
- Facebook insights: confirm the export timezone in Business Suite before using any hour-of-day finding; follower trend and the non-Kanto pages' exports not yet pulled
- Time-to-index for new job pages (publish date vs GSC first-indexed, ~50 URLs)
- Kansai/Kyushu/Tohoku collapse: sourcing or ranking? Needs listings-published per prefecture per month vs converting jobs
- Cause of the 2025-11-01 FB cutoff (needs Meta-side history)
- Fukuoka social distribution (Hakata grew 3→219 applies/month, zero social coverage) — deferred pending Kanto stabilisation
- AdSense page views stepped up permanently after 2026-08-19 (~4,400 → ~6,400/day) while GSC clicks fell — source unidentified
- GA4: Facebook visitors spend ~9 s on job detail pages vs 76 s for Google organic, and almost never reach listing pages; AI-assistant channel 1,012 sessions at 113 s; `(not set)` medium 63 s — possible webview tracking issue
- Job template shows duplicated location string ("Prefecture: Kita-ku Osaka Osaka")
- No yen/commission column in any VC export — all affiliate findings are counts

### Content & product (ongoing)
- Prefecture content: Tokyo, Osaka, **Hokkaido** done (Hokkaido Jul 2026); Fukuoka already at standard. ~44 remaining, Ibaraki next — read the live HTML first. Osaka's July lift (+23.5%) matched untouched Tokyo/Kanagawa (+16–22%), so it isn't content-attributable; judge upgrades against the prefecture class, not their own past
- **Osaka rewrite ready, not deployed:** `osaka-prefecture-content-final.html` (fact-checked; fixes the Employment Service Center location — Hankyu Grand Building 16F, Umeda, not Temmabashi; new FAQ #1; frozen stats like "7,328 applications" removed, never verified). Before deploy: live-check 5 station links (namba, shinsaibashi, umeda, osaka, tennoji), confirm no duplicate "Chuo-ku Osaka" row (the Feb dump had two — probably the area 121 row renamed to Miyakojima-ku in July), and **re-verify wages against the Oct 2026 revision**. Deploy via the `/update-prefecture-content` command (install not confirmed).
- **47-prefecture rollout plan (not started):** tiers by live inventory — A ~9 full pages (8–11k chars, 50+ listings: Aichi, Saitama, Chiba, Hyogo, Fukuoka, Kyoto, Shizuoka, Hokkaido, Kanagawa refresh), B ~12 factory-belt pages (4–6k, 15+), C ~26 compact routing pages (2–3k, 5+). Tier changes only after a full quarter past a threshold. 2–3 pages/week, mixed regions, never a bulk backlog. ⚠️ "Kanagawa refresh" conflicts with the Jul 16 Kanagawa freeze (parked) and Hokkaido was already rewritten in July — confirm before scheduling. First Tier A research pack (Aichi or Fukuoka) on offer.
- Writing guides from that chat: `prefecture-human-voice-guide.md` (voice rules, ban list, "what Google does NOT like"), `a-plus-content-checklist-v2.md` (writer / publish gate / calendar + grep block — canonical; the v2.1 patch was reviewed but its Patch 3 SQL targets a nonexistent table and Patch 5 would 301 location pages, so it wasn't merged). Not yet done: Phase 7 amendment (Japan-filtered engagement rate + time), a quarterly corpus-health item, the anchor-duplication audit script, `~/sources/` source-sheet folder
- **Competitive gaps** (vs YOLO Japan, WORK JAPAN, GaijinPot, WeXpats …): no guide/blog layer (biggest gap); job attributes not exposed as filters ("No Japanese OK", "No CV OK"); fewer languages; weak brand/E-E-A-T. Uncontested: the hand-cash niche. Unique asset: own listings DB as wage data
- **FAQ rich results:** restricted to government/health sites since 2023 and not shown for this site from 2026-05-07. Keep the `<h3>`+`<p>` FAQ structure for readers, translation and the extractor, but invest nothing more for SERPs
- **Visa wording fix (legal error, unverified whether done since Jul):** Fukuoka page says "40 hours per week during breaks" — the rule (入管法施行規則 19条5項1号) is 28h/week, 8h/day during the school's long holidays; 40h/week is the Labour Standards Act ceiling. Fix to "rising to 8 hours per day during official school holidays (within the standard 40-hour weekly ceiling)", then grep all ~12 substantial posts for "40 hours per week" (precheck → replace → recount). July planned an extra model cross-check first
- Hand-cash FAQ: check whether `/hand-cash-jobs-in-japan` has a `blog_posts` row with an FAQ block; if yes, add one weekend FAQ from the drawered weekend draft
- Watch-list ideas from Jul (re-check on current data before acting): konbini brand queries rank ~3.5 vs 1.0–1.2 for other terms; "arubaito meaning" blog post (1,519 impr, pos 7.3)
- Admin blog-posts DataTable shows no rows; XML `img_link` null crash; image library rollout; blog section
- Image library gaps: **zero `food-delivery-staff` images**; 2 untagged files left in `New_Images/` (`grok-443f5c11…`, `grok-b3cacfc5…`) for the next batch
- Vietnamese expansion — see the Vietnamese block under *Blocked / parked*
- Tier-3 410 gaps: ~179 numeric + 51 legacy CI3 URLs return 404 instead of 410. Old numeric IDs (~54,574–125,619) and CI3 hash-ID URLs (e.g. `vj7Uo`) fall outside the tier lifecycle entirely — a catch-all 410 rule for them was proposed (low priority)
- Sourcing priorities: hand-cash, bed-making, convenience-store chains, high-yield restaurant types; underserved Ikebukuro, Nishi-Funabashi, Utsunomiya, Sannomiya, Chiba (see P1c density list)
- **Job-collection automation (not started):** a scraper → Claude Haiku API → dedup → DB insert → admin review pipeline is documented from an earlier session; the bookmarklet workflow (`bulk-job-processing-workflow.md`) is the stop-gap. **Constraint:** shigotoin.com returns 403 to the SiteGround server IP (35.213.165.28), so any tool must fetch from elsewhere. Agent Reach was raised as a candidate — untested; it must (1) return the full Japanese page text, (2) get past shigotoin's WAF, (3) handle JS-rendered pages. Type, proxy options and pricing unknown

---

## 4. Watching ⏳

| Check on | What | Expected |
|---|---|---|
| 2026-10-14 | GA4 Explore → metrics "publisher" | Publisher ad clicks/impressions/Total ad revenue appear; if not → test delayed GA4 load |
| Weekly → 2026-12-03 | AdSense "Ad intents" experiment (Oct 10, 40% users, auto-apply) | +31% after 2 days = too early. Judge by RPM not CPC |
| 2026-10-26 → 11-02 | GSC Job Postings valid items | 8.17K → ~6K. **Intended** |
| 2026-11-09 | GSC Search appearance → Job listing clicks | Hold (~308/3mo). Sharp drop → review before reverting |
| After "Validate fix" | GSC Soft 404 count | Falls from 294 |
| Ongoing | SiteGround #5141798 | Storage-layer fix; cron/CLI `.env` failures stop |
| Ongoing | MissingAppKeyException count | Stays 0 |
| Ongoing | `laravel.log` unresolvable `area_id` | Surfaces feed faults |
| Ongoing | Field CWV (28-day) | Aug 24: INP 227 ms fail (target <200), CLS 0.17 fail (target <0.10) — both ad-driven; LCP/FCP/TTFB pass |
| Overdue (6 weeks from Jul 26) | Ranking prefecture-modifier pages | 54 → 100+ means the sitemap additions worked (count of ranking pages, not clicks) |
| Dec 2026 → Feb 2027 | Seasonal trough | Clicks bottom ~280–300/day in Dec (if 2025 repeats; 2026 runs ~27% above 2025), turn up in Feb |
| Ongoing | `src=x` in FB post URLs | A new apply-link host not yet mapped in `getJobSource()` |
| Monthly | GSC Manual actions + Security issues + Links | Clean; seoexpress spam ignored. WordPress `/blog` (the old security weak point) being deleted Oct 13 |
| Monthly | VC duplication | Exact-postback ratio 1.00–1.04 (>1.10 = regressed); cookie-replay share (same click + offer) from ~21% of rows in H1 — falls only if VC fixes it |
| Monthly | FB slug-less referrer share | 0%; bare `/jobs/{id}/detail` referrers = post composer regressed |
| Monthly | Conversions per 1,000 GSC clicks | Up from 15.76 (Aug 2026) — the recovery metric |
| Monthly | Inventory penetration · jobs/station · converting-listing age | Up from 5.5% · 1.17–1.32 · down from 4.3 days |
| 2026-10-30 → 11-08 | The 65 シゴトin Aug 1–10 pending conversions reach their 90-day deadline (some may resolve earlier — a fresh export can check now) | ~90% approval overall, ~98–99% on clean same-day rows, 100% on FB rows |
| Monthly | GRP03 share of approved; effective rate after the Feb 3 re-code | Meaningful once the rate table is in hand |
| Next FB insights pull | Approved FB conversions/day (~1.6) and link clicks/day (10.2 on Aug 9) | The baseline for any reach change |
| Next VC export | August 2026 保留 rows (174 as of Sep 4) | Resolve to approved; Aug approval in 92–99% band |
| **Now → Nov 2026** | Oct 2026 minimum-wage revision | Every wage figure on content pages cites the 2025 revision; update pages where the new figures differ |
| Monthly | git-vs-production drift check (SETUP.md §4) | Only documented differences |
| Overdue (from Jul 23) | `stepstoneCrawlBot` hits after robots block | Falling from 14,731/30d; still climbing → `.htaccess` UA block |
| Overdue (from Jul 23) | Crawl volume on `/jobs/search`, `/jobs/areas/get` | Drops after disallow |
| Next Wednesday spike | Chrome-Lighthouse hits in server logs (keep ~1–2 weeks) | Should be 0/day once `sg-cachepress` Speed Test is off; a spike = still active |
| Ongoing | AliyunSecBot (8.217.x / 8.210.x) | Hit Jul 30 (AdSense invalid-traffic day), gone Aug 2 — watch for return |

---

## 5. Decisions (permanent unless reversed)

**SEO / indexing**
- **JobPosting only on single-job detail pages** (`a5ae50c`, 2026-10-12). Rich Results Test "valid" = syntax, not policy.
- **Noindex empty listing pages — three guards, all required** (`ListingController.php:354`, mirrored :475): (1) `$totalRows === 0`; (2) no editorial content (`strlen(strip_tags($blogPost->post)) > 500` exempts); (3) never a prefecture page — the 47 are never deindexed (a transient query failure on Tokyo would otherwise emit noindex and get cached 30 min). A noindex response drops `cache_max_age`. Search-results pages always `noindex, follow`.
- **Legacy `/laravel/public/index.php/*` → 301** in two layers (`.htaccess` + `RedirectLeakedPaths.php`).
- **Expired tiers (code is truth):** 0–90d 200+noindex · 91–365d 301 to area page · 365d+ 410.
- **Sitemap:** modifier pages only for prefectures with live jobs (avoids "submitted URL marked noindex"). Stations = hardcoded 40-slug allowlist. Served from `storage/app/sitemap.xml` (24h cache) — **`rm -f storage/app/sitemap.xml` before `sitemap:generate`** or it's a no-op.
- Every area deletion needs explicit 301 overrides (fallback = lowest-id surviving area in same `town_id`, not a similarity match).
- Multilingual renames must touch all 5 language columns.
- **Weekend jobs — NO-BUILD** (first decided 2026-07-21/22, re-confirmed Oct 12). "weekend jobs" 329,831 impressions → 13 clicks; "night shift jobs" 127,521 → 3 clicks (GSC, 3mo to Oct 12). Converting element is hand-cash (July: night-shift alone ≈10 clicks/28d, night-shift + hand-cash ≈90). `weekend` / `night-shift` tags = **filter-only, no landing pages**. Reopen only if "weekend + hand cash" queries start getting impressions. Drafted weekend content is drawered (`weekend-jobs-content.html`, md5 `7d4c3010…`).
- **Omission over invention:** never fabricate data to fill a schema field — no invented wage ceilings (a floor is emitted as `value`), no baseSalary for unparseable wages, no guessed postal codes. Missing = omit the key, never an empty string.
- **Postal codes:** only ken_all-sourced, stored hyphenated `XXX-XXXX` in VARCHAR(8), emitted verbatim.
- **Legal, compliance and wage figures are verified at the government primary source before publishing** (caught 5 invented wages, a 28h-rule drafting error and the 40h/week break error). 28-hour rule = any rolling 7-day period ≤ 28h; 8h/day only during the school's official long holidays, for enrolled students. Holiday premium 35% (MHLW Art. 37), night premium 25% after 22:00.
- **Verified minimum wages (Jul 2026, primary sources):** Tokyo ¥1,226 · Kanagawa 1,225 · Osaka 1,177 · Saitama 1,141 · Aichi 1,140 · Chiba 1,140 · Kyoto 1,122 (eff. Nov 21) · Hyogo 1,116 · Shizuoka 1,097 (Nov 1) · Hiroshima 1,085 (Nov 1) · Fukuoka 1,057 · Miyagi 1,038. (Project instructions' "Osaka… ¥1,100+" is imprecise.) These are the **Oct–Nov 2025** revision. ⚠️ **The 2026 revision takes effect Oct–Nov 2026 (now)** — re-verify every page's figures within 4 weeks of publication, Osaka first (before its deploy).
- **Measuring-week discipline:** no new content pushes while shipped changes await their export reading.
- **Content rules (permanent):** no competitor names (baitoru, townwork, shigotoin); never claim "no Japanese needed"; no hiring promises; no invented anecdotes or statistics; "provides job listings in English", never "translates"; kanji + romaji for key terms; FAQ answers only new information; one dynamic DB fact per page rendered live, nothing weekly-changing hardcoded; `dateModified` changes only on real edits. Template = ingredient checklist, not a fixed skeleton (wages + main-areas sections stay in predictable places). Visa rules: condensed sections until a cornerstone visa guide exists, then link-only. Banned phrases list grows (any phrase on 5+ pages); a fresh model instance that didn't write the batch reviews it. Source sheets live outside the webroot (`~/sources/{prefecture}-{date}.md`). AI category thumbnails are fine; alt text describes the category, never claims a place.
- **Never 301 or remove prefecture/area location pages** — they're listing infrastructure and the Tier 2 redirect targets. Consolidation applies only to editorial blocks. (Exception: defunct municipalities merged with explicit 301s, as in Part G.)
- **No hreflang EN↔VI while the Vietnamese pages are noindexed.**
- **Publishing cadence:** max 3–4 location pages/week, mixed tiers and regions. During a confirmed core-update rollout, hold sitewide template changes but keep publishing individual pages at normal cadence.
- Branding: `og:site_name` = "Nihon Arubaito"; footer "NihonArubaito.com" stays.
- **Tags:** hand-cash & daily-payment never get tag landing pages. Multi-tag URL combos whitelisted only. Never rename existing indexed `/part-time-jobs-in-*` URLs.
- **Listing-quality defence = facts-per-page accuracy**, not prose style. AI authorship isn't a Google signal; value-add (translation across a literacy barrier, structured facts) is. Unreviewed machine translation at scale is the policy-named violation shape.
- **Translation engine:** keep Claude for the job-translation pipeline unless a tripwire fires — Google/AdSense treats AI provenance as a ranking or monetisation factor, YoY clicks break trend in a window Google ties to AI-provenance enforcement, or a concrete mechanism links an AI-text detector to search/ads outcomes (proposed by the assistant in a15d8696; user's final call not visible). ⚠️ **Conflicting claims on Claude watermarking:** a15d8696 (Aug 26, web search) reported Claude models launched from Aug 2 2026 watermark text; the Aug 30–31 chat said no Claude watermark (training knowledge, unverified past Jan 2026). Unwatermarked fallbacks named: DeepL API (glossary can lock 手渡し → "Hand Cash (手渡し tewatashi)"), Google Cloud Translation, OpenAI GPT, self-hosted open models.
- **Vietnamese expansion: human-reviewed pilot only**, never unreviewed MT of the corpus.
- ⚠️ **Multilingual architecture — conflicting records, owner to confirm.** Jul 24: `vi.` subdomain. **Aug 25 (newer): `/ja/` and `/vi/` path prefixes, English stays at root**, prompted by a Japanese job site asking for Japanese content (open questions: does the partner supply Japanese listings or expect translation; separate inventory or translations?). Both agree on: English URLs never move; session-based language on shared URLs is unsafe behind the CDN (proven by the Aug 12 outage). Jul 24 details if subdomain: (chosen Jul 24 over session bypass, `-vi` slugs and `/vi/` prefix): per-host CDN cache keys, zero English route changes. **Hostname is the single source of truth for language** (main forces `user_lang = 1`, vi. forces 3). vi. is **noindexed** — VI SEO is a non-goal (traffic = Facebook groups); losing today's indexed VI detail pages is accepted. If `-vi` slugs are ever used, session bypass must sit underneath. `Vary: Cookie` is ruled out (encrypted per-user session cookies → ~0% hit rate). Romaji in `areas.vietnamese` is correct, not a defect.
- **Vietnamese rules:** no new POST forms on cached vi. pages (stale CSRF token → 419). Never post `/lang/3` or shared-URL links on Facebook before the host guard ships. **English rollback rule:** any change in English caching (HIT, ~90 job links, byte count vs pre-deploy snapshot) → immediate rollback. Pilot kill criterion: clicks without applies → fix job selection/trust, not architecture. A `/vi/`-scale build only if funded by proven Vietnamese revenue. After launch, check `ads.txt` is accepted for the vi. subdomain in AdSense.
- **Google update rollouts:** the Feb "no changes during rollout" freeze was lifted 2026-08-21. **One change at a time** is kept so any effect can be attributed.
- **Every deploy = md5 verify + git commit + ledger entry**, one gate (an uncommitted changeset caused the Aug 28 incident). Never fix server files without syncing the same fix to the repo/staging. Never wholesale-restore a server backup (DB loses days) — file-level only, snapshot first.
- **Spam backlinks:** no disavow unless a manual action or a correlated click drop. A ready `disavow.txt` (127 domains: 73 seoexpress, 41 `.shop`, 13 other; June) is kept locally for defensive use only.
- **Images:** new images are WebP at **476×317** (2× the ~237×155 slot for retina mobile — ignore Lighthouse's 238px advice); legacy JPGs stay JPG (SEO preservation, June). An Aug proposal to convert the 241 legacy JPG/PNG (2018 cohort, mostly on expired jobs) is parked as its own session — first count how many *published* jobs still use `.jpg`. Judge image work by rendered page weight, not directory size.
- **Content length:** never write to a character-count target — padding is the thin-content pattern.

**Measurement**
- **GSC: track clicks only.** Impression logging error May 2025–Apr 2026 voids impression/CTR comparisons across it; never compare across Jul 16–17, 2026 (Google dropped ~73% of impressions — mostly junk desktop impressions at pos 3–4 converting at 0.29% — with clicks flat; position and CTR shifted compositionally, not from ranking. The job-listing rich-result surface shrank ~97% the same days; new baseline ~150 impr/day at pos ~1.5).
- **No-touch:** homepage and top-ranking pages (hand-cash, Tokyo) — title/meta CTR tests (P3) are the only planned exception, one at a time.
- **Don't chase "part time jobs near me".** 28-day: desktop 107,857 impr at 0.016% CTR vs mobile 1,939 at 11.1% — ~98% desktop phantom, 100% Japan. Qualified variants already convert ("restaurant part time jobs near me" 13.7%).
- **Don't change the homepage or rankings in response to impression swings.** The junk desktop impression pool switches on and off (off Jul 16–21, back Jul 22; homepage desktop impressions −74% while clicks rose and "nihon arubaito" held #1). One tail query, "part time jobs near me", was ~25% of sitewide impressions at 0.28% CTR.
- **Build Sep–Feb, harvest Apr–Jun.** Summer decline is normal. Compare YoY before diagnosing. *(This applies to **search clicks** — GSC peaks ~May. **Affiliate conversions** peak in **January**; the "May peak" in conversions was a duplication artifact.)*
- **ValueCommerce: never compare 2026 to raw 2025 rows for Feb–Sep.** Deflate first: Feb 1.21, Mar 1.39, Apr 1.70, May 1.97, Jun 1.72, Jul 1.78, Aug 1.75, Sep 1.30. Jan, Oct–Dec 2025 and all 2026 are clean. **Jan 2025 and Oct 2025 are the clean benchmark months.** Dedup key: (click_at, order_at, referrer, dest_url). CSVs are cp932.
- **Never use VC 注文時のデバイス / 注文時のOS / 注文個数** (constants from the server postback). Use クリック時のブラウザー・アプリ, but note it's ~31% blank and the blanks are disproportionately Facebook in-app traffic.
- **VC exports: check ステータス first and read the real date range from the timestamps, not the filename.** A pending-only (保留) export silently covers only the last ~90 days (`affTxReportCustom_260401to260810.csv` held Aug 1–10 only). Check: 承認期限 + days since 注文日 = 90 on every row → pending-only. A Google Sheets export named `Untitled spreadsheet - …` contains only the active tab.
- **Don't shorten `delete_at` / expiry windows** without re-checking conversion age — older listings carry ~a third of applies.
- **No advance-payment (前払い) landing page** — 31 approved conversions in 6 months; a thin modifier page isn't worth the scaled-content risk.
- **Conversions per converting listing is a supply-gap ranking, not a conversion rate** (the denominator leaves out listings that never converted).
- **Never judge prefecture/area/cluster pages on affiliate conversions** — 100% of conversions start on detail pages. Judge them on visitors sent to detail pages.
- **Wage is not a conversion variable** (1.44–1.76 applies/job across all bands). **Remove the FB post-scoring wage bonus** (+1 at ≥¥1,300, +2 at ≥¥1,500) — no support in 2 years of data.
- **Daily payment is a traffic term, not a conversion term** (1.49 vs 1.52 applies/job; 156 search clicks vs hand-cash 13,234). **Hand-cash is the conversion term** (2.55 vs 1.50).
- **A single job is worth 1–2 applies** (median 1, p90 3, max 19). No single job/boost moves a month — breadth and depth do.
- Applies-per-job must be read with station job count (1–2 jobs at a station → ~1 apply each by construction).
- **Judge AdSense by Page RPM**, not CPC (they move opposite; 250+ CPC & 200+ clicks together = 1 day in 103).
- **Exclude Jul 15, Aug 12, Sep 23** from AdSense/GA4 trends (PageSpeed sweep days).
- **GA4 decisions filtered to Country = Japan**, engagement read as rate **and** time together. **Measurement chain:** ValueCommerce applies → GA4 listing clicks → GSC clicks; impressions/position are context only, in clean windows.
- Don't block Google proxy IPs; don't skip AdSense for Lighthouse (cloaking + fake PSI scores). **Leave AdSense Auto ads ON** (not the Lighthouse source). Fix Lighthouse at the source (`sg-cachepress` Speed Test), never in ad-serving code.
- Judge the site on **mobile clicks**, not impressions — mobile = 8 of 9 clicks at ~9–11% CTR; desktop runs 0.016–0.40% CTR.

**Engineering**
- **Cache/CSRF:** `ApplyCacheHeaders` (prepended → runs last on response) caches by stripping Set-Cookie (SiteGround nginx won't cache with it). Three early returns keep Set-Cookie to stay uncacheable: HTTP ≥ 400, non-English session (`user_lang !== 1`), `skip_cache_empty`. `bootstrap/app.php:17` exempts 15 AJAX endpoints from CSRF (`bc1d651`).
- **Importer, not Claude, owns ID lookups:** translators' Claude must copy the full Japanese address exactly; prefecture/area IDs are resolved/validated server-side from the address (P1a). Never leave `<prefecture_id>` blank.
- **Every new table migration sets `utf8mb4` / `utf8mb4_unicode_ci` explicitly** (DB default suspected latin1).
- **Always read the DB name from `.env`, never hardcode it** (past mistake: assuming `gaijinbaito`): `DB=$(grep -E "^DB_DATABASE=" .env | cut -d= -f2- | tr -d '"' | tr -d "'" | xargs)`.
- **Precheck first for every production change:** read-only diagnostic → report back → generate changes tuned to the real environment → execute with verification steps.
- **Evidence before ✓:** no terminal claim is accepted without raw output; summaries never clear a gate; evidence steps go physically first in a command and are marked blocking. One phase per instruction ("ONLY … then STOP"). No gate inside a single atomic tinker run.
- **Conservation laws for migrations / parser changes:** freeze a snapshot, compute distinct-count targets before running, and any bucket off by one = a leak.
- **Deploy has three proofs:** the change is right (review) · the file deployed (md5) · the runtime serves it (origin-render curl).
- **INSERTs specimen-first** (read schema + example rows before writing values); run collision checks in slug space, not kanji space; freeze English strings before insert.
- **Every DB command shows its SSH target** (Jul 21 near-miss: Part G Step A almost ran against local XAMPP).
- Docs/comments are updated in the same commit as the behaviour they describe.
- **Never give a route carrying a POST form a `cache_max_age`** — cached pages share one CSRF token (419s / token harvesting). Never add `Vary: Cookie`.
- **`optimize:clear` must always be followed by `config:cache`** (otherwise config caching silently reverts; caused MissingAppKeyException in July).
- **XML comments must never contain `--`** — `simplexml_load_string()` rejects the file; the translator's `AREA_ID_UNCERTAIN` notes must use a full stop or single hyphen.
- **Fix the writer before backfilling:** restore the code that writes a field and verify one new row is correct *before* backfilling old rows, otherwise new bad rows keep landing behind the backfill.
- **Deploy discipline (SETUP.md):** GitHub = source of truth; pull first; commit + push before deploy; verify by md5; after any `config/` or `.env` change run `php artisan config:cache` and verify the generated file with `php -r`, not through the app.
- **Config cache trap:** while cached, `.env`/`config/` changes do nothing until rebuilt.
- **Never run production DB work against a local database.**
- **Never verify site state in a normal browser session** — your own session carries `user_lang`, so a poisoned page looks fine to you. Private window or curl only.
- **Back up before any destructive step** (`cp` before `rm`, tar before batch image edits, mysqldump before schema changes). **Archive, don't delete** operational tables (`fb_posts` → `fb_posts_archive`).
- **Store derived values that power repeated queries; keep attribution values in the URL** (`prefecture_id` stored on `fb_posts`; `src=` stays in the link for GA4).
- **Don't relax the SiteGround WAF** for diagnostics, and **don't run undocumented production commands** (`sg-cachemanager flush` declined) — purge via Site Tools.
- Keep the Japanese wage format `1250円〜1350円` in titles (the title transform protects it).
- **Purge SiteGround Dynamic Cache** (Site Tools → Speed → Caching) after deploys; `artisan cache:clear` doesn't touch it.

**Marketing**
- **FB posting caps:** max 8 posts/page/day, ≥45 min apart, one URL per post, hold cadence flat when a post goes viral.
- **FB timing: morning, 06:00–14:00** (apply clicks index 1.4–2.8× organic in both 2025 and 2026; 15:00–23:00 index ~0.4–0.7). ⚠️ The daily queue command used **evening-weighted** slots — **switch to morning-weighted.** Jul–Aug *reach* data (timezone unconfirmed) showed posts at 00–02 got the most reach and 13–18 the least, with almost no posts in the morning — confirm the timezone, then test morning. **Do not move posting to Sat–Mon** (H1 shows no weekday effect at n=1,260; the Aug 55% figure was noise). ⚠️ Two other datasets disagree on hour: Aug 25 found 21:00–23:00 best for **clicks per post** on all three pages (13:00–17:00 worst), and H1 reach peaked 00–02. The morning rule measures **applies**, which is the money metric — but this needs one clean test.
- **FB pages (Aug):** the original page was banned (reportedly for mass posting); three replacements — Osaka (best: 124.5 reach/post, 2.47 clicks/post, 99.9% on-region), Tokyo (63.8 / 1.13; posting a rigid 24/day, 58% off-region jobs), Kanto (27.1 / 0.65; 92% Tokyo jobs despite its name). All three lost 57–71% reach/post in Feb 2026 at once (platform limits — only 9 hides in 8 months). Title modifier: Hand Cash 2.48 clicks/post vs Daily Payment 1.07. Link posts beat photos (Osaka 3.41 vs 1.62). Proposed, not done: Tokyo page Tokyo-only, Kanto page takes the other Kanto prefectures, lead with hand-cash, link posts, cut Tokyo volume. Page link clicks (~69/day) are well below GA4 FB referrals → much FB traffic comes from group posts. Jan–Aug 2026 organic (11,854 posts, ~52/day, 93% photos, median reach 62): Kanto shared 1,711 headlines with Tokyo (72–86% of its posts) — the likely reason it's weakest. Best stations by organic clicks/post: Namba 2.57, Ikebukuro 2.50, Ueno 2.36, Shinjuku 2.34, Osaka 2.11, Kashiwa 2.09; weakest: Toyosu 0.81, Kawasaki 0.91, Ginza 1.07, Kyoto 1.10, Sannomiya 1.17, Hiroo 1.19. Bed-making 2.28 clicks/post; higher wage → more FB clicks (0.67 below ¥1,100, ~1.40 at ¥1,400+). 33% of new jobs (Aug sample) go to prefectures with no FB page (Fukuoka, Aichi, Miyagi). Proposed owned channels: LINE official account, email job alerts; a Fukuoka/Aichi page deferred until Kanto recovers.
- **Applies vs affiliate (user's decision, Aug):** *all* applies (affiliate + email) drive sourcing, organic posting and SEO; *affiliate* applies only gate paid boosts. Non-affiliate jobs are posted deliberately to build ranking data — the single affiliate partner doesn't supply enough jobs.
- **FB post images (Oct 13, proposed, not started):** don't use image-generation models for anything with exact text — wages, station kanji and shift times get corrupted. Current graphics are one template with swapped fields (1080×1350 and 1920×1080), so render them: Blade template → PNG via Spatie Browsershot, fed from `jobs`, an artisan command (e.g. `social:generate --count=N`) for batches. Alternatives: Canva Bulk Create, Bannerbear, Placid, Templated.io. Prefer category-generic photos over employer logos/storefronts (the 7-Eleven logo on our paid-reach graphics is trademark exposure, unverified). ⚠️ The Seven-Eleven photo used on a Kyoto post shows **Korean signage** — check the library for other mis-sourced photos.
  - Second chat (Oct 13): ⚠️ **one AI-made post (Theme Park Cleaning, Maihama) shows Disney characters and "Fantasy Land" signage** — implies Disney is the employer; replace or remove it. It also carries claims ("training provided", "daily/weekly pay") that may not be in the listing. Three of six samples share the current template (red diagonal band, circular photo, "Apply Now" pill).
  - Proposed build (not confirmed): `resources/views/social/job-card.blade.php` + routes `/social/job/{job_no}.png` and `/og/job/{job_no}.png`; set `og:image`/`twitter:image` on detail pages; "Download FB image" button in admin; published jobs only. Sizes 1080×1350, 1080×1080, 1080×1920, 1200×630. Category photo library at `public/images/social/{category_slug}/` (3–5 per category, picked by job_no so a job always gets the same photo). **Blocker:** does Browsershot (headless Chrome) run on SiteGround? (unverified)
  - Proposed rules: the image model never renders text — all text is overlaid from job data; show only fields that exist in the job data; no real logos, mascots or characters. Proposed brand spec: #A3151C / #D32F2F / #F5F5F5 / #111111, Montserrat ExtraBold + Noto Sans JP, body ≥28 px at 1080 wide, 60 px margins.
  - Side note: a sample Fukuoka post shows ¥1,057 — exactly the 2025 Fukuoka minimum. Listings at the old minimum may fall below it once the Oct 2026 revision takes effect.
- FB reach ("Nihon Arubaito Kanto" page, Jul 13–Aug 9): ~400 people/day regardless of volume; more posts split the same reach (reach/post vs posts/day r = −0.66). 414 posts in 28 days ≈ 15/day, up to 26/day — **far above the 8/day cap**. Link posts beat photo posts on reach by +15–39% once hour is controlled (only 33 link posts — retest at ~150); same click-through. Repeating titles costs no reach — don't rewrite for variety. FB mix lacked cleaning and bed-making; the page is Kanto-only (Kansai/Chubu use another page).
- **FB: post same-day listings only** (94% of FB conversions come from jobs < ~2.5 days old; 70% < ~15 h).
- **Boost criteria:** affiliate job only, ≥10 days to expiry, payment hook or bed-making, ≥3 affiliate applies for station × category, not on weak-station list; LPV objective, link posts, ¥200–300/day, fixed 7-day end. Plus (proposed Aug): never boost Photo posts, kill above ¥20/visitor at the 48 h check, never raise budget mid-run, weekly sweep for campaigns with no end date. Benchmarks: record Tokyo Tochomae bed-making URL promotion ¥7.9/visitor (job 156835, Aug 18) — but only 1 affiliate apply from 171 visitors; link posts ¥12.9/visitor vs photos ¥37.9; landing-page-view cost by page Osaka ¥12.5 · Kanto ¥14.9 · Tokyo ¥34.2; 9 of the 12 cheapest campaigns were bed-making; 61% of all-time boost spend ran with **no end date**. Boost value shows up in affiliate applies, never in AdSense. Kanto three-week test proposed: exclusive territory at 6 posts/day, success = reach/post 27 → 60+.

---

## 6. Instrument lessons

1. A negative from an unvalidated instrument is **not** evidence — run a positive control first.
2. Bare curl → 403 (site blocks no-UA). Always send a browser UA.
3. **Redirect checks: never `curl -L`** (it measured listing pages instead of expired pages on Oct 12). Print HTTP code + byte size; detail ~40–70KB, listing ~115–170KB. (To grep a page's content behind a 301, follow it deliberately — two different jobs.)
4. `http://127.0.0.1` 301s to HTTPS with empty body → `curl --resolve host:443:127.0.0.1`.
5. Pretty-printed JSON defeats `"key":"value"` greps — grep the bare key.
6. **Single-file `grep pattern file` silently returns nothing on the Mac**; `grep -r` works; Python gives true counts.
7. **Log files rotate ~04:30 UTC** (so a file named for a date holds mostly the *previous* day) — `nihonarubaito.com-2026-09-24.gz` covers Sep 23 04:30 → Sep 24 04:30. Grep by date string, not filename. GSC days are US Pacific.
8. A cache can lie about itself — read built config with `php -r`, never via the app. Opcache disabled: md5 proves the file deployed, not that runtime loaded it.
9. zsh doesn't word-split unquoted vars — `for f in $FILES` passed one giant filename; deploy loop silently did nothing. Use explicit lists.
10. `*/` inside a docblock path (`lang/*/content.php`) closes the comment and breaks the file.
11. The server can't see whether visitors can reach the site (inside SiteGround's network) — made the Sep 17 CDN outage look local.
12. `php artisan migrate` success ≠ correct collation — verify with `SHOW TABLE STATUS`.
13. Backlink tool "SPAM"/toxicity ≠ harm. Trust GSC Manual actions and real click changes. (June: Ahrefs showed 127 spam domains; GSC showed 51 external links, all from real sites such as reddit, japanlivingguide, expat.com — Google had already filtered the spam.)
14. Heuristics over-flag: `secondary_applies` "suspect rows" were 23 email typos, not corruption.
15. GSC clicks vs GA4 organic sessions never match (delayed tag, ad blockers, Yahoo Japan in GA4 organic, time zones).
16. **Raw VC rows ≠ conversions for Feb–Sep 2025** (up to 1.97× inflated). Any seasonal model or target from that window is distorted, worst in May.
17. The 2025 H1→H2 approval "improvement" (81.2% → 87.7%) was ArubaitoEX leaving, not an operational win. Rejections were 73% latency (>3h click-to-order), not duplicates.
18. GSC CTR "decline" 9.33% → 2.10% is a denominator artifact (impressions exploded; desktop 2.7M impr at 0.66% CTR). Clicks and position improved.
19. Slug tokens aren't comparable across years (円 → jpy rename; role tokens restructured). Only brand names (mcdonalds, lawson, family-mart) are rename-proof.
20. Conversion data shows only converting jobs — absence ≠ not posted, except where publish-rate proxies (job_no deltas) rule it out.
21. `read_conversation` on a flagged chat returns redacted, but `conversation_search` scoped to that chat ID still returns content.
22. Project SQL dumps (`prefectures (1).sql`, `areas (1).sql`) have **garbled Japanese** (double-encoded) — Claude can't match kanji against them. Use the live DB or the English column.
23. Chat `updated_at` ≠ event date; oldest table row ≠ feature start (7-day prune makes the data edge a retention horizon).
24. `grep | head -3` can truncate before the line you need → false "present". Function-scope the grep (`awk '/function X/,/^    \}/'`).
25. Aggregates over a truncated window can fit opposite hypotheses — bucket per day.
26. Causality from clocks: file mtimes and ~100 ms write spreads identify bulk writes vs hand edits.
27. "Does this look AI?" primes any LLM to list signs; AI detectors are genre classifiers biased against translated/ESL text — not evidence.
28. Large transcript/log files can be grepped — grep streams.
29. Over-specific grep patterns give false negatives: `grep -c 'fbposts:prune'` = 0 was read as "schedule missing", but the prune is a closure, not a named command. Grep for the behaviour (`subDays(7)`), not a name. Also: shell exit code 1 after a grep chain = last pattern unmatched, not a failure.
30. When a check returns exactly the reported symptom (e.g. 0 `prefecture_id` matches in the view), that *is* the bug — don't file it as "maybe expected".
31. Audits can switch comparators mid-table: restaurant-directory opening hours (Gurunavi/Retty) are the wrong benchmark for staff shift windows.
32. **Never test CDN caching with `curl -I` (HEAD).** SiteGround handles HEAD on a path that never touches the cache and always returns `X-Proxy-Cache-Info: DT:1` with no `X-Proxy-Cache`. Use two GETs within the TTL and look for `X-Proxy-Cache: MISS` → `HIT` (+ `Age:`). Cost ~4 hours in June.
33. Re-verify a finding live right before reporting it to a vendor — the June SiteGround ticket was built on stale prior-session observations.
34. A vendor's correct result from their own IP doesn't prove behaviour from other IPs or UAs; check test method first (here GET vs HEAD), then routing.
35. A pre-registered baseline can be voided by a surface change (job-listing widget shrank ~97% on Jul 17). Restate the baseline or a later fix reads as a failure.
36. Terminal summaries hid real problems in July: a silently swapped description string, a stale-base diff, an unrequested code deletion, a skipped read-only command (5×). Counter: hash proofs, evidence-first commands, refuse summaries at gates.
37. File/document uploads to reviewers arrived empty 3× — send long outputs as pasted text (in parts) or prove them by hash.
38. A "✓ verified" can hide a category error: a split rule applied outside its scope (Mikatahara seven-town list) misrouted 8 rows, caught only by reading the raw addresses.
39. Bing can show a years-old stored verdict (Tokyo "redirect to /index.php" from 2022) long after the page is fine — run a live URL test before acting.
40. `?v=timestamp` cache-bypass probes show origin rendering, not caching semantics — they hid that the same response without the param would be cached publicly (the VI poisoning hazard).
41. `X-Proxy-Cache: HIT` alone isn't verification — pair it with content assertions (job-link count, language-string count, byte size); a HIT can serve the wrong language.
42. A clean deploy isn't a working fix: a session-reading middleware prepended before `StartSession` reads the default and silently does nothing. Verify by behaviour.
43. Browser 301 caching mimics server bugs ("stuck in Vietnamese" was client-side; cookie-jar curl exonerated the server). Use a cookie jar, not a browser.
44. Historical dedup stats don't transfer to a new model: 4 cross-language collisions in 115,350 old jobs, but translated pairs share company, area and apply URL by construction.
45. Directory size ≠ page weight: image resize looked like −12.5% by `du`, was −70.5% by rendered page bytes.
46. `grep '/'` against access logs matches every line (1,549,703 "homepage hits" vs ~10,272 real) — anchor the request path.
47. `head` on robots.txt hid the `*` group at the end of the file — read the whole file.
48. A server-side curl of its own public domain goes through the CDN, so it can't prove an origin change.
49. Empty or odd grep results need context: a dev-only `@if(!app()->environment('production'))` noindex was briefly read as site-wide; check live headers. Also: a falling 404 count may just be stale flags not recrawled — check crawl dates.
50. GSC's "301s increased" mixed five unrelated URL sources — sample the URL table before reading a ratio.
51. Two GSC exports with different names ("Crawl-stats", "Crawl-stats (1)") were byte-identical — `diff -r` before treating exports as different ranges.
52. GSC can't diagnose an AdSense drop: it sees neither RPM/CPC nor non-Google traffic. Use AdSense Page views vs Page RPM + GA4 channels.
53. Screaming Frog's default UA gets the SiteGround bot challenge (HTTP 202, 169-byte noindex stub) — not what Googlebot sees. Use a real Chrome UA with JS rendering. Ahrefs audits hit the same wall; its traffic estimates lose to GSC clicks.
54. GSC average position blended across devices/appearance misleads (pos 4.85 at 0.22% CTR = desktop phantom).
55. AdSense pageview spikes track Lighthouse storms but don't reconcile (5,611 renders vs 52,096 pageviews on Jul 15) — unverified whether AdSense counts Lighthouse.
56. Read the raw artifact, not the agent's summary: Claude Code concluded "external, no server code" while its own bundle held the PSI result file naming the source. A burst of 403s isn't an attack until you see which URLs.
57. AdSense's "Competitive metrics" report no longer exists — coverage and match rate are now report columns.
58. A VC export filename states the requested range, not the returned rows — reading the Aug 1–10 file as 4 months would have understated the conversion rate ~13×.
59. A field that looks like a measurement can be a constant: VC referrer (always the detail page — only page with the Apply button), 注文時のデバイス, 注文時のOS, 注文個数. Check `nunique()` before analysing any column.
60. **A 65-row, 10-day sample got most magnitudes wrong** and two causes wrong (H1 corrected: duplicates 18.5% → 21.4%; FB share 20% → 31%; cleaning not the top-wage role; no weekday effect; Kanagawa is a category-mix story, not wage). Structural findings held. Don't make decisions on one short window.
61. A pending-only export can't show rejections, so it hides what duplicates cost. And a correct prediction can rest on the wrong mechanism (duplicates did get rejected — but from cookie replay, not the Apply button).
62. Report parse coverage before ranking anything: the first station regex skipped all kanji-slug URLs (628 of 1,260 parsed) and inverted the rankings.
63. Wages parsed from slugs can be daily wages (日給) — cap them before averaging (Okinawa's "¥3,000 median").
64. FB reach per post and total reach move opposite ways with volume — read both. The raw link-vs-photo gap (+84%) was mostly a timing confound.
65. SiteGround's WAF answers request bursts with 403, then HTTP 202 + `sgcaptcha` — it says a request was refused, not who refused it (produced a false "cloaking" conclusion). Throttle diagnostics (≥1.5 s) with a preflight 200 check.
66. The assistant's own fetch tool can serve stale cached pages and strip a cache-buster query — it "saw" the Japanese outage after it was fixed.
67. GSC lists 410 as "Not found (404)" and files URLs by their *last observation* (301ing `index.php` URLs showed under "noindex"). URL Inspection shows only the last crawl.
68. Lab CLS swings 0.06–1.08 between identical runs (ad fill); trust field CLS. Lighthouse may report an old image size until the CDN serves the new file.
69. Verify with the query the page actually uses: an area passed on `area_id` count (22 jobs) but its hand-cash page text-searches and returned 0. Likewise `Str::slug()` ≠ `findAreaByName()`.
70. `git log` is not a deploy record: production isn't a git checkout; `robots.txt`/`.htaccess` sit outside the repo; no deploy record exists before 2026-07-21. Three-way md5 drift on `ListingController.php` happened three times. The repo root *is* the Laravel root — paths prefixed `laravel/` silently miss.
71. Screaming Frog "status 0" rows were local DNS failures, not site errors. Vendor SEO posts announced an "August core update" that never existed — the Google status dashboard is the record.
72. GA4 raw users are bot-heavy (51% on one Aug 27 sample): Meta data-centre cities (Prineville, Forest City, Luleå) are Facebook link-preview crawlers from our own FB posting; Lanzhou = scraper; Boardman/Ashburn = AWS. "Engaged sessions" can be spoofed (Singapore: 17 at 0 s) — engagement *time* separates people (100 s+) from bots (0 s). One day is a reading, not a proportion.
73. A check whose command can't run gets ticked anyway (v2.1's SQL targeted a table that doesn't exist) — target the real schema. Count distinct pages, not grep occurrences, for any "on N+ pages" rule. Screaming Frog All Inlinks needs Link Position = Content or header/footer links drown it. Unobservable metrics ("pogo-sticking") don't belong on a checklist.
74. Attached .xlsx files can arrive empty in a chat (the two audit reports did, repeatedly) — confirm a file was actually read before citing it.
75. FB ad cost: use Ads Manager results, not the page export — "¥50 per click" for Kanto was really ¥14.9 per landing view (the export missed campaigns on pre-Jan posts).
76. General-knowledge lists aren't evidence: station tiers got ✓ marks for stations that were merely in that day's queue. Only the site's own applies data counts. And 1 apply is not a result.
77. Two quick cookieless fetches inside the cache TTL only prove the cache is consistent — purge first or use URL Inspection's live test. Compare unequal date windows per day, not in totals.
78. On the Mac, `git grep … $(git rev-list --all)` over history also returns nothing (positive control `parseBaseSalaryLd` failed). Scan history with `git cat-file --batch-all-objects` + a regex instead, with a positive control. Every search needs a positive control before an empty result counts.
79. A 403 isn't proof of protection: on this site every blocked private path returned SiteGround's identical 75,193-byte WAF challenge page — our own config protected nothing. Anything the WAF didn't recognise (`laravel.log`, `artisan`) was served. A run where *everything* 403s, homepage included, is the WAF blocking you (the Windows-Chrome UA tripped it; Mac-Chrome didn't). Control with the homepage before and after.
80. Fixing the origin doesn't end an exposure behind a CDN: after the log was moved, a cache-busted request got the new 97-byte file but a normal request still got the 16 MB copy. Flush Dynamic Cache and re-test without a buster. Also: on SiteGround, nginx serves some static extensions (e.g. `.xml`) straight from disk, so `.htaccess` denies only cover what reaches Apache — test each extension, don't assume.
81. Never leave backups in the web root. `.zip`/archive files are served straight from disk by nginx — the WAF blocked `public_html.zip` only by filename luck, while `public_html_2_9_2026.zip` (with live WP secrets) stayed public for months. Backups live outside `public_html` (e.g. `~/site-archives/`). Moving a leaked secret doesn't undo the leak — rotate it.
82. Test redirects, don't reason about them: the new blog rules first chained (placed below the trailing-slash stripper, and `(.+)` swallowed the trailing slash). Moving them to the top of `public_html/.htaccess` and using `([^/]+)/?$` gave one hop. Check every redirect with and without a trailing slash.
83. Secret scanners match their own documentation: once a security command containing `github_pat_` / `https://…@github.com` patterns is committed, a history scan will "find tokens". Use length-bounded patterns (`github_pat_[A-Za-z0-9_]{40,}`) and confirm any hit by file path before acting.

---

## 7. Tools, commands, procedures

- **Slash commands:** `.claude/commands/` **does not exist** in the repo. `/remove-listing-jobposting` was run from a local file (2026-10-12). Earlier commands (`/conversion-report`, `/affiliate-boost-shortlist`, `/fb-daily-queue`, `/revenue-diagnosis`) are referenced in chats but **not in the repo** — recreate or locate before relying on them. July also created `homepage-meta-fix`, `crawl-latency-check`, `jobposting-schema-fix`, `municipality-reorg-sweep` (and earlier `deploy-prefecture-content`, `content-integrity-sweep`, `content-integrity-fixes`, `miyakojima-merge`) — copies were in that chat's outputs, also not in the repo. `.claude/skills/laravel-best-practices/` exists (SKILL.md + 21 rules). Their specs (a15d8696): `/conversion-report [days=120]` reads `application_logs` + `secondary_applies`, station from title `' at % Station'`, writes `docs/reports/conversion-report-<date>.md` (ran Aug 23 on the Windows repo); `/affiliate-boost-shortlist [days=120]`; `/fb-daily-queue [hours=48]` writes `docs/fb/queue-<date>.md`, UTMs `utm_medium=social&utm_campaign=<page>_organic` / `utm_medium=boost&utm_campaign=<station>_<category>`. ⚠️ They hardcode `mysql gaijinbaito` — against the read-DB-name-from-`.env` rule; fix when recreating.
- **Server data artifacts:** `/home/customer/catchall_city_zip.csv` (postal source, 1,870 rows) · `/home/customer/salary_fix_snapshot.json` · `/home/customer/partg_backup_20260721_132931.json`.
- **Municipality reorg procedure** (Part G pattern): inventory incl. expired jobs → dependency sweep (`job_location_preferences` too) → backup → INSERT successors → migrate prefs (overlap-delete + dedup) → migrate jobs by address → conservation check → delete old areas → curl retiring slugs, add 301s for failures → `rm -f` sitemap + regenerate → purge Dynamic Cache → re-curl.
- **Analysis scripts** (2026-10-12 session, not in repo): `prep.py`/`prep2.py`/`prep26.py` (VC CSV parsing, cp932, slug attribute extraction), `a1–a7`, `b1–b2`, `c1–c3`, `d1–d3` (analysis), `build_xlsx.py`.
- **Daily FB queue command** (from a15d8696): territory routing Tokyo / Kanto (Saitama, Kanagawa, Chiba, Ibaraki, Gunma, Tochigi) / Osaka (Osaka, Kyoto, Hyogo, Nara, Shiga, Wakayama); quotas Tokyo 8 / Kanto 6 / Osaka 8. **Needs updating:** morning slots, same-day jobs only, drop wage bonus. Also pending from a15d8696: −1 score for weak stations (Hiroo, Ginza, Toyosu, Kawasaki, Sannomiya), max 1 post per station × category per page per day, one boost per station × category at a time. Station tiers file `station_tiers_posting_team.csv/.xlsx` (PROVEN 24, WATCHLIST 41, WEAK 6, AVOID 2; WATCHLIST → PROVEN only on real applies).
- **Artisan:** `jobs:auto-trash` (3→4 past `delete_at`), `sitemap:generate`, `applogs:fetch`, `fbposts:prune`, `fbposts:backfill-prefectures`, `DeduplicateJobs`.
- **Cron (production):** `schedule:run` every 10 min → auto-trash 02:00, sitemap 02:30, fb prune 03:00; `jobs:auto-trash` daily (duplicate); `applogs:fetch` hourly.
- **Drift check** (SETUP.md §4) monthly + before big deploys. **Deploy verify:** `md5 -q` local vs `md5sum` server.
- **Homepage cache regression test:** cookieless curl, iPhone UA, `grep -c "detail/english"` → expect 50–90; 0 or <10 = empty page cached.
- **Outage playbook** (ledger 2026-09-17): multi-network test → status page → cert dates → cache purge → ticket → flush local DNS.
- **NULL-bucket query** (fb_posts health): `SELECT DATE(created_at) d, COUNT(*) total, SUM(prefecture_id IS NULL) nulls FROM fb_posts WHERE created_at >= '<date>' GROUP BY DATE(created_at) ORDER BY d;`
- **Hidden-Unicode scan:** Python regex `[\u200b\u200c\u200d\u2060\ufeff\u202f]` over the content `*.html` files.
- **Snapshot-before-restore:** copy live files to `/tmp` (+ scp local) → restore → diff.
- **Image resize:** `mogrify -resize '500x333>' -quality 85 *.webp` (quote it — bare `>` is a shell redirect). **Page weight:** sum `%{size_download}` over each unique image on a listing page.
- **Log/traffic commands (Aug, local `.claude/commands/`, not in repo):** `/analyze-access-logs` (read-only, month of logs → `~/logmonth/` summaries: daily, top IPs/UAs, AdSense crawler codes, index.php by day), `/find-lighthouse-source` (read-only, per-day Lighthouse counts + cron/repo grep → `~/lh_diag/`), `/install-agent-browser` (agent-browser CLI + skill, pinned 0.33.2, smoke-tests AdSense fill on an iPhone viewport).
- **VC duplicate checks (H1 method):** replay key = クリック日 + `|` + offer_id from リンク先URL (`/offer/(\d+)`); late flag = 注文日 − クリック日 > 1,440 min; FB proxy = `fbclid` in リファラー or `utm_source=fb` or blank クリック時のブラウザー・アプリ. Station regex `-at-([a-z0-9\-]+?)-station` works on kanji-encoded slugs too. Scripts (`h1*.py`, `grp.py`, `station.py`, `fb*.py` …) in that chat, not in repo. Unrun SQL: live duplicate inventory `SELECT apply_link, COUNT(DISTINCT id) FROM jobs WHERE job_status_id=3 GROUP BY apply_link HAVING COUNT(DISTINCT id)>1;` and whether an applog table keeps status history.
- **Crawl diagnostics (Aug, not in repo):** `crawl-health-diagnostic-v2.sh` (throttled 1.5 s, preflight 200 gate, POSIX-only); `source-inspect.sh` (server-side greps, zero external HTTP — WAF-proof); `area-slug-snapshot.php` (records what every area slug resolves to). Image resize via temp dir: `cwebp -q 82 -resize 476 0 "$f" -o ~/tmp/jobs-resized/"$f"` then move back.
- **`/update-prefecture-content <prefecture>`** (Claude Code command; install to `.claude/commands/` not confirmed): find `blog_posts` row → timestamped backup → verify each link's slug against `areas` (301-only = mismatch) → HTML + content-rule checks → parameterised update with char-count check → manual SiteGround purge → live fetch with browser UA → evidence report.
- **`/session-start`** (Oct 14, `.claude/commands/session-start.md`): run first on either machine — fetch, show behind/ahead/uncommitted/stashes, md5-compare any local changes with server + origin, verdict 🟢/🟡/🔴, pull `--ff-only` only on approval, then summarise where we left off. Repo paths: Windows `C:\Users\Tech Fever\Desktop\nihonarubaito\laravel` (this *is* the git repo — the Aug 28 "staging tree"), Mac `~/Desktop/nihonarubaito_laravel`. Oct 14: Windows was 15 commits behind (at `2487e00`, Sep 17), clean — pulled. Oct 14 commit `7371666` added `session-start.md` + a Claude-Code-written `security-check-windows.md` (simpler than the drafted one — no git-history/token checks; acceptable since GitHub has no PATs left). Lesson: without `docs/PROJECT_STATUS.md` in the repo, Claude Code fell back to stale notes and named the tag system as "highest ROI" — put the status file in the repo.
- **Language-cache test** (findings doc §9): cookie-jar curl — set `/lang/3`, check VI homepage is `no-cache` and contains Vietnamese job slugs; then EN homepage is `HIT` with ~90 `/jobs/` links. Run before and after any cache/session/language deploy.

## 8. Key IDs & paths

- **Production is not a git checkout** — deploys are scp; `robots.txt` and `.htaccess` live outside the repo. Stack: Laravel 11 / PHP 8.2
- GA4 **Nihon Arubaito 345458315**, stream 4340167613, **G-8ZMLZ8JK5L** · AdSense **pub-5261166510941827** (matches ads.txt)
- Repo `sawoodo/nihonarubaito_laravel` · Server `~/www/nihonarubaito.com/public_html/laravel/`
- Detail template `resources/views/jobs/detail.blade.php` (schema built in `JobController::createSchema()`)
- Listing schema `ListingController::buildStructuredData()` (called once in `bySlug()`) · Noindex guards `ListingController.php:354/:475`
- Expired tiers `JobController::handleExpiredJob()` · Cache headers `app/Http/Middleware/ApplyCacheHeaders.php`
- `areas`: `id` auto_increment; all name columns NOT NULL — chinese = kanji, korean = `''`, vietnamese = English + trailing comma. `job_location_preferences` has no unique index on (user_id, area_id). Importer takes `$xml->area_id` verbatim (`CreateJobFromXmlController` ~l.360)
