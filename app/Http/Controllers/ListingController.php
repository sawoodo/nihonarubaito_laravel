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

        $title = 'Part-time Jobs for foreigners in Japan | Nihon Arubaito';

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
            'og_description'  => 'Nihonarubaito is helping foreigners to get part-time jobs in japan, Jobs are in English, Vietnamese and Japanese find the best part-time job for you!',
            'page_description' => 'Nihonarubaito is providing best part-time jobs in Japan. Browse your favorite job (Lightwork, Restaurant, Convenience Store) through japan\'s leading job site.',
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
                    $areaSlug = strtolower(str_replace(' ', '-', $area->english));
                    return redirect("part-time-jobs-in-{$areaSlug}", 301);
                }
            }
            if ($prefectureId > 0) {
                // Empty query + prefecture (no area or area=0) → prefecture page
                $pref = Prefecture::find($prefectureId);
                if ($pref) {
                    $prefSlug = strtolower($pref->english);
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

        // Canonical → corresponding prefecture page (not search URL)
        $canonical = null;
        if ($prefectureId > 0) {
            $prefName = $prefectures[$prefectureId] ?? '';
            if ($prefName) {
                $canonical = url('part-time-jobs-in-' . strtolower($prefName));
            }
        }

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
            'og_description'  => 'Nihonarubaito is helping foreigners to get part-time jobs in japan, Jobs are in English, Vietnamese and Japanese find the best part-time job for you!',
            'page_description' => 'Nihonarubaito is providing best part-time jobs in Japan. Browse your favorite job (Lightwork, Restaurant, Convenience Store) through japan\'s leading job site.',
            'og_image'        => 'https://nihonarubaito.com/frontend/images/main-og-title.png',
            'og_url'          => 'https://nihonarubaito.com',
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

            // Try prefecture first
            $prefecture = Prefecture::whereRaw('LOWER(english) = ?', [strtolower($location)])->first();
            $prefectureId = $prefecture ? $prefecture->id : 0;

            // If no prefecture match, try area
            if (!$prefectureId) {
                $area = $this->findAreaByName($location);
                if ($area) {
                    $areaId = $area->id;
                    $prefectureId = $area->prefecture_id;
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
        $uriLocation = isset($uri['location']) ? str_replace('-', ' ', $uri['location']) : '';

        // For station pages, use the station query as the location for SEO
        if ($isStationPage && empty($uriLocation)) {
            $uriLocation = ucwords(str_replace('-', ' ', $uri['query']));
        }

        $jobQuery = ucwords(str_replace('-', ' ', $uri['query']));
        $locationName = ucwords($uriLocation);
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

        // Blog post matching this slug
        $blogPost = BlogPost::where('slug', $slug)->where('lang_id', $langId)->first();

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
        $locationSlug = strtolower(str_replace(' ', '-', $uriLocation));
        $popularLocations = self::STATION_MAP[$locationSlug] ?? $uriLocation;

        $maxLength = 100;
        if (strlen($popularLocations) > $maxLength) {
            $commaPos = strpos($popularLocations, ',', $maxLength);
            if ($commaPos !== false) {
                $popularLocations = substr($popularLocations, 0, $commaPos);
            }
            $popularLocations .= ', and more';
        }

        $ogDescription = $isStationPage
            ? "Find flexible and high-paying part-time jobs near {$uriLocation} in Japan. Browse the latest listings and apply today with Nihon Arubaito!"
            : $this->generateMetaDescription($uriLocation, $query, self::STATION_MAP);
        $keywords = "{$query} jobs in {$uriLocation}, part-time jobs, {$popularLocations}, jobs for foreigners";

        // Intro paragraph
        $introQuery = ucwords($query);
        $introParagraph = "Explore the best {$introQuery} jobs";
        $introParagraph .= $uriLocation ? " in {$uriLocation}" : '';
        $introParagraph .= ' suitable for foreigners and international students. Find flexible roles in popular areas';
        $introParagraph .= $popularLocations ? " such as {$popularLocations}." : '.';
        $introParagraph .= ' Apply today and start earning!';

        // Structured data (JSON-LD)
        $structuredData = $this->buildStructuredData($jobs, $title, $ogDescription);

        // Breadcrumb
        $breadcrumb = $this->buildBreadcrumb($prefectures, $prefectureId, $areaId, $area, $query, $uri, $langName);

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
            'breadcrumb'       => $breadcrumb,
            'active_nav'       => 'jobs',
            'neighbors'        => $neighbors,
            'faq_items'        => $faqItems,
            'prefecture_name'  => $prefecture ? $prefecture->english : null,
        ]);
    }

    /**
     * Find area by name with join to get prefecture_id (replicates CI3 Area_model::get_by_name).
     */
    private function findAreaByName(string $name): ?object
    {
        $parts = explode(' ', $name);

        $query = DB::table('areas as a')
            ->join('towns as t', 'a.town_id', '=', 't.id')
            ->join('prefectures as p', 't.prefecture_id', '=', 'p.id')
            ->select('a.*', 'p.id as prefecture_id', 'p.english as prefecture');

        if (count($parts) > 1) {
            $pattern = implode('[- ]', $parts);
            $query->whereRaw("a.english REGEXP ?", [$pattern]);
        } else {
            $query->where('a.english', 'LIKE', "%{$parts[0]}%");
        }

        return $query->first();
    }

    /**
     * Get regions with prefectures grouped by region name.
     */
    private function getRegionsGrouped(string $langName): array
    {
        $results = DB::table('regions as r')
            ->join('prefectures as p', 'r.id', '=', 'p.region_id')
            ->select("r.{$langName}", "p.id as prefecture_id", "p.{$langName} as prefecture")
            ->get();

        $regions = [];
        foreach ($results as $item) {
            $regions[$item->$langName][] = $item;
        }

        return $regions;
    }

    /**
     * Get popular areas grouped by prefecture name.
     */
    private function getPopularAreasGrouped(string $langName): array
    {
        $results = DB::table('popular_areas as pa')
            ->join('areas as a', 'pa.area_id', '=', 'a.id')
            ->join('towns as t', 'a.town_id', '=', 't.id')
            ->join('prefectures as p', 't.prefecture_id', '=', 'p.id')
            ->select("a.id as area_id", "a.{$langName} as area", "p.id as prefecture_id", "p.{$langName} as prefecture")
            ->get();

        $grouped = [];
        foreach ($results as $item) {
            $grouped[$item->prefecture][] = (object) [
                'area_id' => $item->area_id,
                'area' => $item->area,
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

        return DB::table('popular_areas as pa')
            ->join('areas as a', 'pa.area_id', '=', 'a.id')
            ->join('towns as t', 'a.town_id', '=', 't.id')
            ->join('prefectures as p', 't.prefecture_id', '=', 'p.id')
            ->where('p.id', $prefectureId)
            ->select("a.id as area_id", "a.{$langName} as area")
            ->get()
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
     * Generate SEO-friendly meta description (replicates CI3 generate_meta_description).
     */
    private function generateMetaDescription(string $location, string $query, array $locationsArray, int $charLimit = 160): string
    {
        $locationSlug = strtolower(str_replace(' ', '-', $location));
        $popularLocations = isset($locationsArray[$locationSlug])
            ? explode(',', $locationsArray[$locationSlug])
            : [$location];

        $baseDesc = "Find flexible and high-paying {$query} jobs in {$location} near popular stations like ";
        $desc = $baseDesc;

        foreach ($popularLocations as $station) {
            $station = trim($station);
            if (strlen($desc . $station . ', ') <= $charLimit - 20) {
                $desc .= "{$station}, ";
            } else {
                break;
            }
        }

        $desc = rtrim($desc, ', ') . '. Apply now with Nihon Arubaito!';

        if (strlen($desc) > $charLimit) {
            $desc = substr($desc, 0, $charLimit - 3) . '...';
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
            $jobPostings[] = [
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
                    'address' => [
                        '@type' => 'PostalAddress',
                        'streetAddress' => htmlspecialchars($job->address ?? ''),
                        'addressLocality' => htmlspecialchars($job->area_name ?? ''),
                        'addressRegion' => $job->prefecture_name ? htmlspecialchars($job->prefecture_name) : 'Japan',
                        'addressCountry' => 'JP',
                    ],
                ],
                'baseSalary' => [
                    '@type' => 'MonetaryAmount',
                    'currency' => 'JPY',
                    'value' => [
                        '@type' => 'QuantitativeValue',
                        'value' => $job->wage,
                        'unitText' => 'HOUR',
                    ],
                ],
            ];
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
    private function buildBreadcrumb(array $prefectures, int $prefectureId, int $areaId, ?object $area, string $query, array $uri, string $langName): string
    {
        $prefectureName = $prefectureId > 0 ? ($prefectures[$prefectureId] ?? '') : '';
        $querySlug = ($query && $query !== 'xxx') ? str_replace(' ', '-', $query) : '';
        $prefectureSlug = strtolower($prefectureName);

        $html = '<ol class="breadcrumb tw-mt-8">';
        $html .= '<li><a href="' . e(url('/')) . '">Home</a></li>';

        // Prefecture crumb
        if ($prefectureName) {
            $prefUrl = url("{$querySlug}-jobs-in-{$prefectureSlug}");
            $html .= '<li><a href="' . e($prefUrl) . '">' . e($prefectureName) . '</a></li>';
        }

        // Area crumb
        if ($areaId && $area) {
            $areaName = $area->$langName ?? $area->english ?? '';
            $areaSlug = strtolower(str_replace(' ', '-', $areaName));
            $areaUrl = url("{$querySlug}-jobs-in-{$areaSlug}");
            $html .= '<li><a href="' . e($areaUrl) . '">' . e($areaName) . '</a></li>';
        }

        $html .= '</ol>';

        return $html;
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
