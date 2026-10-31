# Station pages: GSC baseline for step 3 (Explore Nearby)

Search Console, web search, 1–28 Oct 2026 against the same period last year,
filter `part-time-jobs-at-`. Entirely before step 1 (live 31 Oct). Raw files:
`station-baseline-2026-10-28-pages.csv` and `-daily.csv`.

Totals are per station across all its URL forms: `/page/N`, last year's
capitalised slugs (`-at-Toyohashi-station`) and the old `index.php` URLs. Rows for
stations outside the 40 are left out (`kawaramachi-station`, `kitashinchi-station`).

| Group | Clicks | Last year | Impressions |
|---|---:|---:|---:|
| 8 Osaka pages | 27 | 25 | 828 |
| Other 32 pages | 255 | 137 | 12,490 |
| Other 32 without Omiya, Hirose-dori, Kyoto-Kawaramachi | 248 | 126 | 12,294 |

| Osaka page | Clicks | Last year | Impressions |
|---|---:|---:|---:|
| Namba | 17 | 16 | 289 |
| Umeda | 7 | 4 | 139 |
| Shinsaibashi | 2 | 1 | 58 |
| Osaka | 1 | 2 | 219 |
| Tennoji | 0 | 2 | 74 |
| Kyobashi | 0 | 0 | 48 |
| Hommachi | 0 | 0 | 1 |
| Yodoyabashi | 0 | 0 | 0 |

Last year's impressions are not shown: October 2025 falls inside Google's
impression logging error (May 2025–Apr 2026), so that column means nothing.
Impressions compared within Oct–Dec 2026 are fine.

## How step 3 is measured (owner, 2026-10-31)

27 clicks in 28 days is too few to judge by clicks. In order:

1. Impressions and position of the 8 Osaka pages and their link targets.
   Yodoyabashi and Hommachi, near zero today, are the clearest test.
2. GA4 event `explore_nearby_click`, parameter `nearby_link` (the path clicked).
   The page it was clicked on is in `page_location`.
3. Clicks on the 8 pages against the other 32 as a control, for context, 28 days
   before and after step 3. Read the control with all 32 and again without the
   three pages step 1 changed most, because step 1 hit the pages unevenly.

The 28 days before step 3 include about a week after step 1, for both groups.

## Not in this export

The link targets outside the station pages. On or after the step 3 day, use
Pages with a custom date range from 1 Oct, once for `part-time-jobs-at-` and once
with this custom regex:

```
part-time-jobs-in-(osaka|tokyo|chuo-ku-osaka|naniwa-ku-osaka-shi|kita-ku-osaka|osaka-tennoji-ku|abeno-ku-osaka|nishi-ku-osaka|miyakojima-ku-osaka|chuo-ku)$
```

`tokyo` and `chuo-ku` are Kyobashi's links to the Tokyo Kyobashi.
