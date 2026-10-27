<?php

namespace App\Console\Commands;

use App\Models\Job;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Report which published jobs have passed their delete_at. By default it
 * changes nothing.
 *
 * This command used to expire them outright. That was wrong: delete_at is OUR
 * review date, not the employer's closing date. It is set to "now + N days"
 * when a job is created or extended, and nothing in this process ever looks at
 * the source listing — so an expiry here means only "we have not checked this
 * job in a while", while the site then tells readers and Google the job has
 * ended (noindex, then a 301, then a 410).
 *
 * The link-check runs of 2026-10-22 and 2026-10-24 measured the gap: of the
 * jobs that had reached their delete_at, most were still open at the source
 * and were extended; the ones that were genuinely gone had to be PROVEN gone
 * by fetching the listing (a 404 on baitoru, a closing notice on shigotoin).
 * Expiring on the date alone would have retired hundreds of live jobs.
 *
 * So the default run writes a list and stops. Expiring is a decision that
 * needs the link check behind it; it now lives behind --expire and is meant to
 * be run by hand, and it records every job it touches in job_status_history.
 *
 * Trace: a default run ALWAYS writes overdue-YYYY-MM-DD.csv, even when the
 * list is empty, so the file and its mtime answer "did the 02:00 schedule
 * fire?". The log could not answer that — production runs LOG_LEVEL=error and
 * this command only ever called Log::info, which is discarded, so a silent log
 * proved nothing either way.
 *
 * The file goes OUTSIDE the web root, in a sibling of public_html, not in
 * storage/app/. On 2026-10-13 /laravel/storage/app/sitemap.xml was publicly
 * fetchable: nginx serves some static extensions straight from disk and never
 * reaches the .htaccess deny. This list names jobs and their source sites, so
 * it is not something to leave one misconfiguration away from the open web.
 */
class AutoTrashJobs extends Command
{
    protected $signature = 'jobs:auto-trash
        {--expire : Actually expire the overdue jobs — manual use only, after the link check}
        {--dry-run : Print what --expire would do; touches nothing, not even the CSV}';

    protected $description = 'Report published jobs past their delete_at (report-only by default; --expire to act)';

    public function handle()
    {
        if ($this->option('dry-run')) {
            return $this->dryRun();
        }

        return $this->option('expire') ? $this->expire() : $this->report();
    }

    private function overdue()
    {
        return Job::where('job_status_id', Job::STATUS_PUBLISHED)
            ->whereNotNull('delete_at')
            ->whereRaw('DATE(`delete_at`) <= CURDATE()');
    }

    /** Default: list the overdue jobs, change nothing. */
    private function report(): int
    {
        $jobs = $this->overdue()
            ->orderBy('delete_at')
            ->get(['job_no', 'title', 'apply_link', 'delete_at']);

        $count = $jobs->count();
        $path = $this->writeCsv($jobs);

        $this->info("Report-only: {$count} overdue, none expired.");
        if ($path) {
            $this->line("  csv: {$path}");
        }
        foreach ($this->byHost($jobs) as $host => $n) {
            $this->line(sprintf('  %-30s %d', $host, $n));
        }

        // LOG_LEVEL=error in production, so info AND warning are both thrown
        // away; error is the only level that reaches laravel.log. Nothing has
        // gone wrong here — this is simply the one line that can prove the run
        // happened and how much work is waiting.
        if ($count > 0) {
            Log::error("jobs:auto-trash report-only: {$count} overdue, none expired"
                . ($path ? " ({$path})" : ''));
        }

        return Command::SUCCESS;
    }

    /** Inspection only — no database write, no CSV, no log line. */
    private function dryRun(): int
    {
        $count = $this->overdue()->count();
        $this->info("[DRY RUN] --expire would expire {$count} jobs. Nothing written.");

        $samples = $this->overdue()
            ->orderBy('delete_at')
            ->limit(10)
            ->get(['job_no', 'title', 'delete_at']);

        foreach ($samples as $job) {
            $this->line("  - Job {$job->job_no}: {$job->title} (delete_at: {$job->delete_at})");
        }

        return Command::SUCCESS;
    }

