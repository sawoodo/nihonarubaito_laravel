<?php

namespace App\Support;

use App\Models\Job;
use Illuminate\Support\Facades\DB;

/**
 * The one way a job is retired.
 *
 * Every retirement must record WHEN it happened, because the expired-page tier
 * in JobController::handleExpiredJob() counts days from that date: 0-90 days
 * serves the closed page (200 + noindex) with nearby live jobs, 91-365 days a
 * 301 to the location page, and past a year a 410.
 *
 * Before this class there were six paths to status 4 or 5 and they disagreed:
 * the per-job Trash button and both AnalyticsController paths wrote no
 * Expire_Date at all, and the two AnalyticsController ones used DB::table(),
 * which does not even touch updated_at. So a job trashed last week could be
 * dated by an edit months earlier and skip its closed-page period entirely.
 * Measured on 2026-10-27: of 108,332 retired jobs, 97,229 had no Expire_Date,
 * and 197 of the 542 retired pages still earning Google clicks were
 * redirecting early — 141660 was dated 271 days old and had been retired 20.
 *
 * Three things it guarantees that the old paths did not:
 *
 *   1. `Expire_Date = NOW()` on every retirement, so the tier is computed from
 *      the real retirement day and not from whenever the row was last edited.
 *   2. One `job_status_history` row per job ACTUALLY changed, counted from the
 *      same guarded set that was updated — never from ROW_COUNT(), which is 0
 *      for an update that changes nothing and silently dropped 23 audit rows
 *      in the 2026-10-27 dedupe batch.
 *   3. A status guard. A job can be expired only from live (2, 3 or 6), and
 *      trashed from live or from already-expired. So a stale page or a
 *      double-submitted form cannot re-retire a job and overwrite the date of
 *      its original retirement, while the ordinary housekeeping move of
 *      trashing an expired job still works and KEEPS that first date, leaving
 *      the 90-day clock where it was. Un-trashing (5 -> 4) is refused. The
 *      Duplicates page's keep-and-trash had no status check at all and would
 *      trash a job in any state; it gets the guard by coming through here.
 *
 * `date` is never touched. It is the posted date — the model's CREATED_AT,
 * `datePosted` in the JobPosting schema, and the sort key of every listing.
 */
class JobRetirement
{
    /** Live: a job in one of these can be retired. */
    public const LIVE_STATUSES = [Job::STATUS_PENDING, Job::STATUS_PUBLISHED, Job::STATUS_QUOTA_FULL];

    /** The statuses this class is willing to move a job to. */
    public const RETIRED_STATUSES = [Job::STATUS_EXPIRED, Job::STATUS_TRASHED];

    /**
     * Which statuses each target may be reached from.
     *
     * Trashing an already-expired job is ordinary housekeeping -- both show
     * the same page to a visitor -- so 4 -> 5 is allowed, and the original
     * Expire_Date is KEPT so the 90-day clock does not restart. The reverse
     * is not: 5 -> 4 would un-trash a job through a method named retire(),
     * and nothing in the admin asks for it.
     */
    private const ALLOWED_FROM = [
        Job::STATUS_EXPIRED => self::LIVE_STATUSES,
        Job::STATUS_TRASHED => [...self::LIVE_STATUSES, Job::STATUS_EXPIRED],
    ];

