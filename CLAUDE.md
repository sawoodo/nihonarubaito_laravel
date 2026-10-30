# Nihon Arubaito - Development Context

> Connection details (SSH host/port/user, DB name) are kept in a separate private
> credentials note, NOT in this file. Read DB_DATABASE live from .env; never hardcode.

## Deploy Rule — PUSH BEFORE DEPLOYING (always)
GitHub is the source of truth. Work happens on a Mac and a Windows PC, so a
commit that is live on the server but not on origin/main leaves the other
machine silently behind and the server ahead of every copy of the repo.
Order, every time, no exceptions:
  1. commit
  2. `git fetch` + `git push origin main`  ← never skipped, never deferred
  3. deploy (scp + the usual gates)
  4. **leave the config cache REBUILT** — see the next rule
Pushing after the deploy is not the same thing: if a gate fails and the deploy
is rolled back, what shipped and what is on origin have already diverged.
Set 2026-10-23, after three commits ran live while still unpushed.

## Deploy Rule — NEVER LEAVE THE CONFIG CACHE CLEARED
Production MUST end every deploy with `bootstrap/cache/config.php` present.
It is not an optimisation: this host's filesystem intermittently hands Laravel
an **empty `.env`**, and a request that boots without it gets no `APP_KEY` and
falls back to sqlite, then 500s. The cache means requests never read `.env`.
See docs/ledger.md 2026-09-23 and SiteGround ticket #5141798 (still open).

So any deploy that runs `config:clear` (or `optimize:clear`) must finish with:
```bash
ssh nihonarubaito 'cd www/nihonarubaito.com/public_html/laravel && \
  php artisan config:cache && \
  php -r "\$c=require \"bootstrap/cache/config.php\"; \
    printf(\"key_len=%d db=%s env=%s debug=%s\n\", strlen(\$c[\"app\"][\"key\"]??\"\"), \
    \$c[\"database\"][\"default\"]??\"?\", \$c[\"app\"][\"env\"]??\"?\", \
    var_export(\$c[\"app\"][\"debug\"],true));" && \
  php artisan --version'
```
Expect `key_len=51 db=mysql env=production debug=false` and a version line.
`db=sqlite` or `key_len=0` means the rebuild baked in a failed `.env` read —
run `config:clear` immediately and rebuild. Read the generated file with
`php -r`, never via artisan/tinker: a broken cache reports itself as fine.

Rebuilding is also what makes a `config/` or `.env` change take effect at all.
Set 2026-10-24, after a deploy's `config:clear` left the cache absent for 19
hours and 52 requests 500'd on the empty-`.env` fault it exists to mask.

## Environment
- Domain: https://nihonarubaito.com  |  Admin: /admin/login
- Hosting: SiteGround (Japan server). `php artisan cache:clear` does NOT purge
  SiteGround Dynamic Cache/CDN — purge that separately in Site Tools after deploys.
- Stack: Laravel + MySQL + Bootstrap + jQuery + Chart.js

## What Nihon Arubaito Is
A job board that provides Japanese job listings in English for foreign residents in
Japan who speak basic Japanese but cannot read/write kanji. Audience: language-school
and university students, dependent-visa holders from Vietnam, Nepal, Philippines, China,
Indonesia, Myanmar, South Asia. Revenue: Google AdSense + Amazon Associates +
ValueCommerce affiliate (job apply clicks).

## Job Lifecycle System
- **Status names differ between the admin UI and the code.** Both are in use and
  neither gets renamed — the retirement and expiry work depends on the constants.
  Say the id when it matters: "Draft" from the team means status 2, not `STATUS_DRAFT`.

  | id | UI label (`job_status` table) | Code constant (`Job::`) |
  |---|---|---|
  | 1 | Auto Inserted | `STATUS_DRAFT` — bulk `[SAME AS N]` rows; hidden from the admin list |
  | 2 | **Draft** | `STATUS_PENDING` — every new job, the normal state before publishing |
  | 3 | Published | `STATUS_PUBLISHED` |
  | 4 | Expired | `STATUS_EXPIRED` |
  | 5 | Trashed | `STATUS_TRASHED` |
  | 6 | Already Posted | `STATUS_QUOTA_FULL` |
- Status 3 = Published (active, indexed) | Status 5 = Trashed (expired)
- Tier 1 (0-90d expired): noindex, keep page live with related jobs
- Tier 2 (90-180d): 301 redirect to parent location page
- Tier 3 (180+d): 410 Gone
- delete_at controls auto-expiration

## Database Key Tables
- jobs: job_no, title, description, wage, prefecture_id, area_id, job_category_id,
  job_status_id, apply_link, delete_at, user_id
- prefectures: id, english, japanese, region_id
- towns: id, prefecture_id, english, japanese (47 rows — one per prefecture)
- areas: id, **town_id**, english, japanese, postal_code (1,892 rows)
- IMPORTANT: areas have NO prefecture_id. Join areas → prefectures through towns:
  `areas.town_id → towns.id`, then `towns.prefecture_id`. Verified 2026-10-21.
