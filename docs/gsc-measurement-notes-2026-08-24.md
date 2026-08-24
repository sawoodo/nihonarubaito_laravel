# GSC Measurement Notes — 2026-08-24

Three findings that invalidate a lot of prior analysis and should be checked before
any future Search Console work.

---

## 1. TRACK CLICKS. IGNORE IMPRESSIONS, CTR, AND AVERAGE POSITION.

Google has confirmed a logging error that made impressions wrong for eleven months:

> **April 3, 2026 entry, Search Console data anomalies page:**
> "A logging error prevented Search Console from accurately reporting impressions
> from **May 13, 2025 until April 27, 2026**. This issue has been resolved. As a
> result, you may notice a decrease in impressions in the Search Console Performance
> report. **Only impressions and related metrics — CTR and average position — were
> affected; clicks were not affected** by the error."

**Consequences:**

- Any impression comparison spanning April 2026 is meaningless. The apparent jump is
  a counter being fixed, not growth.
- CTR and average position inherit the error, because impressions are the denominator.
- Clicks are clean throughout and are the only trustworthy metric.

**Specific claims this invalidates, all made in analysis before 2026-08-24:**

| claim | status |
|---|---|
| "impressions +673% year over year" | artifact of the fix |
| "desktop impressions +894%" | artifact |
| "Job listing appearance 7,709 → 247,931 impressions" | mostly artifact |
| "CTR collapsed 8.14% → 1.17%" | denominator changed, not behaviour |
| "average position degraded 4.9 → 5.8" | derived from bad impressions |
| "93% of impressions come from the generic cluster" | directionally right, magnitude unreliable |

**The year-over-year growth conclusion survives**, because it was measured in clicks.

---

## 2. THE SITE IS SEASONAL. MAY PEAK, DECEMBER TROUGH, ~50% SWING.

16 months of clicks/day (clicks are unaffected by the logging error):

```
2025-04   387.9   (partial month)
2025-05   424.3   <- peak
2025-06   346.4
2025-07   335.0
2025-08   328.0
2025-09   296.4
2025-10   299.9
2025-11   266.9
2025-12   225.5   <- trough
2026-01   265.8
2026-02   285.6
2026-03   290.1
2026-04   456.8
2026-05   527.3   <- peak
2026-06   473.7
2026-07   411.9
2026-08   342.0   (partial month)
```

Same curve two years running. Peak in May, decline through December, turn in
January/February, sharp rise in April.

Driver is almost certainly Japan's April academic and fiscal year start — students
and new arrivals looking for work build into spring.

**Year over year, matched months:**

```
May   424.3 -> 527.3   +24.3%
Jun   346.4 -> 473.7   +36.7%
Jul   335.0 -> 411.9   +23.0%
Japan clicks/day, Jun 1 - Aug 24:  294.1 -> 373.1   +26.8%
```

**Every device up.** Mobile +23.1%, desktop +23.7%, tablet +26.7%.

### What this means operationally

**A summer decline is normal and expects no diagnosis.** Between June and December,
clicks fall. In 2025 that was -47% peak to trough; in 2026 so far it tracks the same
shape from a 25% higher base.

**Expect further decline through autumn.** If the pattern holds, the floor is around
280-300 clicks/day in December, then a turn in February.

**Build September to February. Harvest April to June.**
Ship content, architecture, and risky changes in the quiet months so they mature
before the spring surge. Do not deploy risky changes into peak season.

### Three weeks of investigation this would have prevented

Between 2026-08-01 and 2026-08-24, a summer decline was investigated against, in turn:
the sitemap expansion, the job title reorder, the empty-page noindex guard, the
language cache-poisoning outage, and three Google algorithm updates. Each was ruled
out on evidence. **None was the cause. It was the annual cycle.**

The error was analysing 7-day, 28-day, and 90-day windows — every one of which starts
at or near the May peak and therefore shows a decline. The year-over-year comparison
was the measurement that mattered and it was not run until the end.

**Rule: before diagnosing any traffic change, compare the same period last year.**

---

## 3. OTHER DOCUMENTED GSC ANOMALIES AFFECTING THIS PROPERTY

**FAQ rich results removed, 2026-05-07 onwards.**
FAQ rich results no longer appear in Google Search at all. The FAQ schema auto-extracted
from `<h3>` + `<p>` pairs on prefecture pages now has no surface to appear in. Not a
traffic loss — FAQ rich results drove few clicks — but the impressions are permanently
gone and the schema is effectively dead weight. Do not investigate the FAQ impression
drop; it is external and final.

**Job listing / Job details logging broken 2026-04-16 to 2026-04-27.**
Impressions and clicks missing for both Search-appearance types in that window. Any
comparison anchored to late April is distorted.

**Bulk data export missing 2026-02-28 and 2026-03-01.** Not recoverable.

---

## 4. CONFIRMED GOOGLE UPDATES IN THE WINDOW

For the record, from status.search.google.com — all three fall inside the period that
was investigated, and none matched the decline pattern:

```
May 2026 core update      2026-05-21 -> 2026-06-02
June 2026 spam update     2026-06-24 -> 2026-06-26
August 2026 spam update   2026-08-18 09:27 PDT -> 2026-08-21 01:49 PDT
```

Traffic **peaked** the week the May core update completed. The decline that followed
was smooth rather than stepped, which is not the signature of an algorithm update.
Positions held or improved throughout (`nihon arubaito` 1.1 -> 1.0,
`hand cash job near me` 1.4 -> 1.3).

The August spam update completed 2026-08-21 and is too recent to assess. Watch
2026-08-25 to 2026-09-05 for a **step** change in clicks at stable impressions. A
continuation of the existing taper is the seasonal curve, not the update.

---

## 5. WHAT IS ACTUALLY GROWING

Year-over-year clicks/day by query — hand-cash variants dominate the gains:

```
hand cash part time job near me     0.22 -> 1.67   +660%
hand cash job in kyoto              0.47 -> 1.82   +286%
part time jobs for foreigners       0.51 -> 2.07   +310%
handcash job near me part time      0.37 -> 1.15   +209%
hand cash job near me night shift   1.37 -> 2.68    +95%
bed making job near me              1.09 -> 2.05    +88%
bed making hand cash job near me    1.34 -> 2.12    +58%
nihon arubaito (brand)              9.00 -> 11.87   +32%
hand cash job near me               9.90 -> 10.99   +11%
part time jobs near me             10.09 ->  9.25    -8%   <- only decliner
```

The only query losing ground is the generic one that converts worst.

**Three independent instruments now agree that hand-cash is the converting concept:**

1. **Search queries** — hand-cash variants show the largest year-over-year gains
2. **Landing pages** — `hand-cash-jobs-in-{prefecture}` at 10.32% CTR vs 1.94% for
   plain location pages
3. **Facebook** — hand-cash post titles at 2.48 clicks/post vs 1.07 for everything else

This should drive prioritisation: hand-cash page coverage, hand-cash-first titles,
hand-cash-led Facebook posts.

---

## 6. RECOMMENDED REPORTING SETUP

Create a saved view in Search Console and use it as the default:

- **Metric:** Clicks only
- **Country:** Japan (89% of real traffic; foreign impressions are noise)
- **Device:** Mobile (89% of real traffic)
- **Comparison:** same period previous year, not previous period

Any dashboard or report that leads with impressions, CTR, or average position for this
property is reporting on a metric that was broken for eleven months and is dominated
by rich-result surfaces nobody clicks.