    /** Manual: expire the overdue jobs and record why in job_status_history. */
    private function expire(): int
    {
        $jobs = $this->overdue()->orderBy('id')->get(['id', 'job_no', 'delete_at']);
        $count = $jobs->count();

        if ($count === 0) {
            $this->info('Nothing overdue — nothing expired.');

            return Command::SUCCESS;
        }

        $batch = 'at-' . now()->format('Ymd-His');
        $now = now();
        $expired = 0;

        DB::transaction(function () use ($jobs, $batch, $now, &$expired) {
            foreach ($jobs->chunk(500) as $chunk) {
                $ids = $chunk->pluck('id')->all();

                $affected = Job::whereIn('id', $ids)
                    ->where('job_status_id', Job::STATUS_PUBLISHED)
                    ->update([
                        'job_status_id' => Job::STATUS_EXPIRED,
                        'Expire_Date' => $now,
                        'updated_at' => $now,
                    ]);

                // Every matched row changes status, so ROW_COUNT is the matched
                // count here and a shortfall means something moved under us.
                if ($affected !== count($ids)) {
                    throw new \RuntimeException(
                        'Expected to expire ' . count($ids) . " jobs in this chunk, updated {$affected} — rolled back."
                    );
                }

                $history = [];
                foreach ($chunk as $job) {
                    $history[] = [
                        'job_no' => $job->job_no,
                        'old_status' => Job::STATUS_PUBLISHED,
                        'new_status' => Job::STATUS_EXPIRED,
                        'old_delete_at' => $job->delete_at,
                        'new_delete_at' => $job->delete_at, // unchanged
                        'source' => 'auto-trash',
                        'actor' => 'cli',
                        'reason' => 'delete_at reached; expired by jobs:auto-trash --expire',
                        'batch' => $batch,
                        'created_at' => $now,
                    ];
                }
                DB::table('job_status_history')->insert($history);

                $expired += $affected;
            }
        });

        $this->info("Expired {$expired} jobs (batch {$batch}).");
        Log::error("jobs:auto-trash --expire: expired {$expired} jobs, batch {$batch}");

        return Command::SUCCESS;
    }

    private function writeCsv($jobs): ?string
    {
        // Sibling of public_html, never under it — see the note on the class.
        $dir = dirname(base_path(), 2) . '/private/overdue';
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            $this->error("Could not create {$dir} — no CSV written.");
            Log::error("jobs:auto-trash could not create {$dir}");

            return null;
        }

        $path = $dir . '/overdue-' . now()->format('Y-m-d') . '.csv';
        $fh = @fopen($path, 'w');
        if (! $fh) {
            $this->error("Could not write {$path}.");
            Log::error("jobs:auto-trash could not write {$path}");

            return null;
        }

        fputcsv($fh, ['job_no', 'title', 'apply_link_host', 'delete_at']);
        foreach ($jobs as $job) {
            fputcsv($fh, [
                $job->job_no,
                $job->title,
                $this->host($job->apply_link),
                $job->delete_at ? $job->delete_at->format('Y-m-d') : '',
            ]);
        }
        fclose($fh);

        return $path;
    }

    private function host(?string $link): string
    {
        $host = strtolower((string) parse_url((string) $link, PHP_URL_HOST));

        return $host === '' ? '(none)' : preg_replace('/^www\./', '', $host);
    }

    /** @return array<string,int> host => count, biggest first */
    private function byHost($jobs): array
    {
        $counts = [];
        foreach ($jobs as $job) {
            $host = $this->host($job->apply_link);
            $counts[$host] = ($counts[$host] ?? 0) + 1;
        }
        arsort($counts);

        return $counts;
    }
}
