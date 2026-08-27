# Area Slug Collision Issue

**Discovered:** 2026-07-26  
**Severity:** Medium (affects area page routing)  
**Scope:** 21 area slug collisions across 1,896 areas

## Problem

Multiple areas with different IDs slugify to the same URL path, causing Pattern 2 regex lookups (`/part-time-jobs-in-{slug}`) to be non-deterministic. `first()` returns whichever area the DB query hits first — a coin flip.

## Affected Slugs

```
nakano         → 14@Tokyo (26 jobs), 941@Nagano (1 job)
fuchu          → 29@Tokyo, 802@Hiroshima
konan          → 225@Aichi, 1834@Kochi
mino           → 170@Osaka, 1014@Gifu
miyoshi        → 803@Hiroshima, 1808@Tokushima
sakura-city    → 349@Chiba, 1096@Tochigi
date-city      → 482@Hokkaido, 1121@Fukushima
hokuto         → 485@Hokkaido, 1744@Yamanashi
+ 13 more (mostly small towns, see full list below)
```

## Impact

1. **Area pages:** `/part-time-jobs-in-nakano` may resolve to Tokyo (high-traffic) or Nagano (1 job)
2. **Hand-cash/daily-payment pages:** Same ambiguity for `hand-cash-jobs-in-{slug}`
3. **Sitemap entries:** If sitemap slug resolves to different area than Pattern 2 regex, page may be noindex

## Immediate Workaround

Exclude colliding slugs from `config/featured.php` lists:
- ~~nakano~~ removed from `daily_payment_areas` (2026-07-26)

## Long-term Solutions

### Option A: Disambiguate slugs in DB
Add `slug` column to `areas` table with unique constraint:
```php
// Migration
Schema::table('areas', function (Blueprint $table) {
    $table->string('slug', 100)->unique()->after('english');
});

// Seeder: append prefecture for collisions
foreach ($collisions as $slug => $areaIds) {
    foreach ($areaIds as $areaId) {
        $area = Area::find($areaId);
        $area->slug = Str::slug($area->english . '-' . $area->prefecture->english);
        $area->save();
    }
}
```

Result:
- `nakano` → `nakano-tokyo`
- `nakano` → `nakano-nagano`

### Option B: Scope by area ID in featured config
Instead of slugs, store area IDs:
```php
'daily_payment_areas' => [
    14,   // Nakano, Tokyo
    225,  // Konan, Aichi
    // ...
],
```

Then resolve URL → area ID explicitly:
```php
$areaId = config("featured.area_id_map.{$slug}");
```

### Option C: Accept ambiguity, pick winner
For each collision, designate the high-job-count area as canonical. Update Pattern 2 regex to:
```php
->where('id', $canonicalAreaId)
```

Trade-off: The low-job area becomes unreachable.

## Full Collision List (21 pairs)

```
date-city                         → 482@Hokkaido, 1121@Fukushima
fuchu                             → 29@Tokyo, 802@Hiroshima
futaba-county-okuma-machi         → 1163@Fukushima, 1164@Fukushima
higashichikuma-omi-village        → 989@Nagano, 1168@Fukushima
hokuto                            → 485@Hokkaido, 1744@Yamanashi
kamikita-district-of-yokohama-... → 1505@Aomori, 1545@Iwate
kamikita-district-oirase          → 1508@Aomori, 1548@Iwate
kamikita-district-rokkasho        → 1507@Aomori, 1547@Iwate
kamikita-district-rokunohe        → 1504@Aomori, 1544@Iwate
kamikita-district-tohoku-machi    → 1506@Aomori, 1546@Iwate
kiso-gun-kiso-machi               → 988@Nagano, 1167@Fukushima
kiso-gun-omma-village             → 987@Nagano, 1166@Fukushima
kiso-gun-otaki-mura               → 986@Nagano, 1165@Fukushima
konan                             → 225@Aichi, 1834@Kochi
mino                              → 170@Osaka, 1014@Gifu
miyoshi                           → 803@Hiroshima, 1808@Tokushima
nakano                            → 14@Tokyo, 941@Nagano
sakura-city                       → 349@Chiba, 1096@Tochigi
shimokita-district-higashidōri    → 1510@Aomori, 1550@Iwate
shimokita-gun-kazamaura           → 1511@Aomori, 1551@Iwate
shimokita-gun-oma-machi           → 1509@Aomori, 1549@Iwate
```

## Detection Query

```sql
SELECT 
  LOWER(REPLACE(REPLACE(REPLACE(a.english, ' ', '-'), '.', '-'), '_', '-')) as slug,
  GROUP_CONCAT(CONCAT(a.id, '@', p.english) SEPARATOR ', ') as area_ids_prefs,
  COUNT(*) as collision_count
FROM areas a
JOIN towns t ON a.town_id = t.id
JOIN prefectures p ON t.prefecture_id = p.id
GROUP BY slug
HAVING collision_count > 1
ORDER BY collision_count DESC, slug;
```

## Priority

Medium — affects 21 of 1,896 areas (1.1%). High-traffic collisions (nakano, fuchu) have workarounds. Low-traffic collisions (small towns) have minimal SEO impact.

Recommend: Option A (disambiguate in DB) as part of tag-system migration prep.