- `prefectures.japanese` is NOT a safe match key: 40 of 47 rows hold the bare name
  (東京), 4 hold the full official name (愛知県), and 3 are wrong — id 14 Niigata
  `新潟市`, id 23 Kumamoto `熊本市` (city names) and id 39 Wakayama `若山`
  (wrong characters). Match prefecture names against a canonical list and resolve
  to ids via `english`, which is clean for all 47 — see App\Services\AddressLocationResolver.
  Not public (every published job is lang_id=1, so prefecture_name renders from
  `english`); it does feed the admin demand-supply shigotoin search links.
- blog_posts: id, slug, lang_id, title, post (HTML for prefecture pages)
- job_categories: 1=Packing/Sorting, 2=Restaurant, 3=Convenience Store,
  4=Bed Making/Cleaning, 5=Delivery
- users: role_id=1 is admin
- IMPORTANT: Listing/detail rows come via Job::search() / withLocalizedNames() with
  aliased join columns (prefecture_name, area_name) — these behave like stdClass, NOT
  full Eloquent models. Use static helpers (e.g. Job::parseBaseSalaryLd()) on them,
  not magic accessors.

## Admin Dashboard Pages Built
1. /admin/analytics — overview (cards, new-vs-expired chart, category breakdown, top jobs, prefecture breakdown, date range)
2. /admin/analytics/demand-supply — GA4 CSV upload; finds pages with traffic but zero jobs (192 gap pages)
3. /admin/analytics/employees — employee performance (jobs/day, streaks, heatmaps)
4. /admin/analytics/expiring-jobs — expiring today/tomorrow/7d, inline date edit, +7/+30/+60 extend, bulk actions
5. /admin/analytics/duplicates — duplicate detection (HIGH/MED/LOW), side-by-side, keep & trash

## SEO Fixes Deployed
- www → non-www 301; trailing-slash → non-trailing 301
- Japanese URL slugs fixed (69 pages); job title truncation to 42 chars (694 pages)
- About page H1 fix
- Area slug canonicalization: /part-time-jobs-in-shinjuku → 301 → -shinjuku-ward (1,183 areas)
- BreadcrumbList schema on all pages
- rel="nofollow sponsored noopener" on apply/affiliate links
- Duplicate content prevention at job creation (3 confidence levels)
- JobPosting baseSalary parser (Jul 2026): string→numeric, ranges via minValue/maxValue,
  unit detection (HOUR/DAY/MONTH), safe-omit on unparseable. Shared static helper
  Job::parseBaseSalaryLd() used by JobController::createSchema(). Validated on all
  991 distinct wages: 945 parse (95.4%), 46 omit (4.6%), 0 false positives. Fixed
  prior bug where detail page emitted concatenated garbage (e.g. "1600円～2000円" →
  16002000) on ~78% of jobs. baseSalary renders numeric.
  Listing pages carry NO JobPosting markup (Google policy: single-job pages only).
  JobPosting lives only on job detail pages via createSchema(). Decided 2026-10-12.

## Performance Fixes
- Removed 70KB areas JSON from every page (FrontendComposer)
- Staggered scripts: AdSense immediately, GA4 +1s, FB Pixel +2s; AdSense push via setTimeout 100ms
- CLS: min-height wrappers on ad containers | INP: 819ms → target 200-250ms
- Crawl latency: ~1,173ms Googlebot TTFB is cold-cache origin fetch to Singapore.
  CDN DOES cache real GET requests (X-Proxy-Cache: HIT within TTL); the DT:1 seen earlier
  was on HEAD (curl -I), which CDNs don't cache. Reframed as "origin cold-fetch latency"
  — lower priority than tag migration.
- CLS NOTE (Jul 2026): Homepage Lighthouse CLS ~1.061 (field 0.15, failing). Root cause:
  main stylesheet loads fully async (media=print/onload) in shared layout, reflowing every
  page. Above-fold images already have explicit dimensions. Fix deferred — CSS-loading
  change and ad-placement change both have uncertain outcomes on a stable/revenue site;
  revisit as a dedicated, measured session (test LCP before/after). Do NOT bundle with other work.

## Content Strategy & Rules
- Prefecture content in blog_posts (slug matches URL, lang_id=1); 49 posts exist
- Tokyo updated Feb 15 2026 (11,778 chars, internal links, kanji/romaji, first-hand advice)
- **Kanagawa prefecture content: DO NOT deploy or modify. Live page performing; draft held in reserve. Decision 2026-07-16.**
- NOT generic AI text — real knowledge of foreigners working in Japan
- FAQ schema auto-extracted from <h3>+<p> after "Frequently Asked Questions"
  (Google retired FAQ rich results 2026-05-07 — keep for structure/AI-citation signal,
  don't expect a SERP feature, don't add new ones for SERP)
