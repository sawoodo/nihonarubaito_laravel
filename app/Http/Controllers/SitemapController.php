<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SitemapController extends Controller
{
    private const STATION_SLUGS = [
        'namba', 'umeda', 'tennoji', 'osaka', 'shinsaibashi',
        'tokyo', 'shinjuku', 'ikebukuro', 'shibuya',
        'kyoto', 'kyoto-kawaramachi',
        'hakata', 'tenjin',
        'nagoya', 'toyohashi', 'sakae',
        'utsunomiya', 'sendai', 'hirose-dori',
        'omiya', 'kawaguchi', 'kawasaki', 'yokohama',
        'sannomiya', 'himeji',
        'funabashi', 'matsudo', 'kashiwa',
        'maebashi', 'gifu', 'hamamatsu', 'shizuoka',
        // Clean-ratio additions (strict ≈ loose, no collisions)
        'hatchobori', 'kanayama', 'yodoyabashi', 'hommachi', 'urawa', 'akihabara', 'kyobashi', 'oyama',
    ];

    public function xml()
    {
        // Serve pre-generated sitemap if available (faster, avoids DB queries)
        $cachedPath = storage_path('app/sitemap.xml');
        if (file_exists($cachedPath) && filemtime($cachedPath) > time() - 86400) {
            return response(file_get_contents($cachedPath), 200, [
                'Content-Type' => 'application/xml; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-cache',
                'X-SG-Exclude-Cache' => '1',
            ]);
        }
        $langId = (int) session('user_lang', 1);
        $langName = session('lang_name', 'english');

        // All active jobs
        $jobs = Job::withLocalizedNames($langName)
            ->where('jobs.job_status_id', Job::STATUS_PUBLISHED)
            ->orderBy('jobs.featured', 'desc')
            ->orderBy('jobs.updated_at', 'desc')
            ->get();

        // Areas that have active jobs, grouped by prefecture
        $areasRaw = DB::table('areas as a')
            ->join('towns as t', 'a.town_id', '=', 't.id')
            ->join('prefectures as p', 't.prefecture_id', '=', 'p.id')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('jobs')
                    ->whereColumn('jobs.area_id', 'a.id')
                    ->where('jobs.job_status_id', Job::STATUS_PUBLISHED);
            })
            ->select('a.id', 'a.english as area', 'p.id as prefecture_id', 'p.english as prefecture')
            ->get();

        $areas = [];
        foreach ($areasRaw as $row) {
            $key = "{$row->prefecture}|{$row->prefecture_id}";
            $areas[$key][] = $row;
        }
        ksort($areas);

        // Build XML
        $baseUrl = url('/');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Static pages
        $xml .= $this->urlEntry("{$baseUrl}/", null, '1.0', 'daily');
        $xml .= $this->urlEntry("{$baseUrl}/subscribe", null, '0.3', 'monthly');
        $xml .= $this->urlEntry("{$baseUrl}/contact", null, '0.3', 'monthly');
        $xml .= $this->urlEntry("{$baseUrl}/privacy-policy", null, '0.3', 'monthly');
        $xml .= $this->urlEntry("{$baseUrl}/terms-of-service", null, '0.3', 'monthly');
        $xml .= $this->urlEntry("{$baseUrl}/faq", null, '0.3', 'monthly');

        // Job detail pages
        foreach ($jobs as $job) {
            $slug = Str::slug("english-{$job->title}");
            $loc = "{$baseUrl}/jobs/{$job->job_no}/detail/{$slug}";
            $lastmod = date('Y-m-d', strtotime($job->date));
            $xml .= $this->urlEntry($loc, $lastmod, '0.5', 'weekly');
        }

        // Category pages
        foreach (['dish-washing-jobs-in-japan', 'sorting-jobs-in-japan', 'bed-making-jobs-in-japan'] as $catSlug) {
            $xml .= $this->urlEntry("{$baseUrl}/{$catSlug}", null, '0.9', 'daily');
        }

        // Hand-cash jobs by area (high-converting modifier + sustained inventory)
        foreach (config('featured.hand_cash_areas', []) as $areaSlug) {
            $xml .= $this->urlEntry("{$baseUrl}/hand-cash-jobs-in-{$areaSlug}", null, '0.8', 'daily');
        }

        // Daily payment jobs by area (high-converting modifier + sustained inventory)
        foreach (config('featured.daily_payment_areas', []) as $areaSlug) {
            $xml .= $this->urlEntry("{$baseUrl}/daily-payment-jobs-in-{$areaSlug}", null, '0.8', 'daily');
        }

        // Prefecture-level modifier pages, driven by live inventory.
        // Only prefectures with jobs are listed — an empty page would emit
        // noindex and GSC would flag it as "submitted URL marked noindex".
        $modifiers = [
            'hand-cash'     => 'hand cash',
            'daily-payment' => 'daily payment',
            'bed-making'    => 'bed making',
        ];

        foreach ($modifiers as $urlSlug => $searchTerm) {
            $prefs = DB::table('jobs as j')
                ->join('prefectures as p', 'j.prefecture_id', '=', 'p.id')
                ->where('j.job_status_id', 3)
                ->where(function ($q) use ($searchTerm) {
                    $q->where('j.title', 'like', "%{$searchTerm}%")
                      ->orWhere('j.description', 'like', "%{$searchTerm}%");
                })
                ->select('p.english', DB::raw('count(*) as n'))
                ->groupBy('p.id', 'p.english')
                ->having('n', '>=', 2)      // buffer against a single expiry emptying the page
                ->pluck('p.english');

            foreach ($prefs as $prefName) {
                $xml .= $this->urlEntry(
                    "{$baseUrl}/{$urlSlug}-jobs-in-" . Str::slug($prefName),
                    null, '0.8', 'daily'
                );
            }
        }

        // Prefecture + area pages (global dedup to handle cross-prefecture name collisions)
        $emittedSlugs = [];
        foreach ($areas as $prefKey => $locations) {
            $parts = explode('|', $prefKey);
            $prefSlug = Str::slug($parts[0]);

            if (!isset($emittedSlugs[$prefSlug])) {
                $emittedSlugs[$prefSlug] = true;
                $xml .= $this->urlEntry("{$baseUrl}/part-time-jobs-in-{$prefSlug}", null, '0.8', 'daily');
            }

            foreach ($locations as $area) {
                $areaSlug = Str::slug($area->area);
                if (isset($emittedSlugs[$areaSlug])) {
                    continue;
                }
                $emittedSlugs[$areaSlug] = true;
                $xml .= $this->urlEntry("{$baseUrl}/part-time-jobs-in-{$areaSlug}", null, '0.7', 'daily');
            }
        }

        // Station pages
        foreach (self::STATION_SLUGS as $station) {
            $xml .= $this->urlEntry("{$baseUrl}/part-time-jobs-at-{$station}-station", null, '0.6', 'daily');
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-cache',
            'X-SG-Exclude-Cache' => '1',
        ]);
    }

    private function urlEntry(string $loc, ?string $lastmod, string $priority, string $changefreq): string
    {
        $xml = "    <url>\n";
        $xml .= "        <loc>" . htmlspecialchars($loc) . "</loc>\n";
        if ($lastmod) {
            $xml .= "        <lastmod>{$lastmod}</lastmod>\n";
        }
        $xml .= "        <priority>{$priority}</priority>\n";
        $xml .= "        <changefreq>{$changefreq}</changefreq>\n";
        $xml .= "    </url>\n";
        return $xml;
    }
}
