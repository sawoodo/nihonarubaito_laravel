<?php

namespace App\Console\Commands;

use App\Models\Job;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoTrashJobs extends Command
{
    protected $signature = 'jobs:auto-trash {--dry-run : Show what would be expired without changing anything}';

    protected $description = 'Move expired jobs (past delete_at date) from Published (3) to Expired (4)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $query = Job::where('job_status_id', Job::STATUS_PUBLISHED)
            ->whereNotNull('delete_at')
            ->whereRaw('DATE(`delete_at`) <= CURDATE()');

        if ($dryRun) {
            $count = $query->count();
            $this->info("[DRY RUN] Would expire {$count} jobs.");

            $samples = Job::where('job_status_id', Job::STATUS_PUBLISHED)
                ->whereNotNull('delete_at')
                ->whereRaw('DATE(`delete_at`) <= CURDATE()')
                ->limit(10)
                ->get(['job_no', 'title', 'delete_at']);

            foreach ($samples as $job) {
                $this->line("  - Job {$job->job_no}: {$job->title} (delete_at: {$job->delete_at})");
            }

            return Command::SUCCESS;
        }

        $affected = $query->update([
            'job_status_id' => Job::STATUS_EXPIRED,
            'updated_at' => now(),
        ]);

        $this->info("Expired {$affected} jobs.");

        if ($affected > 0) {
            Log::info("jobs:auto-trash expired {$affected} jobs");
        }

        return Command::SUCCESS;
    }
}
