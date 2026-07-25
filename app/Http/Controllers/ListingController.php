<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Job;
use App\Models\PopularArea;
use App\Models\Prefecture;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListingController extends Controller
{
    private const PER_PAGE = 30;

    private const STATION_MAP = [
        'tokyo'     => 'Tokyo Station, Ikebukuro, Shinjuku, Shibuya',
        'saitama'   => 'Omiya Station, Kawaguchi, Kawagoe, Urawa',
        'chiba'     => 'Kashiwa Station, Chiba, Funabashi, Matsudo',
        'kanagawa'  => 'Yokohama Station, Kawasaki',
        'osaka'     => 'Umeda Station, Namba, Tennoji, Osaka',
        'hyogo'     => 'Himeji Station, Nishinomiya, Sannomiya, Amagasaki',
        'kyoto'     => 'Kyoto Station',
        'aichi'     => 'Nagoya Station, Sakae',
        'fukuoka'   => 'Hakata Station, Tenjin',
        'ibaraki'   => 'Mito Station, Tsuchiura, Tsukuba City',
        'hiroshima' => 'Naka-ku Hiroshima-shi Station',
        'nara'      => 'Nara Station',
        'gifu'      => 'Gifu Station',
        'shizuoka'  => 'Hamamatsu Station, Shizuoka',
        'gunma'     => 'Maebashi Station',
        'miyagi'    => 'Sendai Station, Hirose-dori',
        'tochigi'   => 'Utsunomiya Station, Mito',
        'okayama'   => 'Okayama Station, Kurashiki',
        'hokkaido'  => 'Sapporo Station, Susukino',
    ];

    /**
     * Main /jobs listing page.
     */
    public function index(Request $request, int $page = null)
    {
        $langId = (int) session('user_lang', 1);
        $langName = session('lang_name', 'english');

        $page = max(1, $page ?? 1);
        $offset = ($page - 1) * self::PER_PAGE;

        $totalRows = Job::allForFrontend($langName, $langId)->count();
        $jobs = Job::allForFrontend($langName, $langId)
            ->skip($offset)
            ->take(self::PER_PAGE)
            ->get();

        // Regions grouped by region name (English only, for sidebar nav)
        $regions = $this->getRegionsGrouped($langName);

        // Popular areas grouped by prefecture
        $popularAreas = $this->getPopularAreasGrouped($langName);

        // Prefecture dropdown
        $prefectures = $this->getPrefectureDropdown($langName);

        // Categories for search form
        $jobCategories = Category::all();

        $title = 'Part-Time Jobs in Japan for Foreigners | Nihon Arubaito';

        return view('listings.index', [
            'jobs'            => $jobs,
            'regions'         => $regions,
            'popular_areas'   => $popularAreas,
            'prefectures'     => $prefectures,
            'job_categories'  => $jobCategories,
            'query'           => '',
            'prefecture_id'   => '',
            'area_id'         => 0,
            'selected_cats'   => [],
            'pagination'      => $this->buildPagination(url('jobs/page'), $totalRows, self::PER_PAGE, $page),
            'breadcrumb'      => '',
            'page_title'      => $title,
            'og_title'        => $title,
            'og_description'  => 'Part-time & hand cash jobs across Japan for foreign residents. Browse 3,000+ job listings in Tokyo, Osaka, Saitama and beyond.',
            'og_type'         => 'website',
            'page_description' => 'Find part-time & hand cash jobs near you in Japan — restaurant, warehouse, hotel cleaning across all 47 prefectures. For foreigners, no kanji required.',
            'og_image'        => 'https://nihonarubaito.com/frontend/images/main-og-title.png',
            'og_url'          => 'https://nihonarubaito.com/',
            'canonical'       => 'https://nihonarubaito.com/',
            'keywords'        => 'Find Part time jobs in japan, Find Work in Japan, jobs Opportunities japan, Part time job portal in japan, Nihon Arubaito, Baito, Jobs for foreigners in japan',
            'active_nav'      => 'jobs',
        ]);
    }

    /**
     * Search: /jobs/search?query=&prefecture_id=X&area_id=Y&categories[]=Z
     */
    public function search(Request $request, int $page = null)
    {
        $langId = (int) session('user_lang', 1);
        $langName = session('lang_name', 'english');

        $query = str_replace('-', ' ', $request->input('query', ''));
        $prefectureId = (int) $request->input('prefecture_id', 0);
        $areaId = (int) $request->input('area_id', 0);
        $categories = is_array($request->input('categories')) ? $request->input('categories') : [];

        // 301 redirect: empty query → clean URL (prevents duplicate content indexing)
        if ($query === '') {
            if ($prefectureId > 0 && $areaId > 0) {
                // Empty query + prefecture + area → area page
                $area = DB::table('areas')->where('id', $areaId)->first();
                if ($area) {
                    $areaSlug = Str::slug($area->english);
                    return redirect("part-time-jobs-in-{$areaSlug}", 301);
                }
            }
            if ($prefectureId > 0) {
                // Empty query + prefecture (no area or area=0) → prefecture page
                $pref = Prefecture::find($prefectureId);
                if ($pref) {
                    $prefSlug = Str::slug($pref->english);
                    return redirect("part-time-jobs-in-{$prefSlug}", 301);
                }
            }
            // Empty query + no prefecture → main jobs page
            return redirect('jobs', 301);
        }

        // Category mixing (bed making ↔ light work)
        if (!empty($categories) && in_array(4, $categories)) {
            $categories[] = '1';
        } elseif (!empty($categories) && in_array(1, $categories)) {
            $categories[] = '4';
        }

        $page = max(1, $page ?? 1);
        $offset = ($page - 1) * self::PER_PAGE;

        $totalRows = Job::search($langId, $langName, $query, $prefectureId, $areaId, $categories)->count();
        $jobs = Job::search($langId, $langName, $query, $prefectureId, $areaId, $categories)
            ->skip($offset)
            ->take(self::PER_PAGE)
            ->get();

        // Pagination with query string preserved
        $queryString = $request->getQueryString();
        $pagination = $this->buildPagination(url('jobs/search/page'), $totalRows, self::PER_PAGE, $page, $queryString ?? '');

        $regions = $this->getRegionsGrouped($langName);
        $popularAreas = $this->getPopularAreasGrouped($langName);
        $prefectures = $this->getPrefectureDropdown($langName);
        $jobCategories = Category::all();

        $title = 'Nihon Arubaito | Part-time Jobs for foreigners';
        if ($jobs->isNotEmpty()) {
            $title .= ' in ' . $jobs->first()->prefecture_name;
        }

        // Self-referencing canonical (noindex prevents indexing, no conflict with prefecture pages)
        $canonical = url()->current();

        return view('listings.index', [
            'jobs'            => $jobs,
            'regions'         => $regions,
            'popular_areas'   => $popularAreas,
            'prefectures'     => $prefectures,
            'job_categories'  => $jobCategories,
            'query'           => $query,
            'prefecture_id'   => $prefectureId,
            'area_id'         => $areaId,
            'selected_cats'   => $categories,
            'pagination'      => $pagination,
            'breadcrumb'      => '',
            'page_title'      => $title,
            'og_title'        => $title,
            'og_description'  => 'Search part-time jobs in Japan for foreigners. Filter by prefecture, area, and job category on Nihon Arubaito.',
            'page_description' => 'Search part-time jobs in Japan for foreigners. Filter by prefecture, area, and category. Restaurant, warehouse, hotel cleaning, and convenience store positions available.',
            'og_image'        => 'https://nihonarubaito.com/frontend/images/main-og-title.png',
            'og_url'          => $canonical ?? 'https://nihonarubaito.com/',
            'keywords'        => 'Find Part time jobs in japan, Find Work in Japan, jobs Opportunities japan, Part time job portal in japan, Nihon Arubaito, Baito, Jobs for foreigners in japan',
            'canonical'       => $canonical,
            'noindex'         => true,
            'active_nav'      => 'jobs',
        ]);
    }

    /**
     * Catch-all slug-based listing: prefecture, station, and area pages.
     */
    public function bySlug(Request $request, string $slug, int $page = null)
    {
        // 301 redirect uppercase slugs to lowercase (SEO canonical)
        $lowerSlug = strtolower($slug);
        if ($slug !== $lowerSlug) {
            $url = $page ? "{$lowerSlug}/page/{$page}" : $lowerSlug;
            return redirect($url, 301);
        }

        $langId = (int) session('user_lang', 1);
        $langName = session('lang_name', 'english');

        $uri = null;

        // Pattern 1: jobs-at-{station} (station pages)
        if (preg_match('/(?:[\w-]+)(?P<job>job|jobs)(?:-(?P<preposition>at)-)(?P<query>[\w-]+)/', $slug, $uri)) {
            if ($uri['job'] === 'job') {
                return redirect("jobs-{$uri['preposition']}-{$uri['query']}", 301);
            }
        }

        // Pattern 2: {query}-jobs-in-{location} (prefecture/area pages)
        if (!$uri && preg_match('/(?P<query>[\w-]+)-(?P<job>job|jobs)(?:-in-)(?P<location>[\w-]+)/', $slug, $uri)) {
            if ($uri['job'] === 'job') {
                return redirect("{$uri['query']}-jobs-in-{$uri['location']}", 301);
            }
        }

        if (!$uri) {
            abort(404);
        }

        $prefectureId = 0;
        $prefecture = null;
        $areaId = 0;
        $area = null;
        $popularAreas = null;

        // Resolve location to prefecture/area
        if (isset($uri['location'])) {
            $location = str_replace('-', ' ', $uri['location']);

            // 410 Gone for garbage URLs (crawler artifacts with dropdown placeholder text)
            if (preg_match('/\b(select|please)\b/i', $location)) {
                abort(410);
            }

            // Try prefecture first
            $prefecture = Prefecture::whereRaw('LOWER(english) = ?', [strtolower($location)])->first();
            $prefectureId = $prefecture ? $prefecture->id : 0;

            // If no prefecture match, try area
            if (!$prefectureId) {
                $area = $this->findAreaByName($location);
                if ($area) {
                    $areaId = $area->id;
                    $prefectureId = $area->prefecture_id;

                    // 301 redirect if URL slug doesn't match canonical area slug (SEO: avoid duplicate content)
                    $canonicalAreaSlug = Str::slug($area->english);
                    if (strtolower($uri['location']) !== $canonicalAreaSlug) {
                        $canonicalSlug = "{$uri['query']}-{$uri['job']}-in-{$canonicalAreaSlug}";
                        $canonicalUrl = $page && $page > 1 ? "{$canonicalSlug}/page/{$page}" : $canonicalSlug;
                        return redirect($canonicalUrl, 301);
                    }
                }
            }

            // If location was specified but nothing matched, 404 (prevents junk URLs like "please-select")
            // Exception: "japan" means nationwide (category pages like bed-making-jobs-in-japan)
            if (!$prefectureId && !$areaId && strtolower($location) !== 'japan') {
                abort(404);
            }

            // Load popular areas for the matched prefecture
            $popularAreas = $this->getPopularAreasForPrefecture($prefectureId, $langName);
        }

        $isStationPage = isset($uri['preposition']) && $uri['preposition'] === 'at';
        $query = str_replace('-', ' ', $uri['query']);
        $uriLocation = isset($uri['location']) ? ucwords(str_replace('-', ' ', $uri['location'])) : '';

        // For station pages, use the station query as the location for SEO
        if ($isStationPage && empty($uriLocation)) {
            $uriLocation = ucwords(str_replace('-', ' ', $uri['query']));
        }

        $jobQuery = ucwords(str_replace('-', ' ', $uri['query']));
        $locationName = $uriLocation; // already ucwords
        $title = $isStationPage
            ? "Part-Time Jobs near {$locationName}"
            : "{$jobQuery} Jobs" . ($locationName ? " in {$locationName}" : '');

        $page = max(1, $page ?? 1);
        $offset = ($page - 1) * self::PER_PAGE;

        $totalRows = Job::search($langId, $langName, $query, $prefectureId, $areaId)->count();
        $jobs = Job::search($langId, $langName, $query, $prefectureId, $areaId)
            ->skip($offset)
            ->take(self::PER_PAGE)
            ->get();

        // Blog post matching this slug (with dynamic data replacement)
        $blogPost = BlogPost::where('slug', $slug)->where('lang_id', $langId)->first();
        if ($blogPost && $prefectureId && str_contains($blogPost->post ?? '', '{{')) {
            $dynamicData = $this->getPrefectureDynamicData($prefectureId);
            $blogPost->post = $this->replaceBlogTemplateVars($blogPost->post, $dynamicData);
        }

        // Nearby prefectures (only for prefecture pages, not station/area pages)
        $neighbors = collect();
        if ($prefecture) {
            $neighbors = DB::table('prefecture_neighbors')
                ->where('prefecture_slug', $slug)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($n) {
                    $name = str_replace('part-time-jobs-in-', '', $n->neighbor_slug);
                    return (object) [
                        'slug' => $n->neighbor_slug,
                        'name' => ucwords(str_replace('-', ' ', $name)),
                    ];
                });
        }

        // FAQ extraction for structured data
        $faqItems = [];
        if ($blogPost && str_contains($blogPost->post ?? '', 'Frequently Asked Questions')) {
            preg_match_all('/<h3[^>]*>(.*?)<\/h3>\s*<p[^>]*>(.*?)<\/p>/s', $blogPost->post, $matches);
            $inFaq = false;
            foreach ($matches[1] as $i => $question) {
                $q = strip_tags(html_entity_decode($question));
                $a = strip_tags(html_entity_decode($matches[2][$i]));
                if (str_contains($q, 'Frequently Asked') || str_contains($q, 'FAQ')) {
                    $inFaq = true;
                    continue;
                }
                if ($inFaq && strlen($q) > 10 && str_contains($q, '?')) {
                    $faqItems[] = ['question' => $q, 'answer' => $a];
                }
            }
        }

        // Prefecture dropdown
        $prefectures = $this->getPrefectureDropdown($langName);

        // Categories
        $jobCategories = Category::all();

        // SEO: station map for meta description
        $locationSlug = Str::slug($uriLocation);
        $popularLocations = self::STATION_MAP[$locationSlug] ?? $uriLocation;

        $maxLength = 100;
        if (strlen($popularLocations) > $maxLength) {
            $commaPos = strpos($popularLocations, ',', $maxLength);
            if ($commaPos !== false) {
                $popularLocations = substr($popularLocations, 0, $commaPos);
            }
            $popularLocations .= ', and more';
        }

        // Min wage for meta description
        $minWage = 0;
        if ($jobs->isNotEmpty()) {
            $rawWage = $jobs->min('wage');
            preg_match('/[0-9,]+/', $rawWage ?? '', $m);
            $minWage = isset($m[0]) ? (int) str_replace(',', '', $m[0]) : 0;
        }

        $ogDescription = $isStationPage
            ? "Find part-time jobs near {$locationName} for foreigners." . ($totalRows > 0 ? " " . number_format($totalRows) . " active listings." : '') . ($minWage > 0 ? " Jobs from ¥" . number_format($minWage) . "/hr." : '') . " Apply on Nihon Arubaito."
            : $this->generateMetaDescription($locationName, $query, self::STATION_MAP, $totalRows, $minWage);
        $keywords = "{$query} jobs in {$uriLocation}, part-time jobs, {$popularLocations}, jobs for foreigners";

        // Intro paragraph
        $introQuery = ucwords($query);
        $introParagraph = "Explore the best {$introQuery} jobs";
        $introParagraph .= $locationName ? " in {$locationName}" : '';
        $introParagraph .= ' suitable for foreigners and international students. Find flexible roles in popular areas';
        $introParagraph .= $popularLocations ? " such as {$popularLocations}." : '.';
        $introParagraph .= ' Apply today and start earning!';

        // Structured data (JSON-LD)
        $structuredData = $this->buildStructuredData($jobs, $title, $ogDescription);

        // Breadcrumb
        $breadcrumbData = $this->buildBreadcrumb($prefectures, $prefectureId, $areaId, $area, $query, $uri, $langName);

        // Pagination base URL
        $paginationBaseUrl = url("{$slug}/page");

        // SEO fix: self-referencing canonical
        $canonical = $page > 1 ? url("{$slug}/page/{$page}") : url($slug);

        // SEO fix: page suffix in title for page 2+
        $pageTitle = $page > 1
            ? "{$title} - Page {$page} | Nihon Arubaito"
            : "{$title} | Nihon Arubaito";

        // URL pattern for popular area links
        $urlPattern = "{$uri['query']}-{$uri['job']}-in-";

        // Noindex only pages that are genuinely empty.
        // Three guards, evaluated left-to-right so pages WITH jobs skip the rest entirely.
        //
        //   1. $totalRows === 0        — nothing to show
        //   2. !$hasEditorialContent   — no written content either (protects future area content)
        //   3. !$isPrefecturePage      — hard floor: the 47 prefecture pages are never deindexed
        //
        // Guard 3 exists because a transient query failure on a top-ranking page
        // (e.g. Tokyo) would otherwise emit noindex and get cached for 30 minutes.
        $hasEditorialContent = !empty($blogPost)
            && strlen(strip_tags($blogPost->post ?? '')) > 500;

        $isPrefecturePage = $prefecture !== null
            && $areaId === 0
            && !$isStationPage
            && $query === 'part time';

        $noindex = ($totalRows === 0) && !$hasEditorialContent && !$isPrefecturePage;

        // Don't cache a noindex response — otherwise the tag survives up to 30 minutes
        // after jobs return. These pages have no traffic, so the cache loss costs nothing.
        if ($noindex) {
            $request->attributes->remove('cache_max_age');
        }

        return view('listings.by-slug', [
            'jobs'             => $jobs,
            'popular_areas'    => $prefecture ? $popularAreas : null,
            'url'              => $urlPattern,
            'blog_post'        => $blogPost,
            'query'            => $query,
            'prefecture_id'    => $prefectureId,
            'area_id'          => $areaId,
            'selected_cats'    => [],
            'prefectures'      => $prefectures,
            'job_categories'   => $jobCategories,
            'pagination'       => $this->buildPagination($paginationBaseUrl, $totalRows, self::PER_PAGE, $page),
            'page_title'       => $pageTitle,
            'og_title'         => $title,
            'og_description'   => $ogDescription,
            'page_description' => $ogDescription,
            'og_image'         => url('frontend/images/main-og-title.png'),
            'og_url'           => $request->url(),
            'canonical'        => $canonical,
            'keywords'         => $keywords,
            'page_heading'     => $title,
            'intro_paragraph'  => $introParagraph,
            'structured_data'  => $structuredData,
            'breadcrumb'       => $breadcrumbData['html'],
            'breadcrumbItems'  => $breadcrumbData['items'],
            'active_nav'       => 'jobs',
            'neighbors'        => $neighbors,
            'faq_items'        => $faqItems,
            'prefecture_name'  => $prefecture ? $prefecture->english : null,
            'noindex'          => $noindex,
        ]);
    }

    /**
     * Find area by name with join to get prefecture_id (replicates CI3 Area_model::get_by_name).
     */
    private function findAreaByName(string $name): ?object
    {
        $parts = explode(' ', $name);

        $baseQuery = fn() => DB::table('areas as a')
            ->join('towns as t', 'a.town_id', '=', 't.id')
            ->join('prefectures as p', 't.prefecture_id', '=', 'p.id')
            ->select('a.*', 'p.id as prefecture_id', 'p.english as prefecture');

        if (count($parts) > 1) {
            // Try exact REGEXP first (e.g. "chiyoda ku" → "chiyoda[- ]ku")
            $pattern = implode('[- ]', $parts);
            $result = $baseQuery()->whereRaw("a.english REGEXP ?", [$pattern])->first();

            // Fallback: LIKE on first word only (e.g. "chiyoda ward" → finds "Chiyoda-ku")
            if (!$result) {
                $result = $baseQuery()->where('a.english', 'LIKE', "{$parts[0]}%")->first();
            }

            return $result;
        }

        return $baseQuery()->where('a.english', 'LIKE', "%{$parts[0]}%")->first();
    }

    /**
     * Get regions with prefectures grouped by region name.
     */
    private function getRegionsGrouped(string $langName): array
    {
        $select = ["r.{$langName}", "p.id as prefecture_id", "p.english as prefecture_slug"];
        if ($langName !== 'english') {
            $select[] = "p.{$langName} as prefecture";
        }

        $results = DB::table('regions as r')
            ->join('prefectures as p', 'r.id', '=', 'p.region_id')
            ->select($select)
            ->get();

        $regions = [];
        foreach ($results as $item) {
            $item->prefecture = $item->prefecture ?? $item->prefecture_slug;
            $regions[$item->$langName][] = $item;
        }

        return $regions;
    }

    /**
     * Get popular areas grouped by prefecture name.
     */
    private function getPopularAreasGrouped(string $langName): array
    {
        $select = ["a.id as area_id", "a.english as area_slug", "p.id as prefecture_id", "p.english as prefecture_slug"];
        if ($langName !== 'english') {
            $select[] = "a.{$langName} as area";
            $select[] = "p.{$langName} as prefecture";
        }

        $results = DB::table('popular_areas as pa')
            ->join('areas as a', 'pa.area_id', '=', 'a.id')
            ->join('towns as t', 'a.town_id', '=', 't.id')
            ->join('prefectures as p', 't.prefecture_id', '=', 'p.id')
            ->select($select)
            ->get();

        $grouped = [];
        foreach ($results as $item) {
            $prefName = $item->prefecture ?? $item->prefecture_slug;
            $grouped[$prefName][] = (object) [
                'area_id' => $item->area_id,
                'area' => $item->area ?? $item->area_slug,
                'area_slug' => $item->area_slug,
            ];
        }

        return $grouped;
    }

    /**
     * Get popular areas for a specific prefecture (not grouped).
     */
    private function getPopularAreasForPrefecture(int $prefectureId, string $langName): array
    {
        if ($prefectureId <= 0) {
            return [];
        }

        $select = ["a.id as area_id", "a.english as area_slug"];
        if ($langName !== 'english') {
            $select[] = "a.{$langName} as area";
        }

        return DB::table('popular_areas as pa')
            ->join('areas as a', 'pa.area_id', '=', 'a.id')
            ->join('towns as t', 'a.town_id', '=', 't.id')
            ->join('prefectures as p', 't.prefecture_id', '=', 'p.id')
            ->where('p.id', $prefectureId)
            ->select($select)
            ->get()
            ->map(fn ($item) => (object) [
                'area_id' => $item->area_id,
                'area' => $item->area ?? $item->area_slug,
                'area_slug' => $item->area_slug,
            ])
            ->all();
    }

    /**
     * Get prefecture dropdown array [id => name].
     */
    private function getPrefectureDropdown(string $langName): array
    {
        $prefectures = Prefecture::all();
        $list = [0 => 'Please select'];
        foreach ($prefectures as $p) {
            $list[$p->id] = $p->$langName;
        }
        return $list;
    }

    /**
     * Get dynamic data for a prefecture to replace template variables in blog content.
     * Cached for 1 hour to minimize DB load.
     */
    private function getPrefectureDynamicData(int $prefectureId): array
    {
        if ($prefectureId <= 0) {
            return [];
        }

        $cacheKey = "prefecture_dynamic_{$prefectureId}";

        return cache()->remember($cacheKey, 3600, function () use ($prefectureId) {
            $jobs = DB::table('jobs')
                ->where('prefecture_id', $prefectureId)
                ->where('job_status_id', 3)
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN job_category_id = 1 THEN 1 ELSE 0 END) as packing,
                    SUM(CASE WHEN job_category_id = 2 THEN 1 ELSE 0 END) as restaurant,
                    SUM(CASE WHEN job_category_id = 3 THEN 1 ELSE 0 END) as konbini,
                    SUM(CASE WHEN job_category_id = 4 THEN 1 ELSE 0 END) as bedmaking,
                    SUM(CASE WHEN job_category_id = 5 THEN 1 ELSE 0 END) as delivery
                ")
                ->first();

            // Wage range: parse numeric values from strings like "1,200円" or "1200円～1500円"
            $wageRows = DB::table('jobs')
                ->where('prefecture_id', $prefectureId)
                ->where('job_status_id', 3)
                ->where('wage_type_id', 1)
                ->pluck('wage');

            $parsedWages = $wageRows->map(function ($w) {
                preg_match('/[\d,]+/', $w ?? '', $m);
                return isset($m[0]) ? (int) str_replace(',', '', $m[0]) : 0;
            })->filter(fn($w) => $w > 500);

            $minWage = $parsedWages->min() ?: 0;
            $maxWage = $parsedWages->max() ?: 0;

            // Subscribers interested in this prefecture
            $subscribers = DB::table('job_location_preferences')
                ->where('prefecture_id', $prefectureId)
                ->distinct('user_id')
                ->count('user_id');

            // Total conversions (join via job_no)
            $conversions = DB::table('application_logs')
                ->join('jobs', 'application_logs.job_no', '=', 'jobs.job_no')
                ->where('jobs.prefecture_id', $prefectureId)
                ->count();

            $secondaryConversions = DB::table('secondary_applies')
                ->join('jobs', 'secondary_applies.job_no', '=', 'jobs.job_no')
                ->where('jobs.prefecture_id', $prefectureId)
                ->count();

            // Restaurant conversions
            $restaurantConversions = DB::table('application_logs')
                ->join('jobs', 'application_logs.job_no', '=', 'jobs.job_no')
                ->where('jobs.prefecture_id', $prefectureId)
                ->where('jobs.job_category_id', 2)
                ->count();

            $restaurantSecondary = DB::table('secondary_applies')
                ->join('jobs', 'secondary_applies.job_no', '=', 'jobs.job_no')
                ->where('jobs.prefecture_id', $prefectureId)
                ->where('jobs.job_category_id', 2)
                ->count();

            return [
                'active_jobs' => number_format($jobs->total ?? 0),
                'active_restaurant' => number_format($jobs->restaurant ?? 0),
                'active_packing' => number_format($jobs->packing ?? 0),
                'active_bedmaking' => number_format($jobs->bedmaking ?? 0),
                'active_konbini' => number_format($jobs->konbini ?? 0),
                'active_delivery' => number_format($jobs->delivery ?? 0),
                'min_wage' => number_format($minWage),
                'max_wage' => number_format($maxWage),
                'total_subscribers' => number_format($subscribers),
                'applications' => number_format($secondaryConversions),
                'total_conversions' => number_format($conversions + $secondaryConversions),
                'restaurant_conversions' => number_format($restaurantConversions + $restaurantSecondary),
            ];
        });
    }

    /**
     * Replace {{template_vars}} in blog post content with live data.
     */
    private function replaceBlogTemplateVars(?string $content, array $dynamicData): string
    {
        if (empty($content) || empty($dynamicData) || !str_contains($content, '{{')) {
            return $content ?? '';
        }

        foreach ($dynamicData as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    /**
     * Generate SEO-friendly meta description (replicates CI3 generate_meta_description).
     */
    private function generateMetaDescription(string $location, string $query, array $locationsArray, int $jobCount = 0, int $minWage = 0): string
    {
        $locationSlug = Str::slug($location);
        $stations = isset($locationsArray[$locationSlug])
            ? explode(',', $locationsArray[$locationSlug])
            : [];

        $desc = "Find {$query} jobs in {$location} for foreigners.";

        if ($jobCount > 0) {
            $desc .= " " . number_format($jobCount) . " active listings";
            if (!empty($stations)) {
                $stationStr = trim($stations[0]);
                if (isset($stations[1])) {
                    $stationStr .= ', ' . trim($stations[1]);
                }
                $desc .= " near {$stationStr}";
            }
            $desc .= '.';
        }

        if ($minWage > 0) {
            $desc .= " Restaurant, warehouse, hotel cleaning jobs from ¥" . number_format($minWage) . "/hr.";
        } else {
            $desc .= " Restaurant, warehouse, and hotel cleaning jobs available.";
        }

        $desc .= " Apply on Nihon Arubaito.";

        if (strlen($desc) > 160) {
            $desc = substr($desc, 0, 157) . '...';
        }

        return $desc;
    }

    /**
     * Build Bootstrap 3 pagination HTML (replicates CI3 my_pagination).
     */
    private function buildPagination(string $baseUrl, int $totalRows, int $perPage, int $currentPage, string $queryString = ''): string
    {
        $totalPages = (int) ceil($totalRows / $perPage);

        if ($totalPages <= 1) {
            return '';
        }

        $qs = $queryString !== '' ? '?' . $queryString : '';
        $numLinks = 4;
        $html = '<ul class="pagination pagination-sm">';

        // First link
        if ($currentPage > 1) {
            $html .= '<li><a href="' . e($baseUrl) . '/1' . e($qs) . '"><span class="glyphicon glyphicon-fast-backward"></span></a></li>';
        }

        // Previous link
        if ($currentPage > 1) {
            $html .= '<li><a href="' . e($baseUrl) . '/' . ($currentPage - 1) . e($qs) . '"><span class="glyphicon glyphicon-step-backward"></span></a></li>';
        }

        // Page number links
        $start = max(1, $currentPage - $numLinks);
        $end = min($totalPages, $currentPage + $numLinks);

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $currentPage) {
                $html .= '<li class="active"><span>' . $i . '</span></li>';
            } else {
                $html .= '<li><a href="' . e($baseUrl) . '/' . $i . e($qs) . '">' . $i . '</a></li>';
            }
        }

        // Next link
        if ($currentPage < $totalPages) {
            $html .= '<li><a href="' . e($baseUrl) . '/' . ($currentPage + 1) . e($qs) . '"><span class="glyphicon glyphicon-step-forward"></span></a></li>';
        }

        // Last link
        if ($currentPage < $totalPages) {
            $html .= '<li><a href="' . e($baseUrl) . '/' . $totalPages . e($qs) . '"><span class="glyphicon glyphicon-fast-forward"></span></a></li>';
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Build JSON-LD WebPage structured data (replicates CI3 structured_data).
     */
    private function buildStructuredData($jobs, string $pageHeading, string $pageDescription): string
    {
        $jobPostings = [];

        foreach ($jobs as $job) {
            // Parse baseSalary using static helper (works on stdClass from joins)
            $baseSalaryValue = Job::parseBaseSalaryLd($job->wage);

            $address = [
                '@type' => 'PostalAddress',
                'streetAddress' => htmlspecialchars($job->address ?? ''),
                'addressLocality' => htmlspecialchars($job->area_name ?? ''),
                'addressRegion' => $job->prefecture_name ? htmlspecialchars($job->prefecture_name) : 'Japan',
                'addressCountry' => 'JP',
            ];
            if (!empty($job->area_postal_code)) {
                $address['postalCode'] = $job->area_postal_code;
            }

            $jobPosting = [
                '@type' => 'JobPosting',
                'title' => htmlspecialchars($job->title),
                'description' => htmlspecialchars(substr(strip_tags($job->description), 0, 160)),
                'employmentType' => 'PART_TIME',
                'datePosted' => $job->updated_at ? date('Y-m-d', strtotime($job->updated_at)) : '',
                'validThrough' => $job->updated_at ? date('Y-m-d', strtotime('+30 days', strtotime($job->updated_at))) : '',
                'hiringOrganization' => [
                    '@type' => 'Organization',
                    'name' => 'Nihon Arubaito',
                    'sameAs' => url('/'),
                ],
                'jobLocation' => [
                    '@type' => 'Place',
                    'address' => $address,
                ],
            ];

            // Add baseSalary only if parseable (absent is valid; malformed is penalized)
            if ($baseSalaryValue) {
                $jobPosting['baseSalary'] = [
                    '@type' => 'MonetaryAmount',
                    'currency' => 'JPY',
                    'value' => $baseSalaryValue,
                ];
            }

            $jobPostings[] = $jobPosting;
        }

        return json_encode([
            '@context' => 'https://schema.org/',
            '@type' => 'WebPage',
            'name' => $pageHeading,
            'description' => $pageDescription,
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Nihon Arubaito',
                'url' => url('/'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => url('frontend/images/logo.webp'),
                ],
            ],
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => $jobPostings,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Build breadcrumb HTML for slug-based listing pages.
     */
    private function buildBreadcrumb(array $prefectures, int $prefectureId, int $areaId, ?object $area, string $query, array $uri, string $langName): array
    {
        $prefectureName = $prefectureId > 0 ? ($prefectures[$prefectureId] ?? '') : '';
        $querySlug = ($query && $query !== 'xxx') ? str_replace(' ', '-', $query) : '';
        $prefectureSlug = Str::slug($prefectureName);

        $items = [['name' => 'Home', 'url' => url('/')]];

        $html = '<ol class="breadcrumb tw-mt-8">';
        $html .= '<li><a href="' . e(url('/')) . '">Home</a></li>';

        // Prefecture crumb
        if ($prefectureName) {
            $prefUrl = url("{$querySlug}-jobs-in-{$prefectureSlug}");
            $html .= '<li><a href="' . e($prefUrl) . '">' . e($prefectureName) . '</a></li>';
            $items[] = ['name' => $prefectureName, 'url' => $prefUrl];
        }

        // Area crumb
        if ($areaId && $area) {
            $areaName = $area->$langName ?? $area->english ?? '';
            $areaSlug = Str::slug($areaName);
            $areaUrl = url("{$querySlug}-jobs-in-{$areaSlug}");
            $html .= '<li><a href="' . e($areaUrl) . '">' . e($areaName) . '</a></li>';
            $items[] = ['name' => $areaName, 'url' => $areaUrl];
        }

        $html .= '</ol>';

        return ['html' => $html, 'items' => $items];
    }

    /**
     * AJAX endpoint: POST /jobs/areas/get — returns areas for a prefecture.
     */
    public function getAreas(Request $request)
    {
        $prefectureId = (int) $request->input('prefecture_id', 0);
        $langName = session('lang_name', 'english');

        if (!$prefectureId) {
            return response()->json(['status' => 'error']);
        }

        $areas = DB::table('areas as a')
            ->join('towns as t', 'a.town_id', '=', 't.id')
            ->where('t.prefecture_id', $prefectureId)
            ->select('a.id', "a.{$langName} as name")
            ->orderBy('a.id')
            ->get();

        // Format as array of [id, name] to match CI3 response
        $formatted = $areas->map(fn($a) => [$a->id, $a->name])->values()->toJson();

        return response()->json(['status' => 'ok', 'areas' => $formatted])
            ->header('Cache-Control', 'public, max-age=86400')
            ->header('Vary', 'Accept');
    }
}
