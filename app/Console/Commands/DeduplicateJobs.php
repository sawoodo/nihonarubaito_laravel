<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeduplicateJobs extends Command
{
    protected $signature = 'jobs:deduplicate
                            {--dry-run : Show duplicates without removing}
                            {--trash : Set duplicates to status 5 (trashed) instead of 4 (expired)}';

    protected $description = 'Find and remove duplicate active jobs (same company + prefecture + area + title)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $newStatus = $this->option('trash') ? 5 : 4;
        $statusLabel = $newStatus === 5 ? 'trashed' : 'expired';

        // Find duplicate groups: same company + prefecture + area + title
        $groups = DB::table('jobs')
            ->select(
                'company_name', 'prefecture_id', 'area_id', 'title',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('MAX(id) as keep_id'),
                DB::raw('GROUP_CONCAT(id ORDER BY id DESC) as all_ids')
            )
            ->where('job_status_id', 3)
            ->whereNotNull('company_name')
            ->where('company_name', '!=', '')
            ->groupBy('company_name', 'prefecture_id', 'area_id', 'title')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('cnt')
            ->get();

        $this->info("Found {$groups->count()} duplicate groups");

        if ($groups->isEmpty()) {
            $this->info('No duplicates found.');
            return 0;
        }

        $totalRemoved = 0;

        foreach ($groups as $group) {
            $allIds = explode(',', $group->all_ids);
            $keepId = (int) $group->keep_id;
            $removeIds = array_values(array_filter($allIds, fn($id) => (int) $id !== $keepId));

            $titlePreview = mb_substr($group->title, 0, 60);

            if ($dryRun) {
                $this->line("  [{$group->cnt}x] {$group->company_name}");
                $this->line("       Title: {$titlePreview}");
                $this->line("       Keep: #{$keepId} | Remove: #" . implode(', #', $removeIds));
            } else {
                DB::table('jobs')
                    ->whereIn('id', $removeIds)
                    ->update([
                        'job_status_id' => $newStatus,
                        'updated_at'    => now(),
                    ]);
                $totalRemoved += count($removeIds);
                $this->line("  {$statusLabel} " . count($removeIds) . " dupes of #{$keepId} ({$group->company_name})");
            }
        }

        $this->newLine();
        $wouldRemove = $groups->sum(fn($g) => $g->cnt - 1);

        if ($dryRun) {
            $this->info("DRY RUN complete. Would {$statusLabel} {$wouldRemove} duplicate jobs across {$groups->count()} groups.");
        } else {
            $this->info("Done. Set {$totalRemoved} duplicate jobs to {$statusLabel} (status {$newStatus}).");
        }

        return 0;
    }
}
