<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Populate areas.slug with unique values, preserving current URL resolution.
 *
 * Winner selection: runs the CURRENT findAreaByName() lookup to determine
 * which area wins each collision. This preserves ranking URLs by construction
 * (nakano → ID 14 Tokyo, fuchu → ID 29 Tokyo, sakura-city → ID 349 Chiba).
 *
 * Losers get {slug}-{prefecture}. These are NEW URLs (didn't exist before),
 * so nothing can regress.
 *
 * Usage:
 *   php artisan areas:populate-slugs --dry-run   (preview decisions)
 *   php artisan areas:populate-slugs              (write slugs)
 */
class PopulateAreaSlugs extends Command
{
    protected $signature   = 'areas:populate-slugs {--dry-run : print the plan, write nothing}';
    protected $description = 'Populate areas.slug with unique values, preserving current URL resolution';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $this->info($dry ? 'DRY RUN — nothing will be written' : 'LIVE — writing areas.slug');

        $before = DB::table('areas')->count();
        $this->line("areas before: {$before}");

        $areas = DB::table('areas')->orderBy('id')->get();

        // ---- pass 1: naive slug for everyone -------------------
        $slugToIds = [];
        foreach ($areas as $a) {
            $slug = Str::slug($a->english);
            if ($slug === '') {
                $this->error("area id={$a->id} produces an empty slug: [{$a->english}]");
                return self::FAILURE;
            }
            $slugToIds[$slug][] = $a->id;
        }

        $collisions = array_filter($slugToIds, fn ($ids) => count($ids) > 1);
        $this->line('collisions: '.count($collisions));

        // ---- pass 2: resolve collisions ------------------------
        // The winner is whichever area the CURRENT lookup returns.
        // That is what preserves ranking URLs — not id order, not job count.
        $assign = [];   // area_id => final slug

        foreach ($slugToIds as $slug => $ids) {
            if (count($ids) === 1) {
                $assign[$ids[0]] = $slug;
                continue;
            }

            $winner   = $this->currentLookup(str_replace('-', ' ', $slug));
            $winnerId = $winner->id ?? null;

            // If the current lookup returns an area OUTSIDE this group,
            // nobody owns the bare slug today (the substring bug). Fall
            // back to most published jobs, then lowest id.
            if (! in_array($winnerId, $ids, false)) {
                $winnerId = collect($ids)
                    ->sortByDesc(function ($id) {
                        return DB::table('jobs')
                            ->where('area_id', $id)
                            ->where('job_status_id', 3)
                            ->count();
                    })
                    ->sortBy(fn ($id) => $id)  // tie-break by lowest ID
                    ->first();
                $this->warn("  {$slug}: no current owner, awarding to id={$winnerId} by job count");
            }

            foreach ($ids as $id) {
                if ((int) $id === (int) $winnerId) {
                    $assign[$id] = $slug;
                    $this->line("  {$slug} -> id={$id}  (KEEPS bare slug)");
                } else {
                    $pref = $this->prefectureSlug($id);
                    $alt  = "{$slug}-{$pref}";
                    $n    = 2;
                    while (in_array($alt, $assign, true) || isset($slugToIds[$alt])) {
                        $alt = "{$slug}-{$pref}-{$n}";
                        $n++;
                    }
                    $assign[$id] = $alt;
                    $this->line("  {$slug} -> id={$id}  becomes [{$alt}]");
                }
            }
        }

        // ---- gate: every assigned slug must be unique ----------
        if (count($assign) !== count(array_unique($assign))) {
            $dupes = array_diff_assoc($assign, array_unique($assign));
            $this->error('ABORT — duplicate slugs after resolution:');
            foreach ($dupes as $id => $s) {
                $this->error("   id={$id} slug={$s}");
            }

            return self::FAILURE;
        }
        $this->info('uniqueness check passed: '.count($assign).' distinct slugs');

        if ($dry) {
            $this->info('dry run complete, nothing written');

            return self::SUCCESS;
        }

        // ---- write --------------------------------------------
        DB::transaction(function () use ($assign) {
            foreach ($assign as $id => $slug) {
                DB::table('areas')->where('id', $id)->update(['slug' => $slug]);
            }
        });

        // ---- conservation -------------------------------------
        $after   = DB::table('areas')->count();
        $written = DB::table('areas')->whereNotNull('slug')->count();
        $this->line("areas after:   {$after}   (must equal {$before})");
        $this->line("slugs written: {$written}  (must equal {$after})");

        if ($after !== $before || $written !== $after) {
            $this->error('CONSERVATION FAILED — investigate before proceeding');

            return self::FAILURE;
        }

        $this->info('done. re-run area-slug-snapshot.php and diff before changing the lookup.');

        return self::SUCCESS;
    }

    /**
     * Exact copy of ListingController::findAreaByName() — deliberately
     * duplicated so the command records real behaviour rather than an
     * assumption about it.
     */
    private function currentLookup(string $name): ?object
    {
        $parts = explode(' ', $name);

        $baseQuery = fn () => DB::table('areas as a')
            ->join('towns as t', 'a.town_id', '=', 't.id')
            ->join('prefectures as p', 't.prefecture_id', '=', 'p.id')
            ->select('a.*', 'p.id as prefecture_id', 'p.english as prefecture');

        if (count($parts) > 1) {
            $pattern = implode('[- ]', $parts);
            $result  = $baseQuery()->whereRaw('a.english REGEXP ?', [$pattern])->first();
            if (! $result) {
                $result = $baseQuery()->where('a.english', 'LIKE', "{$parts[0]}%")->first();
            }

            return $result;
        }

        return $baseQuery()->where('a.english', 'LIKE', "%{$parts[0]}%")->first();
    }

    private function prefectureSlug(int $areaId): string
    {
        $row = DB::table('areas as a')
            ->join('towns as t', 'a.town_id', '=', 't.id')
            ->join('prefectures as p', 't.prefecture_id', '=', 'p.id')
            ->where('a.id', $areaId)
            ->value('p.english');

        return $row ? Str::slug($row) : 'x';
    }
}
