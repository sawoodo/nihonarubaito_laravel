<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\JobDeduplicator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnalyticsController extends Controller
{
    private const SUPER_ADMIN_EMAIL = 'ahmedsa@admin.com';

    private function authorizeSuperAdmin()
    {
        if (session('user')->email !== self::SUPER_ADMIN_EMAIL) {
            abort(403, 'You are not authorized.');
        }
    }

    public function dashboard(Request $request)
    {
        $this->authorizeSuperAdmin();

        // ── Date Range ──
        $to = $request->input('to')
            ? Carbon::parse($request->input('to'))->format('Y-m-d')
            : now()->format('Y-m-d');

        $from = $request->input('from')
            ? Carbon::parse($request->input('from'))->format('Y-m-d')
            : now()->subDays(29)->format('Y-m-d');

        $fromDate = $from;
        $toDate = $to;
        $rangeDays = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;

        // Previous period (same length, immediately before)
        $prevTo = Carbon::parse($from)->subDay()->format('Y-m-d');
        $prevFrom = Carbon::parse($prevTo)->subDays($rangeDays - 1)->format('Y-m-d');

        // ── Summary Cards (current period + previous period for comparison) ──
        $activeJobs = DB::table('jobs')->where('job_status_id', 3)->count();

        $newJobs = DB::table('jobs')
            ->whereRaw('DATE(date) >= ? AND DATE(date) <= ?', [$fromDate, $toDate])
            ->count();
        $prevNewJobs = DB::table('jobs')
            ->whereRaw('DATE(date) >= ? AND DATE(date) <= ?', [$prevFrom, $prevTo])
            ->count();

        $conversions = DB::table('application_logs')
            ->whereRaw('DATE(order_date) >= ? AND DATE(order_date) <= ?', [$fromDate, $toDate])
            ->count();
        $prevConversions = DB::table('application_logs')
            ->whereRaw('DATE(order_date) >= ? AND DATE(order_date) <= ?', [$prevFrom, $prevTo])
            ->count();

        $newSubscribers = DB::table('users')
            ->where('role_id', User::ROLE_SUBSCRIBER)
            ->whereRaw('DATE(created_at) >= ? AND DATE(created_at) <= ?', [$fromDate, $toDate])
            ->count();
        $prevNewSubscribers = DB::table('users')
            ->where('role_id', User::ROLE_SUBSCRIBER)
            ->whereRaw('DATE(created_at) >= ? AND DATE(created_at) <= ?', [$prevFrom, $prevTo])
            ->count();

        // ── Report 1: Jobs by Prefecture ──
        $prefectureStats = DB::table('jobs as j')
            ->join('prefectures as p', 'j.prefecture_id', '=', 'p.id')
            ->select([
                'p.english as prefecture',
                'p.id as prefecture_id',
                DB::raw('COUNT(CASE WHEN j.job_status_id = 3 THEN 1 END) as active'),
                DB::raw("COUNT(CASE WHEN j.job_status_id IN (4,5) AND DATE(j.updated_at) >= '{$fromDate}' AND DATE(j.updated_at) <= '{$toDate}' THEN 1 END) as expired_recent"),
                DB::raw("COUNT(CASE WHEN DATE(j.date) >= '{$fromDate}' AND DATE(j.date) <= '{$toDate}' THEN 1 END) as new_in_range"),
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('p.id', 'p.english')
            ->orderByDesc('active')
            ->get();

        // Application counts per prefecture (within range)
        $prefApplyCounts = DB::table('application_logs as al')
            ->join('jobs as j', 'al.job_no', '=', 'j.job_no')
            ->join('prefectures as p', 'j.prefecture_id', '=', 'p.id')
            ->select('p.id as prefecture_id', DB::raw('COUNT(*) as applies'))
            ->whereRaw('DATE(al.order_date) >= ? AND DATE(al.order_date) <= ?', [$fromDate, $toDate])
            ->groupBy('p.id')
            ->pluck('applies', 'prefecture_id');

        // ── Report 2: Daily New vs Expired ──
        $dailyNew = DB::table('jobs')
            ->select(DB::raw('DATE(date) as day'), DB::raw('COUNT(*) as count'))
            ->whereRaw('DATE(date) >= ? AND DATE(date) <= ?', [$fromDate, $toDate])
            ->groupBy(DB::raw('DATE(date)'))
            ->pluck('count', 'day');

        $dailyExpired = DB::table('jobs')
            ->select(DB::raw('DATE(updated_at) as day'), DB::raw('COUNT(*) as count'))
            ->whereIn('job_status_id', [4, 5])
            ->whereRaw('DATE(updated_at) >= ? AND DATE(updated_at) <= ?', [$fromDate, $toDate])
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->pluck('count', 'day');

        // Build day-by-day arrays
        $days = [];
        $newCounts = [];
        $expiredCounts = [];
        $current = Carbon::parse($fromDate);
        $end = Carbon::parse($toDate);
        while ($current->lte($end)) {
            $day = $current->format('Y-m-d');
            $days[] = $current->format('M d');
            $newCounts[] = $dailyNew[$day] ?? 0;
            $expiredCounts[] = $dailyExpired[$day] ?? 0;
            $current->addDay();
        }

        // ── Report 3: Top Jobs by Conversions ──
        $topJobs = DB::table('application_logs as al')
            ->join('jobs as j', 'al.job_no', '=', 'j.job_no')
            ->join('prefectures as p', 'j.prefecture_id', '=', 'p.id')
            ->join('categories as c', 'j.job_category_id', '=', 'c.id')
            ->select([
                'j.job_no',
                'j.title',
                'p.english as prefecture',
                'c.english as category',
                'j.job_status_id',
                DB::raw('COUNT(al.id) as conversions'),
                DB::raw('MIN(al.order_date) as first_apply'),
                DB::raw('MAX(al.order_date) as last_apply'),
            ])
            ->whereRaw('DATE(al.order_date) >= ? AND DATE(al.order_date) <= ?', [$fromDate, $toDate])
            ->groupBy('j.job_no', 'j.title', 'p.english', 'c.english', 'j.job_status_id')
            ->orderByDesc('conversions')
            ->limit(20)
            ->get();

        // ── Report 4: Category Distribution (within range) ──
        $categoryStats = DB::table('jobs as j')
            ->join('categories as c', 'j.job_category_id', '=', 'c.id')
            ->select([
                'c.english as category',
                DB::raw('COUNT(CASE WHEN j.job_status_id = 3 THEN 1 END) as active'),
                DB::raw("COUNT(CASE WHEN DATE(j.date) >= '{$fromDate}' AND DATE(j.date) <= '{$toDate}' THEN 1 END) as new_in_range"),
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('c.id', 'c.english')
            ->orderByDesc('active')
            ->get();

        $statusLabels = [
            1 => ['Draft', 'default'],
            2 => ['Pending', 'warning'],
            3 => ['Published', 'success'],
            4 => ['Expired', 'danger'],
            5 => ['Trashed', 'danger'],
            6 => ['Quota Full', 'info'],
        ];

        // ── GA4 Demand vs Supply ──
        $lastUpload = DB::table('ga4_landing_pages')->orderByDesc('uploaded_at')->first();
        $demandSupply = [];
        $gapZeroJobs = 0;
        $gapLowJobs = 0;

        if ($lastUpload) {
            $demandSupply = $this->buildDemandSupply($lastUpload->date_from, $lastUpload->date_to);
            $gapZeroJobs = collect($demandSupply)->where('active_jobs', 0)->where('sessions', '>', 0)->count();
            $gapLowJobs = collect($demandSupply)->where('active_jobs', '>', 0)->where('active_jobs', '<', 5)->where('sessions', '>', 0)->count();
        }

        return view('admin.analytics.dashboard', compact(
            'fromDate',
            'toDate',
            'rangeDays',
            'activeJobs',
            'newJobs',
            'prevNewJobs',
            'conversions',
            'prevConversions',
            'newSubscribers',
            'prevNewSubscribers',
            'prefectureStats',
            'prefApplyCounts',
            'days',
            'newCounts',
            'expiredCounts',
            'topJobs',
            'categoryStats',
            'statusLabels',
            'lastUpload',
            'demandSupply',
            'gapZeroJobs',
            'gapLowJobs',
        ) + ['activeSideMenu' => 'analytics']);
    }

    public function uploadGa4(Request $request)
    {
        $this->authorizeSuperAdmin();

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
            'ga4_from' => 'required|date',
            'ga4_to'   => 'required|date|after_or_equal:ga4_from',
        ]);

        $dateFrom = Carbon::parse($request->input('ga4_from'))->format('Y-m-d');
        $dateTo = Carbon::parse($request->input('ga4_to'))->format('Y-m-d');

        $file = $request->file('csv_file');
        $lines = array_filter(explode("\n", file_get_contents($file->getRealPath())));

        // Skip GA4 header lines (# comments and blank lines) until we find column headers
        $dataStarted = false;
        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Skip the two-row header (e.g., "Segment,All Users,..." and "Landing page,Sessions,...")
            if (!$dataStarted) {
                if (str_contains($line, 'Landing page') || str_contains($line, 'Segment')) {
                    continue;
                }
                $dataStarted = true;
            }

            $cols = str_getcsv($line);

            // Skip rows without a valid path
            $path = trim($cols[0] ?? '');
            if ($path === '' || $path === '(not set)' || !str_starts_with($path, '/')) {
                continue;
            }

            // Skip grand total row
            if (str_contains($line, 'Grand total')) {
                continue;
            }

            $sessions = (int) str_replace(',', '', $cols[1] ?? '0');
            $pageviews = (int) str_replace(',', '', $cols[2] ?? '0');

            $rows[] = [
                'page_path' => $path,
                'sessions'  => $sessions,
                'pageviews' => $pageviews,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'uploaded_at' => now(),
            ];
        }

        if (empty($rows)) {
            return back()->with('error', 'No valid rows found in the CSV file.');
        }

        // Replace data for same date range
        DB::table('ga4_landing_pages')
            ->where('date_from', $dateFrom)
            ->where('date_to', $dateTo)
            ->delete();

        // Insert in chunks
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('ga4_landing_pages')->insert($chunk);
        }

        return back()->with('success', "Imported " . count($rows) . " landing pages for {$dateFrom} to {$dateTo}.");
    }

    public function demandSupply(Request $request)
    {

        $lastUpload = DB::table('ga4_landing_pages')->orderByDesc('uploaded_at')->first();
        $demandSupply = [];
        $gapZeroJobs = 0;
        $gapLowJobs = 0;
        $gapOk = 0;

        // Upload history (last 10)
        $uploadHistory = DB::table('ga4_landing_pages')
            ->select('date_from', 'date_to', 'uploaded_at', DB::raw('COUNT(*) as row_count'))
            ->groupBy('date_from', 'date_to', 'uploaded_at')
            ->orderByDesc('uploaded_at')
            ->limit(10)
            ->get();

        // Prefectures and areas with names for Shigotoin links and search
        $prefectures = DB::table('prefectures')->select('id', 'english', 'japanese')->get();
        $prefectureJp = $prefectures->pluck('japanese', 'id');
        $prefectureEn = $prefectures->pluck('english', 'id');
        $areaJp = DB::table('areas')->pluck('japanese', 'id');

        if ($lastUpload) {
            $demandSupply = $this->buildDemandSupply($lastUpload->date_from, $lastUpload->date_to);
            $gapZeroJobs = collect($demandSupply)->where('active_jobs', 0)->where('sessions', '>', 0)->count();
            $gapLowJobs = collect($demandSupply)->where('active_jobs', '>', 0)->where('active_jobs', '<', 5)->where('sessions', '>', 0)->count();
            $gapOk = collect($demandSupply)->where('active_jobs', '>=', 5)->count();
        }

        $totalNoJobSessions = collect($demandSupply)->where('active_jobs', 0)->sum('sessions');

        return view('admin.analytics.demand-supply', compact(
            'lastUpload',
            'demandSupply',
            'gapZeroJobs',
            'gapLowJobs',
            'gapOk',
            'totalNoJobSessions',
            'uploadHistory',
            'prefectureJp',
            'prefectureEn',
            'areaJp',
        ) + ['activeSideMenu' => 'demand_supply']);
    }

    public function exportDemandSupplyCsv()
    {
        $this->authorizeSuperAdmin();

        $lastUpload = DB::table('ga4_landing_pages')->orderByDesc('uploaded_at')->first();
        if (!$lastUpload) {
            return back()->with('error', 'No GA4 data uploaded yet.');
        }

        $data = $this->buildDemandSupply($lastUpload->date_from, $lastUpload->date_to);

        $filename = "demand-supply-{$lastUpload->date_from}-to-{$lastUpload->date_to}.csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Page Path', 'Type', 'Location', 'Sessions', 'Pageviews', 'Active Jobs', 'Gap Score', 'Status']);
            foreach ($data as $row) {
                fputcsv($out, [
                    $row['page_path'], $row['page_type'], $row['location'],
                    $row['sessions'], $row['pageviews'], $row['active_jobs'],
                    $row['gap_score'] ?? 'N/A', $row['status'],
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Query slug → category IDs mapping (with bed-making ↔ light-work mixing)
    private const CATEGORY_MAP = [
        'bed-making'        => [4, 1], // Bed Making/Cleaning + Packing/Sorting (mixing)
        'sorting'           => [1, 4], // Packing/Sorting + Bed Making/Cleaning (mixing)
        'light-work'        => [1, 4], // Same as sorting
        'dish-washing'      => [4, 1], // Cleaning category + mixing
        'convenience-store' => [3],
        'combini-part-time' => [3],
        'restaurant'        => [2],
        'delivery'          => [5],
    ];

    private function buildDemandSupply(string $dateFrom, string $dateTo): array
    {
        $ga4Pages = DB::table('ga4_landing_pages')
            ->where('date_from', $dateFrom)
            ->where('date_to', $dateTo)
            ->where('sessions', '>', 0)
            ->orderByDesc('sessions')
            ->get();

        // Preload lookup tables
        $prefectures = DB::table('prefectures')
            ->select('id', 'english')
            ->get()
            ->keyBy(fn($p) => strtolower($p->english));

        $areas = DB::table('areas as a')
            ->join('towns as t', 'a.town_id', '=', 't.id')
            ->select('a.id', 'a.english', 't.prefecture_id')
            ->orderBy('a.id')
            ->get();

        // Build multiple area lookup keys for fuzzy matching
        // Keep first match (lowest id) for duplicate names — matches frontend findAreaByName() behavior
        $areasByKey = [];    // exact key: "shinjuku-ward" → area
        $areasByPartial = []; // partial: "shinjuku" → area (first word match)
        foreach ($areas as $a) {
            $key = Str::slug($a->english); // Handles trailing whitespace + matches URL slugs exactly
            if (!isset($areasByKey[$key])) {
                $areasByKey[$key] = $a;
            }
            // Also index by first word for partial matching (e.g., "shinjuku" matches "shinjuku ward")
            $parts = explode(' ', strtolower($a->english));
            if (count($parts) > 1) {
                $firstWord = $parts[0];
                if (!isset($areasByPartial[$firstWord])) {
                    $areasByPartial[$firstWord] = $a;
                }
            }
        }

        // Active jobs per prefecture
        $jobsByPref = DB::table('jobs')
            ->where('job_status_id', 3)
            ->select('prefecture_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('prefecture_id')
            ->pluck('cnt', 'prefecture_id');

        // Active jobs per area
        $jobsByArea = DB::table('jobs')
            ->where('job_status_id', 3)
            ->select('area_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('area_id')
            ->pluck('cnt', 'area_id');

        // Active jobs matching station names
        $jobsByStation = DB::table('jobs')
            ->where('job_status_id', 3)
            ->whereNotNull('station')
            ->where('station', '!=', '')
            ->select('station', DB::raw('COUNT(*) as cnt'))
            ->groupBy('station')
            ->pluck('cnt', 'station');

        // Active jobs per category (nationwide)
        $jobsByCat = DB::table('jobs')
            ->where('job_status_id', 3)
            ->select('job_category_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('job_category_id')
            ->pluck('cnt', 'job_category_id');

        // Active jobs per category + prefecture
        $jobsByCatPref = DB::table('jobs')
            ->where('job_status_id', 3)
            ->select('job_category_id', 'prefecture_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('job_category_id', 'prefecture_id')
            ->get()
            ->groupBy('job_category_id')
            ->map(fn($rows) => $rows->pluck('cnt', 'prefecture_id'));

        // Active "hand cash" / "daily payment" jobs (text search in title/description)
        $handCashByPref = DB::table('jobs')
            ->where('job_status_id', 3)
            ->where(function ($q) {
                $q->where('title', 'LIKE', '%hand cash%')
                  ->orWhere('title', 'LIKE', '%daily payment%')
                  ->orWhere('title', 'LIKE', '%日払い%')
                  ->orWhere('description', 'LIKE', '%hand cash%')
                  ->orWhere('description', 'LIKE', '%daily payment%')
                  ->orWhere('description', 'LIKE', '%日払い%');
            })
            ->select('prefecture_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('prefecture_id')
            ->pluck('cnt', 'prefecture_id');

        $handCashTotal = $handCashByPref->sum();

        // Aggregate sessions by canonical path (merge paginated pages like /page/2, /page/3)
        $aggregated = [];

        foreach ($ga4Pages as $page) {
            $path = $page->page_path;

            // Skip individual job detail pages, search pages, static pages
            if (preg_match('#^/jobs/\d+/detail#', $path) || str_starts_with($path, '/jobs/search')
                || $path === '/' || $path === '/subscribe' || $path === '/contact'
                || $path === '/about' || $path === '/faq' || $path === '/privacy-policy'
                || $path === '/terms-of-service' || $path === '/account'
                || $path === '/job/link-sent'
                || str_starts_with($path, '/admin') || str_starts_with($path, '/lang/')
                || str_starts_with($path, '/sitemap') || $path === '/jobs') {
                continue;
            }

            // Strip /page/N suffix for paginated listing pages
            $matchPath = preg_replace('#/page/\d+$#', '', $path);

            $parsed = $this->parsePagePath($matchPath, $prefectures, $areasByKey, $areasByPartial);
            if (!$parsed) {
                continue;
            }

            // Aggregate by canonical path
            if (isset($aggregated[$matchPath])) {
                $aggregated[$matchPath]['sessions'] += $page->sessions;
                $aggregated[$matchPath]['pageviews'] += $page->pageviews;
            } else {
                $aggregated[$matchPath] = [
                    'parsed'   => $parsed,
                    'sessions' => $page->sessions,
                    'pageviews' => $page->pageviews,
                ];
            }
        }

        $results = [];

        foreach ($aggregated as $matchPath => $data) {
            $parsed = $data['parsed'];
            $sessions = $data['sessions'];
            $pageviews = $data['pageviews'];

            // Look up active job count based on page type
            $activeJobCount = 0;

            if (!empty($parsed['category_ids'])) {
                // Category page: count by category IDs + location
                $catIds = $parsed['category_ids'];

                if ($parsed['prefecture_id'] === 0 && $parsed['area_id'] === 0) {
                    // Nationwide (japan): sum all matching categories
                    foreach ($catIds as $catId) {
                        $activeJobCount += $jobsByCat[$catId] ?? 0;
                    }
                } else {
                    // Specific prefecture
                    foreach ($catIds as $catId) {
                        $activeJobCount += $jobsByCatPref[$catId][$parsed['prefecture_id']] ?? 0;
                    }
                }
            } elseif ($parsed['type'] === 'Daily Pay') {
                // Hand cash / daily payment: text-search-based count
                if ($parsed['prefecture_id'] === 0) {
                    $activeJobCount = $handCashTotal;
                } else {
                    $activeJobCount = $handCashByPref[$parsed['prefecture_id']] ?? 0;
                }
            } elseif ($parsed['area_id']) {
                $activeJobCount = $jobsByArea[$parsed['area_id']] ?? 0;
            } elseif ($parsed['station']) {
                $stationLower = strtolower($parsed['station']);
                foreach ($jobsByStation as $dbStation => $cnt) {
                    if (str_contains(strtolower($dbStation), $stationLower)) {
                        $activeJobCount += $cnt;
                    }
                }
            } elseif ($parsed['prefecture_id']) {
                $activeJobCount = $jobsByPref[$parsed['prefecture_id']] ?? 0;
            }

            $gapScore = $activeJobCount > 0 ? round($sessions / $activeJobCount, 1) : null;

            if ($activeJobCount === 0) {
                $status = 'No Jobs';
                $statusClass = 'danger';
            } elseif ($activeJobCount < 5) {
                $status = 'Low Supply';
                $statusClass = 'warning';
            } else {
                $status = 'OK';
                $statusClass = 'success';
            }

            $results[] = [
                'page_path'      => $matchPath,
                'page_type'      => $parsed['type'],
                'location'       => $parsed['location_name'],
                'sessions'       => $sessions,
                'pageviews'      => $pageviews,
                'active_jobs'    => $activeJobCount,
                'gap_score'      => $gapScore,
                'status'         => $status,
                'status_class'   => $statusClass,
                'prefecture_id'  => $parsed['prefecture_id'],
                'area_id'        => $parsed['area_id'],
                'category_ids'   => $parsed['category_ids'],
                'station'        => $parsed['station'],
            ];
        }

        // Sort: No Jobs first (by sessions desc), then Low Supply (by sessions desc), then OK
        usort($results, function ($a, $b) {
            $order = ['No Jobs' => 0, 'Low Supply' => 1, 'OK' => 2];
            $cmp = ($order[$a['status']] ?? 3) <=> ($order[$b['status']] ?? 3);
            return $cmp !== 0 ? $cmp : $b['sessions'] <=> $a['sessions'];
        });

        return $results;
    }

    public function expiringJobs(Request $request)
    {

        $filter = $request->input('filter', '7');
        $prefectureId = $request->input('prefecture_id', '');
        $categoryId = $request->input('category_id', '');
        $search = $request->input('search', '');

        // Summary counts
        $base = DB::table('jobs')->where('job_status_id', 3)->whereNotNull('delete_at');
        $today = now()->format('Y-m-d');
        $expiringToday = (clone $base)->whereDate('delete_at', $today)->count();
        $expiringTomorrow = (clone $base)->whereDate('delete_at', now()->addDay()->format('Y-m-d'))->count();
        $expiring3Days = (clone $base)->whereBetween(DB::raw('DATE(delete_at)'), [$today, now()->addDays(3)->format('Y-m-d')])->count();
        $expiring7Days = (clone $base)->whereBetween(DB::raw('DATE(delete_at)'), [$today, now()->addDays(7)->format('Y-m-d')])->count();
        $expiring14Days = (clone $base)->whereBetween(DB::raw('DATE(delete_at)'), [$today, now()->addDays(14)->format('Y-m-d')])->count();

        // Build query for the table
        $query = DB::table('jobs as j')
            ->join('prefectures as p', 'j.prefecture_id', '=', 'p.id')
            ->join('categories as c', 'j.job_category_id', '=', 'c.id')
            ->leftJoin('areas as a', 'j.area_id', '=', 'a.id')
            ->where('j.job_status_id', 3)
            ->whereNotNull('j.delete_at')
            ->select(
                'j.id', 'j.job_no', 'j.title', 'j.apply_link', 'j.delete_at',
                'j.wage', 'j.station', 'j.img_id',
                'p.english as prefecture', 'p.id as prefecture_id',
                'c.english as category',
                'a.english as area'
            );

        // Date range filter
        if ($filter === '0') {
            $query->whereDate('j.delete_at', $today);
        } elseif ($filter === '1') {
            $query->whereDate('j.delete_at', now()->addDay()->format('Y-m-d'));
        } elseif ($filter === 'custom') {
            $from = $request->input('from', $today);
            $to = $request->input('to', now()->addDays(7)->format('Y-m-d'));
            $query->whereBetween(DB::raw('DATE(j.delete_at)'), [$from, $to]);
        } else {
            $days = (int) $filter;
            $query->whereBetween(DB::raw('DATE(j.delete_at)'), [$today, now()->addDays($days)->format('Y-m-d')]);
        }

        if ($prefectureId) {
            $query->where('j.prefecture_id', $prefectureId);
        }
        if ($categoryId) {
            $query->where('j.job_category_id', $categoryId);
        }
        if ($search) {
            $query->where('j.title', 'LIKE', "%{$search}%");
        }

        $query->orderBy('j.delete_at', 'asc');

        $jobs = $query->get();

        // Prefectures and categories for filter dropdowns
        $prefectures = DB::table('prefectures')->orderBy('english')->get(['id', 'english']);
        $categories = DB::table('categories')->orderBy('english')->get(['id', 'english']);

        return view('admin.analytics.expiring-jobs', compact(
            'jobs', 'filter', 'prefectureId', 'categoryId', 'search',
            'expiringToday', 'expiringTomorrow', 'expiring3Days', 'expiring7Days', 'expiring14Days',
            'prefectures', 'categories',
        ) + ['activeSideMenu' => 'expiring_jobs']);
    }

    public function updateExpiringDate(Request $request)
    {

        $jobId = $request->input('job_id');
        $deleteAt = $request->input('delete_at');

        if (!$jobId || !$deleteAt) {
            return response()->json(['success' => false, 'message' => 'Missing parameters'], 400);
        }

        $job = DB::table('jobs')->where('id', $jobId)->first(['id', 'delete_at']);
        if (!$job) {
            return response()->json(['success' => false, 'message' => 'Job not found'], 404);
        }

        $newDate = Carbon::parse($deleteAt)->format('Y-m-d');
        if ($newDate < now()->format('Y-m-d')) {
            return response()->json(['success' => false, 'message' => 'Cannot set date in the past'], 422);
        }

        DB::table('jobs')->where('id', $jobId)->update(['delete_at' => $newDate . ' 00:00:00']);

        $daysLeft = Carbon::parse($newDate)->diffInDays(now()->startOfDay());
        if (Carbon::parse($newDate)->lt(now()->startOfDay())) {
            $daysLeft = 0;
        }

        return response()->json([
            'success' => true,
            'new_delete_at' => $newDate,
            'days_left' => $daysLeft,
        ]);
    }

    public function bulkExtendExpiring(Request $request)
    {

        $jobIds = $request->input('job_ids', []);
        $days = (int) $request->input('days', 7);

        if (empty($jobIds) || $days <= 0) {
            return response()->json(['success' => false, 'message' => 'Missing parameters'], 400);
        }

        $updated = DB::table('jobs')
            ->whereIn('id', $jobIds)
            ->where('job_status_id', 3)
            ->update(['delete_at' => DB::raw("DATE_ADD(delete_at, INTERVAL {$days} DAY)")]);

        return response()->json(['success' => true, 'updated' => $updated]);
    }

    public function trashExpiring(Request $request)
    {

        $jobId = $request->input('job_id');
        if (!$jobId) {
            return response()->json(['success' => false, 'message' => 'Missing job_id'], 400);
        }

        $updated = DB::table('jobs')
            ->where('id', $jobId)
            ->where('job_status_id', 3)
            ->update(['job_status_id' => 5]);

        return response()->json(['success' => true, 'trashed' => $updated]);
    }

    public function employees(Request $request)
    {
        $this->authorizeSuperAdmin();

        // Date range
        $to = $request->input('to')
            ? Carbon::parse($request->input('to'))->format('Y-m-d')
            : now()->format('Y-m-d');
        $from = $request->input('from')
            ? Carbon::parse($request->input('from'))->format('Y-m-d')
            : now()->subDays(29)->format('Y-m-d');

        $fromDate = $from;
        $toDate = $to;
        $rangeDays = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;

        // Previous period for comparison arrows
        $prevTo = Carbon::parse($from)->subDay()->format('Y-m-d');
        $prevFrom = Carbon::parse($prevTo)->subDays($rangeDays - 1)->format('Y-m-d');

        // All backend users who have ever created jobs
        $backendUsers = DB::table('users')
            ->whereIn('role_id', [1, 2, 4])
            ->get(['id', 'first_name', 'last_name', 'email', 'role_id']);

        $userMap = $backendUsers->keyBy('id')->map(fn($u) => (object) [
            'id'    => $u->id,
            'name'  => trim($u->first_name . ' ' . $u->last_name),
            'email' => $u->email,
            'role_id' => $u->role_id,
        ]);

        // Jobs created in current period, grouped by user
        $currentPeriod = DB::table('jobs')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->whereRaw('DATE(date) >= ? AND DATE(date) <= ?', [$fromDate, $toDate])
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        // Jobs created in previous period for comparison
        $previousPeriod = DB::table('jobs')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->whereRaw('DATE(date) >= ? AND DATE(date) <= ?', [$prevFrom, $prevTo])
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        // Active jobs per user (current)
        $activePerUser = DB::table('jobs')
            ->where('job_status_id', 3)
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        // Daily breakdown for current period (for charts + streaks + best day)
        $dailyData = DB::table('jobs')
            ->select('user_id', DB::raw('DATE(date) as day'), DB::raw('COUNT(*) as count'))
            ->whereRaw('DATE(date) >= ? AND DATE(date) <= ?', [$fromDate, $toDate])
            ->groupBy('user_id', DB::raw('DATE(date)'))
            ->get();

        // Category breakdown per user in period
        $categoryBreakdown = DB::table('jobs as j')
            ->join('categories as c', 'j.job_category_id', '=', 'c.id')
            ->select('j.user_id', 'c.english as category', DB::raw('COUNT(*) as count'))
            ->whereRaw('DATE(j.date) >= ? AND DATE(j.date) <= ?', [$fromDate, $toDate])
            ->groupBy('j.user_id', 'c.english')
            ->get()
            ->groupBy('user_id');

        // Prefecture breakdown per user in period (top 5)
        $prefBreakdown = DB::table('jobs as j')
            ->join('prefectures as p', 'j.prefecture_id', '=', 'p.id')
            ->select('j.user_id', 'p.english as prefecture', DB::raw('COUNT(*) as count'))
            ->whereRaw('DATE(j.date) >= ? AND DATE(j.date) <= ?', [$fromDate, $toDate])
            ->groupBy('j.user_id', 'p.english')
            ->orderByDesc('count')
            ->get()
            ->groupBy('user_id');

        // Quality metrics: missing fields in period
        $qualityMetrics = DB::table('jobs')
            ->select(
                'user_id',
                DB::raw('SUM(img_id = 0 OR img_id IS NULL) as no_image'),
                DB::raw("SUM(station = '' OR station IS NULL) as no_station"),
                DB::raw("SUM(wage = '' OR wage IS NULL) as no_wage"),
                DB::raw('COUNT(*) as total')
            )
            ->whereRaw('DATE(date) >= ? AND DATE(date) <= ?', [$fromDate, $toDate])
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        // Status breakdown per user in period
        $statusBreakdown = DB::table('jobs')
            ->select('user_id', 'job_status_id', DB::raw('COUNT(*) as count'))
            ->whereRaw('DATE(date) >= ? AND DATE(date) <= ?', [$fromDate, $toDate])
            ->groupBy('user_id', 'job_status_id')
            ->get()
            ->groupBy('user_id');

        // Build daily data indexed by user → day
        $dailyByUser = [];
        foreach ($dailyData as $row) {
            $dailyByUser[$row->user_id][$row->day] = $row->count;
        }

        // Build employee cards data
        $employees = [];
        $userIds = $currentPeriod->keys()->merge($activePerUser->keys())->unique();

        foreach ($userIds as $userId) {
            if (!isset($userMap[$userId])) continue;

            $user = $userMap[$userId];
            $created = $currentPeriod[$userId] ?? 0;
            $prevCreated = $previousPeriod[$userId] ?? 0;
            $active = $activePerUser[$userId] ?? 0;

            // Calculate comparison percentage
            $changePercent = null;
            if ($prevCreated > 0) {
                $changePercent = round(($created - $prevCreated) / $prevCreated * 100, 1);
            } elseif ($created > 0) {
                $changePercent = 100;
            }

            // Best day and streak
            $userDays = $dailyByUser[$userId] ?? [];
            $bestDayDate = null;
            $bestDayCount = 0;
            foreach ($userDays as $day => $count) {
                if ($count > $bestDayCount) {
                    $bestDayCount = $count;
                    $bestDayDate = $day;
                }
            }

            // Calculate streak (consecutive days with >= 1 job, counting backwards from toDate)
            $streak = 0;
            $checkDate = Carbon::parse($toDate);
            $startDate = Carbon::parse($fromDate);
            while ($checkDate->gte($startDate)) {
                $dayStr = $checkDate->format('Y-m-d');
                if (isset($userDays[$dayStr]) && $userDays[$dayStr] > 0) {
                    $streak++;
                } else {
                    break;
                }
                $checkDate->subDay();
            }

            // Avg per day (only days in period)
            $avgPerDay = $rangeDays > 0 ? round($created / $rangeDays, 1) : 0;

            // Category breakdown
            $categories = [];
            if (isset($categoryBreakdown[$userId])) {
                foreach ($categoryBreakdown[$userId] as $row) {
                    $categories[] = ['name' => $row->category, 'count' => $row->count];
                }
            }

            // Prefecture breakdown (top 5)
            $prefectures = [];
            if (isset($prefBreakdown[$userId])) {
                $count = 0;
                foreach ($prefBreakdown[$userId] as $row) {
                    $prefectures[] = ['name' => $row->prefecture, 'count' => $row->count];
                    if (++$count >= 5) break;
                }
            }

            // Quality
            $quality = $qualityMetrics[$userId] ?? null;

            // Status counts
            $statuses = [];
            if (isset($statusBreakdown[$userId])) {
                foreach ($statusBreakdown[$userId] as $row) {
                    $statuses[$row->job_status_id] = $row->count;
                }
            }

            $employees[] = [
                'user'           => $user,
                'created'        => $created,
                'prev_created'   => $prevCreated,
                'active'         => $active,
                'avg_per_day'    => $avgPerDay,
                'best_day_date'  => $bestDayDate,
                'best_day_count' => $bestDayCount,
                'streak'         => $streak,
                'change_percent' => $changePercent,
                'categories'     => $categories,
                'prefectures'    => $prefectures,
                'quality'        => $quality,
                'statuses'       => $statuses,
            ];
        }

        // Sort: most jobs created first
        usort($employees, fn($a, $b) => $b['created'] <=> $a['created']);

        // Build chart data: daily stacked bar
        $allDays = [];
        $current = Carbon::parse($fromDate);
        $end = Carbon::parse($toDate);
        while ($current->lte($end)) {
            $allDays[] = $current->format('Y-m-d');
            $current->addDay();
        }

        $chartLabels = array_map(fn($d) => Carbon::parse($d)->format('M d'), $allDays);

        // Chart datasets: one per active user
        $chartDatasets = [];
        $colors = ['#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e', '#16a085', '#c0392b'];
        $colorIdx = 0;
        foreach ($employees as $emp) {
            if ($emp['created'] === 0) continue;
            $data = [];
            foreach ($allDays as $day) {
                $data[] = $dailyByUser[$emp['user']->id][$day] ?? 0;
            }
            $chartDatasets[] = [
                'label' => $emp['user']->name ?: $emp['user']->email,
                'data'  => $data,
                'backgroundColor' => $colors[$colorIdx % count($colors)],
                'borderColor' => $colors[$colorIdx % count($colors)],
            ];
            $colorIdx++;
        }

        // Weekly summary data
        $weeklySummary = [];
        foreach ($employees as $emp) {
            if ($emp['created'] === 0) continue;
            $userDays = $dailyByUser[$emp['user']->id] ?? [];
            $weeks = [];
            $currentWeek = Carbon::parse($fromDate)->startOfWeek(Carbon::MONDAY);
            $endDate = Carbon::parse($toDate);

            while ($currentWeek->lte($endDate)) {
                $weekLabel = $currentWeek->format('M d');
                $weekDays = [];
                for ($i = 0; $i < 7; $i++) {
                    $d = $currentWeek->copy()->addDays($i)->format('Y-m-d');
                    $weekDays[] = $userDays[$d] ?? 0;
                }
                $weeks[] = [
                    'label' => $weekLabel,
                    'days'  => $weekDays,
                    'total' => array_sum($weekDays),
                ];
                $currentWeek->addWeek();
            }
            $weeklySummary[] = [
                'user'  => $emp['user'],
                'weeks' => $weeks,
            ];
        }

        // Summary totals
        $totalCreated = collect($employees)->sum('created');
        $totalAvgPerDay = $rangeDays > 0 ? round($totalCreated / $rangeDays, 1) : 0;
        $totalActive = DB::table('jobs')->where('job_status_id', 3)->count();

        // Best day overall
        $bestOverall = DB::table('jobs')
            ->select(DB::raw('DATE(date) as day'), DB::raw('COUNT(*) as count'))
            ->whereRaw('DATE(date) >= ? AND DATE(date) <= ?', [$fromDate, $toDate])
            ->groupBy(DB::raw('DATE(date)'))
            ->orderByDesc('count')
            ->first();

        $statusLabels = [1 => 'Draft', 2 => 'Pending', 3 => 'Published', 4 => 'Expired', 5 => 'Trashed', 6 => 'Quota Full'];

        // Detail table data (date × user with count > 0)
        $detailTableData = [];
        foreach ($employees as $emp) {
            if ($emp['created'] === 0) continue;
            $userId = $emp['user']->id;
            $userDays = $dailyByUser[$userId] ?? [];
            $cur = Carbon::parse($fromDate)->copy();
            $e = Carbon::parse($toDate);
            while ($cur->lte($e)) {
                $day = $cur->format('Y-m-d');
                $count = $userDays[$day] ?? 0;
                if ($count > 0) {
                    $detailTableData[] = [
                        'date'         => $day,
                        'date_display' => $cur->format('M d, Y'),
                        'user_id'      => $userId,
                        'employee'     => $emp['user']->name ?: $emp['user']->email,
                        'created'      => $count,
                    ];
                }
                $cur->addDay();
            }
        }

        return view('admin.analytics.employees', compact(
            'fromDate', 'toDate', 'rangeDays',
            'employees', 'totalCreated', 'totalAvgPerDay', 'totalActive', 'bestOverall',
            'chartLabels', 'chartDatasets', 'weeklySummary', 'statusLabels', 'detailTableData',
        ) + ['activeSideMenu' => 'employees']);
    }

    private function parsePagePath(string $path, $prefectures, $areasByKey, $areasByPartial): ?array
    {
        $result = [
            'type' => 'unknown',
            'location_name' => '',
            'prefecture_id' => 0,
            'area_id' => 0,
            'station' => null,
            'category_ids' => [],
        ];

        // Pattern: /part-time-jobs-at-{station}
        if (preg_match('#^/part-time-jobs-at-(.+?)(?:-station)?$#', $path, $m)) {
            $station = str_replace('-', ' ', $m[1]);
            $result['type'] = 'Station';
            $result['location_name'] = ucwords($station);
            $result['station'] = $station;
            return $result;
        }

        // Pattern: /{query}-jobs-in-{location}
        if (preg_match('#^/([\w-]+)-jobs-in-([\w-]+)$#', $path, $m)) {
            $query = $m[1];
            $location = $m[2];

            // Determine page type and category mapping from query prefix
            if ($query === 'part-time') {
                $result['type'] = 'Prefecture/Area';
            } elseif ($query === 'hand-cash' || $query === 'daily-payment') {
                $result['type'] = 'Daily Pay';
            } elseif (isset(self::CATEGORY_MAP[$query])) {
                $result['type'] = 'Category';
                $result['category_ids'] = self::CATEGORY_MAP[$query];
            } else {
                // Unknown query — treat as category text search page
                $result['type'] = 'Category';
            }

            // Resolve location
            $locClean = strtolower(str_replace('-', ' ', $location));
            $locKey = strtolower($location);

            if ($location === 'japan') {
                $result['location_name'] = 'Japan (Nationwide)';
                return $result;
            }

            // Try prefectures (exact match)
            if (isset($prefectures[$locClean])) {
                $pref = $prefectures[$locClean];
                $result['prefecture_id'] = $pref->id;
                $result['location_name'] = $pref->english;
                return $result;
            }

            // Try areas (exact key match: "shinjuku-ward")
            if (isset($areasByKey[$locKey])) {
                $area = $areasByKey[$locKey];
                $result['area_id'] = $area->id;
                $result['prefecture_id'] = $area->prefecture_id;
                $result['location_name'] = $area->english;
                if ($result['type'] === 'Prefecture/Area') {
                    $result['type'] = 'Area';
                }
                return $result;
            }

            // Try areas (fuzzy/partial match: "shinjuku" matches "shinjuku ward")
            if (isset($areasByPartial[$locKey])) {
                $area = $areasByPartial[$locKey];
                $result['area_id'] = $area->id;
                $result['prefecture_id'] = $area->prefecture_id;
                $result['location_name'] = $area->english;
                if ($result['type'] === 'Prefecture/Area') {
                    $result['type'] = 'Area';
                }
                return $result;
            }

            // Fallback — unresolved location
            $result['location_name'] = ucwords(str_replace('-', ' ', $location));
            return $result;
        }

        return null;
    }

    // ── Duplicate Review ──

    public function duplicates(Request $request)
    {
        $groups = JobDeduplicator::findAllDuplicateGroups();

        // Enrich with prefecture names
        $prefectureIds = [];
        foreach ($groups as $g) {
            foreach ($g['jobs'] as $j) {
                $prefectureIds[] = $j->prefecture_id;
            }
        }
        $prefectures = DB::table('prefectures')->whereIn('id', array_unique($prefectureIds))->pluck('english', 'id')->toArray();
        $areas = DB::table('areas')
            ->whereIn('id', collect($groups)->flatMap(fn($g) => collect($g['jobs'])->pluck('area_id'))->unique()->toArray())
            ->pluck('english', 'id')->toArray();
        $categories = DB::table('categories')->pluck('english', 'id')->toArray();

        // Filter
        $levelFilter = $request->input('level', 'all');
        $prefectureFilter = (int) $request->input('prefecture_id', 0);
        $search = trim($request->input('search', ''));

        if ($levelFilter !== 'all') {
            $groups = array_values(array_filter($groups, fn($g) => $g['level'] === $levelFilter));
        }
        if ($prefectureFilter) {
            $groups = array_values(array_filter($groups, function ($g) use ($prefectureFilter) {
                foreach ($g['jobs'] as $j) {
                    if ($j->prefecture_id == $prefectureFilter) return true;
                }
                return false;
            }));
        }
        if ($search) {
            $groups = array_values(array_filter($groups, function ($g) use ($search) {
                if (stripos($g['label'], $search) !== false) return true;
                if (stripos($g['company'], $search) !== false) return true;
                foreach ($g['jobs'] as $j) {
                    if (stripos($j->job_no, $search) !== false) return true;
                }
                return false;
            }));
        }

        // Counts
        $allGroups = JobDeduplicator::findAllDuplicateGroups();
        $highCount = count(array_filter($allGroups, fn($g) => $g['level'] === 'high'));
        $mediumCount = count(array_filter($allGroups, fn($g) => $g['level'] === 'medium'));
        $lowCount = count(array_filter($allGroups, fn($g) => $g['level'] === 'low'));

        // Dismissed groups
        $dismissedGroups = DB::table('dismissed_duplicates')->orderByDesc('dismissed_at')->get();

        $prefectureList = DB::table('prefectures')->orderBy('english')->pluck('english', 'id')->toArray();

        return view('admin.analytics.duplicates', compact(
            'groups', 'prefectures', 'areas', 'categories',
            'levelFilter', 'prefectureFilter', 'search',
            'highCount', 'mediumCount', 'lowCount',
            'dismissedGroups', 'prefectureList'
        ) + ['activeSideMenu' => 'duplicates']);
    }

    public function keepAndTrash(Request $request)
    {
        $keepId = (int) $request->input('keep_id');
        $trashIds = $request->input('trash_ids', []);

        if (!$keepId || empty($trashIds)) {
            return response()->json(['success' => false, 'message' => 'Missing parameters.']);
        }

        DB::table('jobs')->whereIn('id', $trashIds)->update(['job_status_id' => 5]);

        return response()->json(['success' => true, 'trashed' => count($trashIds)]);
    }

    public function dismissGroup(Request $request)
    {
        $hash = $request->input('hash');
        if (!$hash) {
            return response()->json(['success' => false, 'message' => 'Missing hash.']);
        }

        $user = session('user');
        DB::table('dismissed_duplicates')->insertOrIgnore([
            'group_hash'   => $hash,
            'dismissed_by' => $user->id,
            'dismissed_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function bulkDismiss(Request $request)
    {
        $hashes = $request->input('hashes', []);
        if (empty($hashes)) {
            return response()->json(['success' => false, 'message' => 'No groups selected.']);
        }

        $user = session('user');
        $rows = [];
        foreach ($hashes as $hash) {
            $rows[] = ['group_hash' => $hash, 'dismissed_by' => $user->id, 'dismissed_at' => now()];
        }
        DB::table('dismissed_duplicates')->insertOrIgnore($rows);

        return response()->json(['success' => true, 'dismissed' => count($hashes)]);
    }

    public function undismissGroup(Request $request)
    {
        $hash = $request->input('hash');
        if (!$hash) {
            return response()->json(['success' => false, 'message' => 'Missing hash.']);
        }

        DB::table('dismissed_duplicates')->where('group_hash', $hash)->delete();

        return response()->json(['success' => true]);
    }
}
