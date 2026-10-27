<?php

namespace App\Services;

use App\Models\Job;
use Illuminate\Support\Facades\DB;

/**
 * The write side of the import duplicate check: take the existing job the
 * matcher found, instead of creating a second copy of it.
 *
 * Why this is a service and not inline in the controller: three screens reach
 * it (single XML import, manual create, and the card's own button), and every
 * path must obey the same two rules.
 *
 * RULE 1: `date` is never touched. It is the job's posted date — the model's
 * CREATED_AT, `datePosted` in the JobPosting schema, and the sort key of every
 * listing. JobDeduplicator::refreshExisting() overwrote it, which is why that
 * method carries a never-call warning and has no caller.
 *
 * RULE 2: every change leaves a row. A relink writes job_apply_link_history, a
 * revival writes job_status_history, and creating anyway despite a match writes
 * the reason the person typed. Before 2026-10-21 "how was this job retired?"
 * had no answer anywhere in this schema; that is not being repeated.
 */
class JobMatchApplier
{
    /** Statuses a job can be relinked in without changing its status. */
    private const LIVE = [Job::STATUS_PENDING, Job::STATUS_PUBLISHED, Job::STATUS_QUOTA_FULL];

    /** Statuses a job can be revived from, inside the revive window. */
    private const RETIRED = [Job::STATUS_EXPIRED, Job::STATUS_TRASHED];

    public static function batchId(): string
    {
        return 'im-' . now()->format('Ymd-His');
    }

    /**
     * Give job $jobNo the newly sourced apply link, and revive it if it was
     * retired inside the window. Returns a result for the caller to flash.
     *
     * @return array{ok: bool, message: string, revived: bool}
     */
    public static function relink(
        string $jobNo,
        string $newLink,
        int $deleteInDays,
        string $source,
        string $actor
    ): array {
        $newLink = trim($newLink);
        if (JobMatcher::normLink($newLink) === '') {
            return ['ok' => false, 'message' => 'That apply link is not a usable URL — nothing changed.', 'revived' => false];
        }

        $job = DB::table('jobs')->where('job_no', $jobNo)->first();
        if (! $job) {
            return ['ok' => false, 'message' => "Job {$jobNo} no longer exists — nothing changed.", 'revived' => false];
        }

        $status = (int) $job->job_status_id;
        $live = in_array($status, self::LIVE, true);
        $retired = in_array($status, self::RETIRED, true);

        if (! $live && ! $retired) {
            return ['ok' => false, 'message' => "Job {$jobNo} is a draft — open it and edit it directly.", 'revived' => false];
        }

        if ($retired) {
            $since = $job->Expire_Date ?: $job->updated_at;
            $days = $since ? (int) floor((time() - strtotime((string) $since)) / 86400) : null;
            if ($days === null || $days > JobMatcher::REVIVE_WINDOW_DAYS) {
                return [
                    'ok' => false,
                    'revived' => false,
                    'message' => "Job {$jobNo} was retired more than " . JobMatcher::REVIVE_WINDOW_DAYS
                        . ' days ago — create the new job instead of reviving a stale page.',
                ];
            }
        }

        // The later of the job's own review date and the new default, so a
        // relink never brings a review date forward.
        $proposed = date('Y-m-d', strtotime("+{$deleteInDays} days"));
        $current = $job->delete_at ? date('Y-m-d', strtotime((string) $job->delete_at)) : null;
        $newDeleteAt = ($current !== null && $current > $proposed) ? $current : $proposed;

        $batch = self::batchId();
        $oldLink = (string) ($job->apply_link ?? '');

        DB::transaction(function () use ($job, $jobNo, $newLink, $oldLink, $newDeleteAt, $retired, $status, $source, $actor, $batch) {
            $update = [
                'apply_link' => $newLink,
                'delete_at' => $newDeleteAt,
                'updated_at' => now(),
            ];

            if ($retired) {
                $update['job_status_id'] = Job::STATUS_PUBLISHED;
                // Clear the retirement stamp: the expiry tiers read
                // Expire_Date, and a published job holding one would date its
                // next expiry from the old retirement.
                $update['Expire_Date'] = null;
            }

            DB::table('jobs')->where('job_no', $jobNo)->update($update);

            if ($oldLink !== $newLink) {
                DB::table('job_apply_link_history')->insert([
                    'job_no' => $jobNo,
                    'old_apply_link' => $oldLink,
                    'new_apply_link' => $newLink,
                    'source' => $source,
                    'actor' => $actor,
                    'batch' => $batch,
                    'created_at' => now(),
                ]);
            }

            // Written unconditionally, not guarded on ROW_COUNT(): an UPDATE
            // that changes nothing reports 0 rows, which silently skipped 23
            // audit rows in the 2026-10-27 dedupe batch.
            DB::table('job_status_history')->insert([
                'job_no' => $jobNo,
                'old_status' => $status,
                'new_status' => $retired ? Job::STATUS_PUBLISHED : $status,
                'old_delete_at' => $job->delete_at,
                'new_delete_at' => $newDeleteAt,
                'source' => $source,
                'actor' => $actor,
                'batch' => $batch,
                'reason' => $retired
                    ? 'revived instead of re-translated; link rewritten'
                    : 'already live; link rewritten from a new import',
                'created_at' => now(),
            ]);
        });

        return [
            'ok' => true,
            'revived' => $retired,
            'message' => $retired
                ? "Job {$jobNo} revived and given the new apply link (review date {$newDeleteAt})."
                : "Job {$jobNo} given the new apply link (review date {$newDeleteAt}).",
        ];
    }

    /**
     * Record that a person looked at the match and said it was a different
     * job. The reason they typed is the whole point of the row: it is the only
     * evidence that the decision was made deliberately.
     */
    public static function logCreatedDespite(
        string $newJobNo,
        string $matchedJobNo,
        string $text,
        int $newStatus,
        string $source,
        string $actor
    ): void {
        DB::table('job_status_history')->insert([
            'job_no' => $newJobNo,
            'old_status' => null,
            'new_status' => $newStatus,
            'source' => $source,
            'actor' => $actor,
            'batch' => self::batchId(),
            'reason' => mb_substr("created despite match with {$matchedJobNo}: {$text}", 0, 255),
            'created_at' => now(),
        ]);
    }

    /**
     * Flatten what the card needs out of a JobMatcher::findMatches() result,
     * so the view never reaches into a database row.
     *
     * @param  array<int,array<string,mixed>>  $matches
     * @return array<int,array<string,mixed>>
     */
    public static function forCard(array $matches): array
    {
        return array_map(function (array $m) {
            $job = (array) $m['job'];

            return [
                'job_no' => (string) ($job['job_no'] ?? ''),
                'title' => (string) ($job['title'] ?? ''),
                'company_name' => (string) ($job['company_name'] ?? ''),
                'address' => (string) ($job['address'] ?? ''),
                'station' => (string) ($job['station'] ?? ''),
                'wage' => (string) ($job['wage'] ?? ''),
                'apply_link' => (string) ($job['apply_link'] ?? ''),
                'delete_at' => (string) ($job['delete_at'] ?? ''),
                'status_label' => $m['status_label'],
                'retired_days_ago' => $m['retired_days_ago'],
                'reason' => $m['reason'],
                'verdict' => $m['verdict'],
            ];
        }, $matches);
    }
}