    /**
     * Retire jobs, recording the date and the reason.
     *
     * @param  array<int,string>  $jobNos
     * @param  int  $toStatus  Job::STATUS_EXPIRED or Job::STATUS_TRASHED
     * @param  string  $source  who did it, in the job_status_history sense:
     *                          'admin', 'link-check-action', 'duplicates', …
     * @param  callable|null  $guard  optional extra constraints, applied to BOTH
     *                          the read and the write so the pair stays atomic —
     *                          used where a screen has its own staleness check,
     *                          e.g. "and the apply_link is still the one I showed".
     * @return array{batch: string, retired: array<int,string>, skipped: array<int,string>}
     */
    public static function retire(
        array $jobNos,
        int $toStatus,
        string $source,
        string $actor,
        string $reason,
        ?string $batch = null,
        ?callable $guard = null
    ): array {
        if (! in_array($toStatus, self::RETIRED_STATUSES, true)) {
            throw new \InvalidArgumentException("JobRetirement::retire() cannot move a job to status {$toStatus}.");
        }

        $jobNos = array_values(array_unique(array_map('strval', $jobNos)));
        $batch ??= 'rt-' . now()->format('Ymd-His');

        if (! $jobNos) {
            return ['batch' => $batch, 'retired' => [], 'skipped' => []];
        }

        $retired = [];

        DB::transaction(function () use ($jobNos, $toStatus, $source, $actor, $reason, $batch, $guard, &$retired) {
            $now = now();

            $from = self::ALLOWED_FROM[$toStatus];

            // Read the rows under lock FIRST. These are the jobs that will be
            // updated and the jobs that get a history row — one set, so the
            // audit trail cannot drift from what actually changed.
            $read = DB::table('jobs')
                ->whereIn('job_no', $jobNos)
                ->whereIn('job_status_id', $from);
            if ($guard) {
                $guard($read);
            }
            // id, not job_no: `job_no` has only a NON-UNIQUE index and three
            // legacy CI3-era values are shared by two rows each (7vyeO, BtQuB,
            // uOr8M). No live row shares one today, so no current path can hit
            // it, but writing by the id of the rows actually read means a
            // future duplicate cannot widen one request into two retirements.
            $rows = $read->lockForUpdate()->get(['id', 'job_no', 'job_status_id', 'delete_at']);

            if ($rows->isEmpty()) {
                return;
            }

            // Two groups, because the retirement DATE is handled differently.
            // A job going live -> retired is being retired now, so it takes
            // Expire_Date = now even if it is carrying a stale value from an
            // earlier life (a revived job, a quota-full row). A job that is
            // already expired keeps the date of that first retirement, so
            // trashing it later does not restart its 90-day clock; NOW() is
            // used only when it has no date at all.
            $live = $rows->filter(fn ($r) => in_array((int) $r->job_status_id, self::LIVE_STATUSES, true));
            $already = $rows->reject(fn ($r) => in_array((int) $r->job_status_id, self::LIVE_STATUSES, true));

            foreach ([[$live, $now, self::LIVE_STATUSES],
                [$already, DB::raw('COALESCE(`Expire_Date`, ' . self::quoted($now) . ')'), [Job::STATUS_EXPIRED]],
            ] as [$set, $expireValue, $fromStatuses]) {
                if ($set->isEmpty()) {
                    continue;
                }
                $write = DB::table('jobs')
                    ->whereIn('id', $set->pluck('id')->all())
                    ->whereIn('job_status_id', $fromStatuses);
                if ($guard) {
                    $guard($write);
                }
                $write->update([
                    'job_status_id' => $toStatus,
                    'Expire_Date' => $expireValue,
                    'updated_at' => $now,
                ]);
            }

            $nos = $rows->pluck('job_no')->map('strval')->all();

            $history = [];
            foreach ($rows as $row) {
                $history[] = [
                    'job_no' => (string) $row->job_no,
                    'old_status' => (int) $row->job_status_id,
                    'new_status' => $toStatus,
                    // delete_at is not changed by a retirement; both columns
                    // carry it so the row says so rather than leaving a gap.
                    'old_delete_at' => $row->delete_at,
                    'new_delete_at' => $row->delete_at,
                    'source' => $source,
                    'actor' => $actor,
                    'reason' => mb_substr($reason, 0, 255),
                    'batch' => $batch,
                    'created_at' => $now,
                ];
            }
            foreach (array_chunk($history, 500) as $chunk) {
                DB::table('job_status_history')->insert($chunk);
            }

            $retired = $nos;
        });

        return [
            'batch' => $batch,
            'retired' => $retired,
            'skipped' => array_values(array_diff($jobNos, $retired)),
        ];
    }

    /** A datetime as a SQL literal, for the one place a raw expression is needed. */
    private static function quoted(\Carbon\Carbon $t): string
    {
        return "'" . $t->format('Y-m-d H:i:s') . "'";
    }

    /**
     * Same, for screens that hold jobs.id rather than job_no (the Expiring and
     * Duplicates pages both post ids). Resolving here keeps the id/job_no
     * difference out of every caller.
     *
     * @param  array<int,int|string>  $ids
     * @return array{batch: string, retired: array<int,string>, skipped: array<int,string>}
     */
    public static function retireByIds(
        array $ids,
        int $toStatus,
        string $source,
        string $actor,
        string $reason,
        ?string $batch = null
    ): array {
        // Resolve to job_no only to report back; the retirement itself is
        // keyed on these ids, so a shared job_no cannot pull in a second row.
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $jobNos = DB::table('jobs')->whereIn('id', $ids)->pluck('job_no')->map('strval')->all();

        return self::retire($jobNos, $toStatus, $source, $actor, $reason, $batch,
            fn ($q) => $q->whereIn('id', $ids));
    }

    /**
     * The date the expired-page tier should count from.
     *
     * `Expire_Date` is the truth when it is there. When it is not — 97,229 rows
     * retired before this class existed — the best available proxy is the
     * review date, because a job is usually retired when its delete_at comes
     * up: GREATEST(updated_at, LEAST(delete_at, now())).
     *
     * LEAST(delete_at, now()) keeps a review date in the FUTURE from producing
     * a negative age (a job trashed by hand before its date was due), and
     * GREATEST errs towards "retired later", so an undated page gets its
     * closed-page period rather than an early 301. Erring the other way would
     * redirect a page that is still useful; erring this way costs 90 days of a
     * noindexed page nobody can find in search.
     *
     * @param  object  $job  a jobs row or Job model with Expire_Date, updated_at,
     *                       delete_at and date
     */
    public static function tierDate($job): ?\Carbon\Carbon
    {
        $carbon = static function ($v): ?\Carbon\Carbon {
            if (! $v || (is_string($v) && str_starts_with($v, '0000'))) {
                return null;
            }

            return $v instanceof \DateTimeInterface
                ? \Carbon\Carbon::instance($v)
                : \Carbon\Carbon::parse($v);
        };

        if ($expire = $carbon($job->Expire_Date ?? null)) {
            return $expire;
        }

        $updated = $carbon($job->updated_at ?? null);
        $review = $carbon($job->delete_at ?? null);

        if ($review && $review->isFuture()) {
            $review = now();                       // LEAST(delete_at, now())
        }

        if ($updated && $review) {
            return $updated->greaterThan($review) ? $updated : $review;
        }

        return $updated ?? $review ?? $carbon($job->date ?? null);
    }
}
