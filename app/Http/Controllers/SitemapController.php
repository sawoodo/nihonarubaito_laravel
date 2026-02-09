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
        'nagoya', 'Toyohashi', 'sakae',
        'Utsunomiya', 'sendai', 'Hirose-dori',
        'omiya', 'kawaguchi', 'kawasaki', 'yokohama',
        'sannomiya', 'himeji',
        'funabashi', 'matsudo', 'kashiwa',
        'maebashi', 'gifu', 'hamamatsu', 'Shizuoka',
    ];

    public function xml()
    {
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
        $xml .= $this->urlEntry($baseUrl, null, '1.0', 'daily');
        $xml .= $this->urlEntry("{$baseUrl}/subscribe", null, '0.3', 'monthly');
        $xml .= $this->urlEntry("{$baseUrl}/contact", null, '0.3', 'monthly');

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

        // Prefecture + area pages
        foreach ($areas as $prefKey => $locations) {
            $parts = explode('|', $prefKey);
            $prefName = strtolower($parts[0]);

            $xml .= $this->urlEntry("{$baseUrl}/part-time-jobs-in-{$prefName}", null, '0.8', 'daily');

            foreach ($locations as $area) {
                $areaSlug = strtolower(str_replace(' ', '-', $area->area));
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