- Audience: foreigners with basic spoken Japanese, can't read kanji
- Do NOT name competitors (baitoru, townwork, shigotoin); do NOT claim "no Japanese needed"
- Include kanji+romaji: tewatashi (手渡し), zairyuu kaado (在留カード), etc.
- "Provides job listings in English" (not "translates" — direct employer listings planned)
- Interlink area / hand-cash / daily-payment / bed-making pages
- FAQ answers must be NEW info; save Do's/Don'ts, phrases, bank-account guide for blog

## Minimum Wages (as of Oct 2025)
- Tokyo ¥1,226 | Kanagawa ¥1,225 | Osaka/Saitama/Chiba/Aichi/Kyoto/Hyogo ¥1,100+
- National average ¥1,121 | Night premium mandatory 25% after 10 PM

## Key Files
- app/Models/Job.php — Job model; static parseBaseSalaryLd() wage parser
- app/Http/Controllers/ListingController.php — frontend listings (buildStructuredData)
- app/Http/Controllers/JobController.php — job detail pages (createSchema)
- app/Http/Controllers/Admin/AnalyticsController.php — analytics dashboards
- app/Http/Controllers/Admin/JobController.php — admin job management
- app/Http/Controllers/Admin/CreateJobFromXmlController.php — XML import (img_link null crash open)
- app/Services/JobDeduplicator.php — duplicate detection
- resources/views/frontend/layouts/frontend.blade.php — main layout
- resources/views/frontend/jobs/detail.blade.php — job detail
- resources/views/frontend/layouts/scripts.blade.php — staggered JS

## ValueCommerce Integration
- OAuth bearer token for conversion tracking; raw curl (not Guzzle) to match headers
- Cron: applogs:fetch every 1-2 hours

## Google Search Console — Measurement Rules (Updated Aug 24, 2026)
**CRITICAL: Google confirmed a logging error that made impressions wrong May 2025–Apr 2026.**
Any impression, CTR, or position comparison spanning that window is meaningless. **Track
clicks only.** See docs/gsc-measurement-notes-2026-08-24.md for full detail.

### Current Performance (Japan mobile clicks, YoY matched months)
- May: 424.3 → 527.3 (+24%) | Jun: 346.4 → 473.7 (+37%) | Jul: 335.0 → 411.9 (+23%)
- Jun 1–Aug 24: 294.1 → 373.1 clicks/day (+27% YoY, every device up)
- ~89% Japan, ~89% mobile, indexed ~6,600

### Seasonality Pattern (Confirmed Two Years Running)
- **Peak:** May (~525 clicks/day) — April fiscal/academic year start drives demand
- **Trough:** December (~225 clicks/day) — 50% swing peak-to-trough
- **Turn:** February, sharp rise through April
- **Summer decline is NORMAL.** Between June and December, clicks fall. Do not diagnose
  as a problem — compare YoY same period before investigating any change.
- **Rule:** Build Sep–Feb (ship risky changes in quiet months). Harvest Apr–Jun (peak traffic).

### What's Actually Growing (YoY clicks/day)
- Hand-cash queries dominate: "hand cash part time job near me" +660%, "hand cash job
  in kyoto" +286%, "handcash job near me part time" +209%
- Brand: "nihon arubaito" +32%
- Declining: "part time jobs near me" -8% (generic, worst converter)
- **Three instruments agree hand-cash converts:** queries (largest YoY gains), landing
  pages (hand-cash 10.32% CTR vs 1.94%), Facebook (hand-cash posts 2.48 vs 1.07 clicks/post)

### Opportunity (Unchanged)
- "weekend jobs" 164K impressions/28d, pos ~4, ~1 click (homepage surfaces, no landing page)
- "night shift jobs" 49K/28d, "summer jobs" 12.5K/28d
- Tag-system migration is the direct payoff for these

### Confirmed Google Updates (No Impact)
- May 2026 core (May 21–Jun 2): traffic **peaked** the week it completed
- Jun 2026 spam (Jun 24–26), Aug 2026 spam (Aug 18–21): no step change observed
- FAQ rich results removed May 7, 2026 (external, affects all sites, impression drop permanent)

## Priorities
1. Tag-system migration — weekend/night-shift/summer landing pages. Phase 1 schema done;
   awaiting diagnostic precheck before Phase 2. Highest ROI (even 1% CTR on "weekend jobs"
   ≈ +59 clicks/day). Precheck: latin1 collation trap; FK type match (bigint vs int unsigned).
2. Origin cold-fetch latency (Singapore) — lower priority than previously thought.

## Ongoing Tasks
- Prefecture content: Tokyo done, Osaka next
- Admin blog posts DataTable not showing rows (JS deployed, still broken)
- Monitor INP in GSC
- Blog section for guides
- Fix XML img_link null crash in CreateJobFromXmlController (pairs with getThumbnailAttribute())
- Image library rollout (154-prompt reference; Grok Imagine) — wire Blade + PHP model method
