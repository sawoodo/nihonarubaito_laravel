<?php

namespace App\Services;

use App\Models\Job;
use App\Models\FbPostedLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class FbQueueService
{
    // Territory routing by prefecture
    private const TERRITORY_MAP = [
        'tokyo' => ['Tokyo'],
        'kanto' => ['Saitama', 'Kanagawa', 'Chiba', 'Ibaraki', 'Gunma', 'Tochigi'],
        'osaka' => ['Osaka', 'Kyoto', 'Hyogo', 'Nara', 'Shiga', 'Wakayama'],
    ];

    // Hub stations (proven high-performers)
    private const HUB_STATIONS = [
        'Shinjuku', 'Ikebukuro', 'Ueno', 'Namba', 'Osaka', 'Umeda',
        'Kashiwa', 'Yokohama'
    ];

    // Weak stations (penalty)
    private const WEAK_STATIONS = [
        'Hiroo', 'Ginza', 'Toyosu', 'Kawasaki', 'Sannomiya', 'Kyoto'
    ];

    // Payment hook keywords
    private const PAYMENT_HOOKS = [
        'Daily Payment', 'Weekly Payment', 'Advance Payment',
        'Hand Cash', '手渡し', 'Daily Pay', 'Weekly Pay'
    ];

    /**
     * Get eligible jobs for a page, scored and ranked
     */
    public function getPageQueue(string $page, array $filters = []): Collection
    {
        $jobs = $this->getEligibleJobs($page, $filters);

        // Precompute demand data ONCE for all station×category combos
        $demandData = $this->precomputeDemandData();
        $affiliateDemandData = $this->precomputeDemandData(true);

        return $jobs->map(function ($job) use ($page, $demandData, $affiliateDemandData) {
            $score = $this->calculateScore($job, $demandData);
            return (object) [
                'job' => $job,
                'score' => $score['total'],
                'score_breakdown' => $score['breakdown'],
                'boost_eligible' => $this->isBoostEligible($job, $affiliateDemandData),
                'suggested_format' => null, // Set after sorting
                'headline' => $this->generateHeadline($job),
                'post_url' => $this->generatePostUrl($job, $page, false),
                'boost_url' => $this->generatePostUrl($job, $page, true),
                'days_until_expiry' => $this->daysUntilExpiry($job),
                'is_affiliate' => $this->isAffiliate($job),
                'edited_since_posted' => $this->wasEditedSincePosted($job, $page),
            ];
        })->sortByDesc('score')->values()->map(function ($item, $index) {
            // Top 2 = Text format, rest = Link
            $item->suggested_format = $index < 2 ? 'text' : 'link';
            return $item;
        });
    }

    /**
     * Get jobs not in any territory (site-only bucket)
     */
    public function getSiteOnlyJobs(): Collection
    {
        $allTerritoryPrefectures = collect(self::TERRITORY_MAP)->flatten()->unique()->values()->toArray();

        return Job::select('jobs.*', 'prefectures.english as prefecture_name')
            ->join('prefectures', 'jobs.prefecture_id', '=', 'prefectures.id')
            ->where('jobs.job_status_id', Job::STATUS_PUBLISHED)
            ->where('jobs.lang_id', 1) // English only
            ->whereNotIn('prefectures.english', $allTerritoryPrefectures)
            ->where('jobs.date', '>=', now()->subHours(48))
            ->orderBy('jobs.date', 'desc')
            ->get()
            ->groupBy('prefecture_name');
    }

    /**
     * Get sourcing gaps (station×category with applies but no inventory)
     */
    public function getSourcingGaps(): Collection
    {
        // Get station×category combos with ≥3 affiliate applies (120 days)
        $demandCombos = DB::table('application_logs as al')
            ->join('jobs as j', 'al.job_no', '=', 'j.job_no')
            ->where('al.order_date', '>=', now()->subDays(120))
            ->where('j.apply_link', 'LIKE', '%shigotoin.com%')
            ->select('j.station', 'j.job_category_id', DB::raw('COUNT(*) as applies'))
            ->groupBy('j.station', 'j.job_category_id')
            ->having('applies', '>=', 3)
            ->get();

        // Get current affiliate inventory by station×category
        $currentInventory = Job::where('job_status_id', Job::STATUS_PUBLISHED)
            ->where('lang_id', 1)
            ->where('apply_link', 'LIKE', '%shigotoin.com%')
            ->select('station', 'job_category_id', DB::raw('COUNT(*) as count'))
            ->groupBy('station', 'job_category_id')
            ->get()
            ->keyBy(fn($i) => "{$i->station}_{$i->job_category_id}");

        // Find gaps (demand but no inventory)
        return $demandCombos->filter(function ($combo) use ($currentInventory) {
            $key = "{$combo->station}_{$combo->job_category_id}";
            return !isset($currentInventory[$key]);
        })->map(function ($combo) {
            return (object) [
                'station' => $combo->station,
                'category_id' => $combo->job_category_id,
                'category_name' => $this->getCategoryName($combo->job_category_id),
                'applies' => $combo->applies,
            ];
        });
    }

    // ── Private helpers ──

    /**
     * Precompute demand data for all station×category combos (run ONCE)
     */
    private function precomputeDemandData(bool $affiliateOnly = false): array
    {
        // Get application_logs applies
        $query1 = DB::table('application_logs as al')
            ->join('jobs as j', 'al.job_no', '=', 'j.job_no')
            ->where('al.order_date', '>=', now()->subDays(120))
            ->select('j.station', 'j.job_category_id', DB::raw('COUNT(*) as applies'));

        if ($affiliateOnly) {
            $query1->where('j.apply_link', 'LIKE', '%shigotoin.com%');
        }

        $appliesData1 = $query1->groupBy('j.station', 'j.job_category_id')->get();

        // Get secondary_applies
        $query2 = DB::table('secondary_applies as sa')
            ->join('jobs as j', 'sa.job_no', '=', 'j.job_no')
            ->where('sa.apply_date', '>=', now()->subDays(120))
            ->select('j.station', 'j.job_category_id', DB::raw('COUNT(*) as applies'));

        if ($affiliateOnly) {
            $query2->where('j.apply_link', 'LIKE', '%shigotoin.com%');
        }

        $appliesData2 = $query2->groupBy('j.station', 'j.job_category_id')->get();

        // Merge both datasets into keyed array
        $result = [];
        foreach ($appliesData1 as $row) {
            $key = "{$row->station}_{$row->job_category_id}";
            $result[$key] = $row->applies;
        }
        foreach ($appliesData2 as $row) {
            $key = "{$row->station}_{$row->job_category_id}";
            $result[$key] = ($result[$key] ?? 0) + $row->applies;
        }

        return $result;
    }

    private function getEligibleJobs(string $page, array $filters): Collection
    {
        $prefectures = self::TERRITORY_MAP[$page] ?? [];

        $query = Job::select('jobs.*', 'prefectures.english as prefecture_name')
            ->join('prefectures', 'jobs.prefecture_id', '=', 'prefectures.id')
            ->with(['category', 'language']) // Eager load relationships
            ->where('jobs.job_status_id', Job::STATUS_PUBLISHED)
            ->where('jobs.lang_id', 1) // English only
            ->whereIn('prefectures.english', $prefectures)
            // Bound to recent candidates only (last 60 days)
            ->where('jobs.date', '>=', now()->subDays(60))
            // Exclude jobs expiring in next 7 days (too soon to post)
            ->where(function($q) {
                $q->whereNull('jobs.delete_at')
                  ->orWhere('jobs.delete_at', '>', now()->addDays(7));
            })
            ->limit(500); // Safety cap per page

        // Exclude jobs posted to this page in last 14 days
        $recentlyPosted = FbPostedLog::where('page', $page)
            ->where('posted_at', '>=', now()->subDays(14))
            ->pluck('job_no');
        $query->whereNotIn('jobs.job_no', $recentlyPosted);

        // Exclude jobs posted to this page 2+ times lifetime
        $overPosted = FbPostedLog::where('page', $page)
            ->select('job_no', DB::raw('COUNT(*) as post_count'))
            ->groupBy('job_no')
            ->having('post_count', '>=', 2)
            ->pluck('job_no');
        $query->whereNotIn('jobs.job_no', $overPosted);

        // Apply filters
        if (!empty($filters['categories'])) {
            $query->whereIn('jobs.job_category_id', $filters['categories']);
        }
        if (!empty($filters['wage_floor'])) {
            $query->where('jobs.wage', '>=', $filters['wage_floor']);
        }
        if (!empty($filters['hook_only'])) {
            $query->where(function ($q) {
                foreach (self::PAYMENT_HOOKS as $hook) {
                    $q->orWhere('jobs.title', 'LIKE', "%{$hook}%");
                }
            });
        }
        if (!empty($filters['affiliate_only'])) {
            $query->where('jobs.apply_link', 'LIKE', '%shigotoin.com%');
        }

        return $query->get();
    }

    private function calculateScore($job, array $demandData): array
    {
        $breakdown = [];
        $total = 0;

        // Demand points (station×category applies, 120 days) - from precomputed data
        $key = "{$job->station}_{$job->job_category_id}";
        $demandApplies = $demandData[$key] ?? 0;
        if ($demandApplies >= 50) {
            $breakdown[] = '+3 demand (≥50 applies)';
            $total += 3;
        } elseif ($demandApplies >= 20) {
            $breakdown[] = '+2 demand (20-49 applies)';
            $total += 2;
        } elseif ($demandApplies >= 5) {
            $breakdown[] = '+1 demand (5-19 applies)';
            $total += 1;
        }

        // Payment hook
        if ($this->hasPaymentHook($job->title)) {
            $breakdown[] = '+3 payment hook';
            $total += 3;
        }

        // Category bonuses
        if ($job->job_category_id == 4) { // Bed Making
            $breakdown[] = '+2 bed making';
            $total += 2;
        } elseif ($job->job_category_id == 1) { // Packing
            $breakdown[] = '+1 packing';
            $total += 1;
        }

        // Wage bonuses
        $wage = (int) preg_replace('/[^\d]/', '', $job->wage ?? '0');
        if ($wage >= 1500) {
            $breakdown[] = '+2 wage ≥¥1,500';
            $total += 2;
        } elseif ($wage >= 1300) {
            $breakdown[] = '+1 wage ≥¥1,300';
            $total += 1;
        }

        // Station tier
        if (in_array($job->station, self::HUB_STATIONS)) {
            $breakdown[] = '+1 hub station';
            $total += 1;
        } elseif (in_array($job->station, self::WEAK_STATIONS)) {
            $breakdown[] = '-1 weak station';
            $total -= 1;
        }

        // Affiliate
        if ($this->isAffiliate($job)) {
            $breakdown[] = '+2 affiliate';
            $total += 2;
        }

        return [
            'total' => $total,
            'breakdown' => $breakdown,
        ];
    }

    private function isBoostEligible($job, array $affiliateDemandData): bool
    {
        // Must be affiliate
        if (!$this->isAffiliate($job)) {
            return false;
        }

        // Must have ≥10 days until expiry
        if ($this->daysUntilExpiry($job) < 10) {
            return false;
        }

        // Must have payment hook OR be bed-making
        if (!$this->hasPaymentHook($job->title) && $job->job_category_id != 4) {
            return false;
        }

        // Station×category must have ≥3 affiliate applies (120 days) - from precomputed data
        $key = "{$job->station}_{$job->job_category_id}";
        $affiliateApplies = $affiliateDemandData[$key] ?? 0;
        if ($affiliateApplies < 3) {
            return false;
        }

        return true;
    }

    private function getStationCategoryApplies(string $station, int $categoryId, bool $affiliateOnly = false): int
    {
        $query = DB::table('application_logs as al')
            ->join('jobs as j', 'al.job_no', '=', 'j.job_no')
            ->where('al.order_date', '>=', now()->subDays(120))
            ->where('j.station', $station)
            ->where('j.job_category_id', $categoryId);

        if ($affiliateOnly) {
            $query->where('j.apply_link', 'LIKE', '%shigotoin.com%');
        }

        // Also count secondary_applies (email applies)
        $affiliateCount = $query->count();

        $secondaryCount = DB::table('secondary_applies as sa')
            ->join('jobs as j', 'sa.job_no', '=', 'j.job_no')
            ->where('sa.apply_date', '>=', now()->subDays(120))
            ->where('j.station', $station)
            ->where('j.job_category_id', $categoryId)
            ->when($affiliateOnly, fn($q) => $q->where('j.apply_link', 'LIKE', '%shigotoin.com%'))
            ->count();

        return $affiliateCount + $secondaryCount;
    }

    private function isAffiliate($job): bool
    {
        return str_contains($job->apply_link ?? '', 'shigotoin.com');
    }

    private function hasPaymentHook(string $title): bool
    {
        foreach (self::PAYMENT_HOOKS as $hook) {
            if (stripos($title, $hook) !== false) {
                return true;
            }
        }
        return false;
    }

    private function daysUntilExpiry($job): int
    {
        if (!$job->delete_at) {
            return 999;
        }
        return max(0, now()->diffInDays($job->delete_at, false));
    }

    private function wasEditedSincePosted($job, string $page): bool
    {
        $lastPost = FbPostedLog::where('job_no', $job->job_no)
            ->where('page', $page)
            ->orderBy('posted_at', 'desc')
            ->first();

        if (!$lastPost) {
            return false;
        }

        return $job->updated_at > $lastPost->posted_at;
    }

    private function generateHeadline($job): string
    {
        $hook = '';
        foreach (self::PAYMENT_HOOKS as $hookWord) {
            if (stripos($job->title, $hookWord) !== false) {
                $hook = $hookWord . ' — ';
                break;
            }
        }

        $category = $this->getCategoryName($job->job_category_id);
        $station = $job->station;
        $wage = preg_replace('/[^\d]/', '', $job->wage ?? '0');

        return "{$hook}{$category} at {$station} Station 駅, ¥{$wage}/hr";
    }

    private function generatePostUrl($job, string $page, bool $isBoost): string
    {
        $baseUrl = url($job->detail_path);

        if ($isBoost) {
            $station = str_replace(' ', '_', strtolower($job->station));
            $category = strtolower($this->getCategoryName($job->job_category_id));
            return "{$baseUrl}?utm_source=fb&utm_medium=boost&utm_campaign={$station}_{$category}";
        }

        return "{$baseUrl}?utm_source=fb&utm_medium=social&utm_campaign={$page}_organic";
    }

    private function getCategoryName(int $categoryId): string
    {
        return match ($categoryId) {
            1 => 'Packing/Sorting',
            2 => 'Restaurant',
            3 => 'Convenience Store',
            4 => 'Bed Making/Cleaning',
            5 => 'Delivery',
            default => 'Other',
        };
    }
}
