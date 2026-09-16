<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillFbPostPrefectures extends Command
{
    protected $signature   = 'fbposts:backfill-prefectures {--dry-run : report only, write nothing}';
    protected $description = 'Populate fb_posts.prefecture_id from the job referenced in link';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $this->info($dry ? 'DRY RUN — nothing will be written' : 'LIVE');

        $before = DB::table('fb_posts')->count();
        $this->line("fb_posts rows before: {$before}");

        // job_no -> prefecture_id, loaded once. ~17k rows, trivial memory.
        $jobMap = DB::table('jobs')->pluck('prefecture_id', 'job_no');
        $this->line('jobs in lookup: '.$jobMap->count());

        $updates = [];   // prefecture_id => [post ids]
        $noMatch  = 0;
        $noJobNo  = 0;

        foreach (DB::table('fb_posts')->select('id', 'link')->cursor() as $post) {
            if (! preg_match('#/jobs/([^/?]+)/#', (string) $post->link, $m)) {
                $noJobNo++;
                continue;
            }
            $prefId = $jobMap[$m[1]] ?? null;
            if ($prefId === null) {
                $noMatch++;
                continue;
            }
            $updates[$prefId][] = $post->id;
        }

        $total = array_sum(array_map('count', $updates));
        $this->line("resolved:        {$total}");
        $this->line("no job_no in link: {$noJobNo}");
        $this->line("job_no not found:  {$noMatch}");

        if ($dry) {
            $this->line('');
            $this->line('posts per prefecture (top 15):');
            $counts = collect($updates)->map(fn ($ids) => count($ids))->sortDesc()->take(15);
            foreach ($counts as $prefId => $n) {
                $name = DB::table('prefectures')->where('id', $prefId)->value('english');
                $this->line(sprintf('   %-14s %d', $name ?? "id={$prefId}", $n));
            }
            return self::SUCCESS;
        }

        // Chunked so a single UPDATE never carries 22k ids.
        DB::transaction(function () use ($updates) {
            foreach ($updates as $prefId => $ids) {
                foreach (array_chunk($ids, 500) as $chunk) {
                    DB::table('fb_posts')->whereIn('id', $chunk)
                        ->update(['prefecture_id' => $prefId]);
                }
            }
        });

        // Conservation: row count must not move, only the new column fills.
        $after  = DB::table('fb_posts')->count();
        $filled = DB::table('fb_posts')->whereNotNull('prefecture_id')->count();
        $this->line("fb_posts rows after: {$after}   (must equal {$before})");
        $this->line("prefecture_id filled: {$filled}");

        if ($after !== $before) {
            $this->error('CONSERVATION FAILED — row count changed. Investigate.');
            return self::FAILURE;
        }

        $this->info('done');
        return self::SUCCESS;
    }
}
