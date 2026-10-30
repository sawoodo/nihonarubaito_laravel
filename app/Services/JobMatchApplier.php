<?php

namespace App\Services;

use App\Models\Job;
use App\Support\JobRetirement;
use Illuminate\Support\Facades\DB;

/**
 * The write side of the import duplicate check: take the existing job the
 * matcher found, instead of creating a second copy of it.
 *
 * Why this is a service and not inline in the controller: four screens reach
 * it (single XML import, manual create, the publish check, and the card's own
 * buttons), and every path must obey the same two rules.
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
     * retired inside the window.
     *
     * $expectStatus and $expectLink are what the card showed the person. The
     * row is re-read FOR UPDATE inside the transaction and must still match
     * both, or nothing is written: the card is a snapshot in a session, and
     * between rendering it and the click another translator may have edited,
     * trashed or relinked the same job. Overwriting then would silently undo
     * their work and the history row would describe a state that never existed.
     * This is the same guard the Link Check apply step uses -- status 3 AND the
     * exact apply_link the scan saw -- for the same reason.
     *
     * @return array{ok: bool, message: string, revived: bool}
     */
    public static function relink(
        string $jobNo,
        string $newLink,
        int $deleteInDays,
        int $expectStatus,
        string $expectLink,
        string $source,
        string $actor
    ): array {
        $newLink = trim($newLink);
        if (JobMatcher::normLink($newLink) === '') {
            return ['ok' => false, 'message' => 'That apply link is not a usable URL — nothing changed.', 'revived' => false];
        }

        $stale = [
            'ok' => false,
            'revived' => false,
            'message' => "Job {$jobNo} changed since this card was shown — nothing done. "
                . 'Open the job to see its current state, or submit the XML again for a fresh check.',
        ];

        $result = ['ok' => false, 'revived' => false, 'message' => 'Nothing done.'];
        $batch = self::batchId();

        DB::transaction(function () use (
            $jobNo, $newLink, $deleteInDays, $expectStatus, $expectLink,
            $source, $actor, $batch, $stale, &$result
        ) {
            $job = DB::table('jobs')->where('job_no', $jobNo)->lockForUpdate()->first();
            if (! $job) {
                $result = ['ok' => false, 'revived' => false,
                    'message' => "Job {$jobNo} no longer exists — nothing changed."];

                return;
            }

            $status = (int) $job->job_status_id;
            $oldLink = (string) ($job->apply_link ?? '');

            if ($status !== $expectStatus || trim($oldLink) !== trim($expectLink)) {
                $result = $stale;

                return;
            }

            $live = in_array($status, self::LIVE, true);
            $retired = in_array($status, self::RETIRED, true);

            if (! $live && ! $retired) {
                $result = ['ok' => false, 'revived' => false,
                    'message' => "Job {$jobNo} is a draft — open it and edit it directly."];

                return;
            }

            if ($retired) {
                $since = $job->Expire_Date ?: $job->updated_at;
                $days = $since ? (int) floor((time() - strtotime((string) $since)) / 86400) : null;
                if ($days === null || $days > JobMatcher::REVIVE_WINDOW_DAYS) {
                    $result = [
                        'ok' => false,
                        'revived' => false,
                        'message' => "Job {$jobNo} was retired more than " . JobMatcher::REVIVE_WINDOW_DAYS
                            . ' days ago — create the new job instead of reviving a stale page.',
                    ];

                    return;
                }
            }

            // The later of the job's own review date and the new default, so a
            // relink never brings a review date forward. Either way it is in
            // the future, which is what makes a revived job safe to publish:
            // a past delete_at would put it straight back in the overdue list.
            $proposed = date('Y-m-d', strtotime("+{$deleteInDays} days"));
            $current = $job->delete_at ? date('Y-m-d', strtotime((string) $job->delete_at)) : null;
            $newDeleteAt = ($current !== null && $current > $proposed) ? $current : $proposed;

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

            $result = [
                'ok' => true,
                'revived' => $retired,
                'message' => $retired
                    ? "Job {$jobNo} revived and given the new apply link (review date {$newDeleteAt})."
                    : "Job {$jobNo} given the new apply link (review date {$newDeleteAt}).",
            ];
        });

        return $result;
    }

    /**
     * The publish check's button: pending job $dupNo turned out to be $keepNo,
     * so $keepNo takes its link and $dupNo is trashed. One transaction, so a
     * refused relink leaves the pending job exactly as it was.
     *
     * Trashed under source 'dedupe' with "duplicate of job K" on purpose: the
     * copy keeps its link, and JobMatcher::resolveDedupeTrashed() reads that
     * pair to point the next import of this listing at K. Under any other
     * source the card would offer to revive the copy.
     *
     * @return array{ok: bool, message: string, revived: bool}
     */
    public static function relinkAndTrash(
        string $keepNo,
        string $dupNo,
        int $expectKeepStatus,
        string $expectKeepLink,
        string $expectDupLink,
        string $actor
    ): array {
        $stale = [
            'ok' => false,
            'revived' => false,
            'message' => "Job {$dupNo} changed since this card was shown — nothing done. "
                . 'Press Publish again for a fresh check.',
        ];

        if ($keepNo === $dupNo) {
            return $stale;
        }

        $result = $stale;

        DB::transaction(function () use (
            $keepNo, $dupNo, $expectKeepStatus, $expectKeepLink, $expectDupLink, $actor, &$result
        ) {
            $dup = DB::table('jobs')->where('job_no', $dupNo)->lockForUpdate()->first();

            if (! $dup || (int) $dup->job_status_id !== Job::STATUS_PENDING
                || trim((string) $dup->apply_link) !== trim($expectDupLink)) {
                return;
            }

            $relink = self::relink(
                $keepNo,
                (string) $dup->apply_link,
                self::daysLeft($dup->delete_at),
                $expectKeepStatus,
                $expectKeepLink,
                'publish-check',
                $actor
            );

            if (! $relink['ok']) {
                $result = $relink;

                return;
            }

            $trash = JobRetirement::retire(
                [$dupNo], Job::STATUS_TRASHED, 'dedupe', $actor,
                "duplicate of job {$keepNo}", 'pc-' . now()->format('Ymd-His')
            );

            // The row is locked and pending, so this cannot happen. If it
            // does, throwing undoes the relink rather than half-doing the pair.
            if (! $trash['retired']) {
                throw new \RuntimeException("Job {$dupNo} could not be trashed.");
            }

            $relink['message'] .= " Job {$dupNo} trashed as its duplicate.";
            $result = $relink;
        });

        return $result;
    }

    /**
     * Whole days until a pending job's review date, so the job it merges into
     * inherits that date. relink() takes days and keeps the later of the two.
     * The 60 is the default of the manual create form, for a date already past.
     */
    private static function daysLeft(?string $deleteAt): int
    {
        $days = $deleteAt ? (int) ceil((strtotime($deleteAt) - time()) / 86400) : 0;

        return $days > 0 ? $days : 60;
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

    /** The same record for "Publish anyway" at the publish check. */
    public static function logPublishedDespite(
        string $jobNo,
        string $matchedJobNo,
        string $text,
        string $actor
    ): void {
        DB::table('job_status_history')->insert([
            'job_no' => $jobNo,
            'old_status' => Job::STATUS_PENDING,
            'new_status' => Job::STATUS_PUBLISHED,
            'source' => 'publish-check',
            'actor' => $actor,
            'batch' => self::batchId(),
            'reason' => mb_substr("published despite match with {$matchedJobNo}: {$text}", 0, 255),
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
                // The two values the relink guard re-checks under lock.
                'job_status_id' => (int) ($job['job_status_id'] ?? 0),
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
