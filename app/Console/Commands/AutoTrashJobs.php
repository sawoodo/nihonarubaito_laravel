<?php

namespace App\Console\Commands;

use App\Models\Job;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoTrashJobs extends Command
{
    protected $signature = 'jobs:auto-trash';

    protected $description = 'Trash published jobs whose delete_at date has passed';

    public function handle()
    {
        $affected = Job::where('job_status_id', Job::STATUS_PUBLISHED)
            ->whereNotNull('delete_at')
            ->whereRaw('DATE(`delete_at`) <= CURDATE()')
            ->limit(100)
            ->update(['job_status_id' => Job::STATUS_TRASHED]);

        $this->info("Trashed {$affected} expired jobs.");

        return Command::SUCCESS;
    }
}
