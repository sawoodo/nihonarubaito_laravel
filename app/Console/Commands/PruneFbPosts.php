<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneFbPosts extends Command
{
    protected $signature   = 'fbposts:prune {--days=7 : Delete posts older than N days}';
    protected $description = 'Delete fb_posts older than N days (default 7) to keep table lean';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $before = DB::table('fb_posts')->count();
        $deleted = DB::table('fb_posts')
            ->where('scheduled_at', '<', $cutoff)
            ->delete();
        $after = DB::table('fb_posts')->count();

        $this->info("Pruned fb_posts older than {$days} days:");
        $this->line("  Before:  {$before}");
        $this->line("  Deleted: {$deleted}");
        $this->line("  After:   {$after}");

        Log::info("FB posts pruned: {$deleted} deleted (older than {$days}d), {$after} remaining");

        return self::SUCCESS;
    }
}
