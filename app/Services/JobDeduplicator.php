<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class JobDeduplicator
{
    /**
     * Normalize a title for comparison: lowercase, strip special chars,
     * remove common filler words, collapse whitespace.
     */
    public static function normalizeTitle(string $title): string
    {
        $title = mb_strtolower(trim($title));
        $title = preg_replace('/[^a-z0-9\x{3000}-\x{9FFF}\x{FF00}-\x{FFEF}\s]/u', ' ', $title);
        // Remove common English filler words
        $stopWords = ['part', 'time', 'job', 'jobs', 'at', 'in', 'near', 'station', 'the', 'a', 'an', 'for', 'and', 'of'];
        $title = preg_replace('/\b(' . implode('|', $stopWords) . ')\b/i', ' ', $title);
        return preg_replace('/\s+/', ' ', trim($title));
    }

    /**
     * Check if a job is a duplicate of an existing active job.
     * Returns the best match found (highest confidence first).
     *
     * @return array|null  ['level' => 'high'|'medium'|'low', 'job' => object]
     */
    public static function findDuplicate(
        string $companyName,
        int $prefectureId,
        int $areaId,
        string $title,
        ?int $excludeJobId = null
    ): ?array {
        if (empty($title)) {
            return null;
        }

        $baseQuery = DB::table('jobs')
            ->whereIn('job_status_id', [1, 2, 3]) // Draft, Pending, Published
            ->select('id', 'job_no', 'title', 'company_name', 'prefecture_id', 'area_id', 'date', 'delete_at', 'job_status_id');

        if ($excludeJobId) {
            $baseQuery->where('id', '!=', $excludeJobId);
        }

        // Level 1 - EXACT: same company + prefecture + area + title
        if (!empty($companyName)) {
            $exact = (clone $baseQuery)
                ->where('company_name', $companyName)
                ->where('prefecture_id', $prefectureId)
                ->where('area_id', $areaId)
                ->where('title', $title)
                ->orderByDesc('id')
                ->first();

            if ($exact) {
                return ['level' => 'high', 'job' => $exact];
            }
        }

        // Level 2 - STRONG: same company + prefecture + similar title (first 30 chars or normalized match)
        if (!empty($companyName)) {
            $candidates = (clone $baseQuery)
                ->where('company_name', $companyName)
                ->where('prefecture_id', $prefectureId)
                ->orderByDesc('id')
                ->limit(50)
                ->get();

            $normalizedNew = self::normalizeTitle($title);
            $first30New = mb_substr($title, 0, 30);

            foreach ($candidates as $c) {
                if (mb_substr($c->title, 0, 30) === $first30New || self::normalizeTitle($c->title) === $normalizedNew) {
                    return ['level' => 'medium', 'job' => $c];
                }
            }
        }

        // Level 3 - POSSIBLE: exact same title anywhere
        $sameTitle = (clone $baseQuery)
            ->where('title', $title)
            ->orderByDesc('id')
            ->first();

        if ($sameTitle) {
            return ['level' => 'low', 'job' => $sameTitle];
        }

        return null;
    }

    /**
     * Find active job with the exact same apply_link (Tier 0 — highest priority).
     * Same Baitoru/Shigotoin URL = same job, no fuzzy matching needed.
     *
     * @return object|null  The existing job row, or null if no match.
     */
    public static function findByApplyLink(string $applyLink): ?object
    {
        $applyLink = trim($applyLink);
        if (empty($applyLink) || $applyLink === '123') {
            return null;
        }

        return DB::table('jobs')
            ->where('apply_link', $applyLink)
            ->whereIn('job_status_id', [2, 3]) // Pending, Published
            ->select('id', 'job_no', 'title', 'company_name', 'prefecture_id', 'area_id', 'date', 'delete_at', 'job_status_id')
            ->orderByDesc('id')
            ->first();
    }

        /**
     * Refresh an existing job's expiry date instead of creating a duplicate.
     */
    public static function refreshExisting(int $existingJobId, ?string $newDeleteAt = null): void
    {
        $update = [
            'date'       => now(),
            'updated_at' => now(),
        ];

        if ($newDeleteAt) {
            $update['delete_at'] = $newDeleteAt;
        }

        DB::table('jobs')->where('id', $existingJobId)->update($update);
    }

    /**
     * Find all duplicate groups among active/draft jobs for the review page.
     * Returns an array of groups, each with confidence level and job list.
     */
    public static function findAllDuplicateGroups(): array
    {
        // Fetch all active + recent draft jobs
        $jobs = DB::table('jobs')
            ->whereIn('job_status_id', [1, 3]) // Draft + Published
            ->select('id', 'job_no', 'title', 'company_name', 'prefecture_id', 'area_id', 'station', 'date', 'delete_at', 'job_status_id', 'apply_link', 'wage')
            ->orderByDesc('id')
            ->get();

        // Get dismissed group hashes
        $dismissed = DB::table('dismissed_duplicates')->pluck('group_hash')->toArray();

        $groups = [];
        $assigned = []; // job IDs already in a group

        // Pass 1: EXACT duplicates (company + prefecture + area + title)
        $exactBuckets = [];
        foreach ($jobs as $job) {
            if (isset($assigned[$job->id])) continue;
            $key = mb_strtolower($job->company_name) . '|' . $job->prefecture_id . '|' . $job->area_id . '|' . mb_strtolower($job->title);
            $exactBuckets[$key][] = $job;
        }

        foreach ($exactBuckets as $bucket) {
            if (count($bucket) < 2) continue;
            $hash = md5('exact|' . $bucket[0]->company_name . '|' . $bucket[0]->prefecture_id . '|' . $bucket[0]->area_id . '|' . $bucket[0]->title);
            if (in_array($hash, $dismissed)) continue;
            $ids = array_map(fn($j) => $j->id, $bucket);
            foreach ($ids as $id) $assigned[$id] = true;
            $groups[] = [
                'level'      => 'high',
                'hash'       => $hash,
                'label'      => $bucket[0]->title,
                'company'    => $bucket[0]->company_name,
                'jobs'       => $bucket,
            ];
        }

        // Pass 2: STRONG duplicates (company + prefecture + normalized title match)
        $strongBuckets = [];
        foreach ($jobs as $job) {
            if (isset($assigned[$job->id])) continue;
            if (empty($job->company_name)) continue;
            $key = mb_strtolower($job->company_name) . '|' . $job->prefecture_id . '|' . self::normalizeTitle($job->title);
            $strongBuckets[$key][] = $job;
        }

        foreach ($strongBuckets as $bucket) {
            if (count($bucket) < 2) continue;
            $hash = md5('strong|' . mb_strtolower($bucket[0]->company_name) . '|' . $bucket[0]->prefecture_id . '|' . self::normalizeTitle($bucket[0]->title));
            if (in_array($hash, $dismissed)) continue;
            $ids = array_map(fn($j) => $j->id, $bucket);
            foreach ($ids as $id) $assigned[$id] = true;
            $groups[] = [
                'level'      => 'medium',
                'hash'       => $hash,
                'label'      => $bucket[0]->title,
                'company'    => $bucket[0]->company_name,
                'jobs'       => $bucket,
            ];
        }

        // Pass 3: POSSIBLE duplicates (exact title anywhere)
        $titleBuckets = [];
        foreach ($jobs as $job) {
            if (isset($assigned[$job->id])) continue;
            $key = mb_strtolower(trim($job->title));
            if (empty($key)) continue;
            $titleBuckets[$key][] = $job;
        }

        foreach ($titleBuckets as $bucket) {
            if (count($bucket) < 2) continue;
            $hash = md5('title|' . mb_strtolower(trim($bucket[0]->title)));
            if (in_array($hash, $dismissed)) continue;
            $ids = array_map(fn($j) => $j->id, $bucket);
            foreach ($ids as $id) $assigned[$id] = true;
            $groups[] = [
                'level'      => 'low',
                'hash'       => $hash,
                'label'      => $bucket[0]->title,
                'company'    => $bucket[0]->company_name ?? '(various)',
                'jobs'       => $bucket,
            ];
        }

        return $groups;
    }
}
