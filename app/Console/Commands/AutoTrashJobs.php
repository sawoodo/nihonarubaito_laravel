<?php

namespace App\Console\Commands;

use App\Models\Job;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoTrashJobs extends Command
{
    protected $signature = 'jobs:auto-trash';

    protected $description = 'Expire published jobs past Expire_Date, trash published jobs past delete_at';

    public function handle()
    {
        // Step 1: Move published jobs past Expire_Date → Expired (status 4)
        $expired = Job::where('job_status_id', Job::STATUS_PUBLISHED)
            ->whereNotNull('Expire_Date')
            ->where('Expire_Date', '<', now())
            ->update([
                'job_status_id' => Job::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);

        // Step 2: Move published jobs past delete_at → Trashed (status 5)
        $trashed = Job::where('job_status_id', Job::STATUS_PUBLISHED)
            ->whereNotNull('delete_at')
            ->whereRaw('DATE(`delete_at`) <= CURDATE()')
            ->update([
                'job_status_id' => Job::STATUS_TRASHED,
                'updated_at' => now(),
            ]);

        $this->info("Expired {$expired} jobs, trashed {$trashed} jobs.");

        if ($expired > 0 || $trashed > 0) {
            Log::info("jobs:auto-trash — expired {$expired}, trashed {$trashed}");
        }

        return Command::SUCCESS;
    }
}
