# Nihon Arubaito - Development Context

> Connection details (SSH host/port/user, DB name) are kept in a separate private
> credentials note, NOT in this file. Read DB_DATABASE live from .env; never hardcode.

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
- Status 3 = Published (active, indexed) | Status 5 = Trashed (expired)
- Tier 1 (0-90d expired): noindex, keep page live with related jobs
- Tier 2 (90-180d): 301 redirect to parent location page
- Tier 3 (180+d): 410 Gone
- delete_at controls auto-expiration

## Database Key Tables
- jobs: job_no, title, description, wage, prefecture_id, area_id, job_category_id,
  job_status_id, apply_link, delete_at, user_id
- prefectures: id, english, japanese | areas: id, prefecture_id, english, japanese
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
  Job::parseBaseSalaryLd() used by ListingController::buildStructuredData() AND
  JobController::createSchema(). Validated on all 991 distinct wages: 945 parse (95.4%),
  46 omit (4.6%), 0 false positives. Fixed prior bug where detail page emitted
  concatenated garbage (e.g. "1600円～2000円" → 16002000) on ~78% of jobs.
  DO NOT convert listing inline JobPosting/ItemList to url-only ListItems — that
  pattern earns the Job listing rich result; fix only fields inside it.
  VALIDATED by Google Rich Results Test (Jul 7 2026): 31 valid items, 30 Job Postings,
  0 errors (only optional postalCode warning). baseSalary renders numeric. Task closed.

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
