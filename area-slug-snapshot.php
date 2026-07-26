<?php
/**
 * area-slug-snapshot.php — nihonarubaito.com
 *
 * READ-ONLY. Runs only SELECT queries. Writes one output file.
 *
 * Records what every area slug resolves to RIGHT NOW, using the exact
 * lookup logic from ListingController::findAreaByName(). This is the
 * frozen baseline: after any slug migration, re-run and diff. Every
 * slug that currently resolves must resolve to the SAME area id, or
 * a ranking URL has moved.
 *
 * Usage (on the server, from the Laravel root):
 *   php area-slug-snapshot.php
 *
 * Output: /tmp/area-slug-snapshot.txt   (slug => resolved_area_id)
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// ---------------------------------------------------------------
// Exact copy of ListingController::findAreaByName(), so the snapshot
// records real behaviour rather than what we assume it is.
// ---------------------------------------------------------------
$findAreaByName = function (string $name) {
    $parts = explode(' ', $name);

    $baseQuery = fn () => DB::table('areas as a')
        ->join('towns as t', 'a.town_id', '=', 't.id')
        ->join('prefectures as p', 't.prefecture_id', '=', 'p.id')
        ->select('a.*', 'p.id as prefecture_id', 'p.english as prefecture');

    if (count($parts) > 1) {
        $pattern = implode('[- ]', $parts);
        $result = $baseQuery()->whereRaw('a.english REGEXP ?', [$pattern])->first();

        if (! $result) {
            $result = $baseQuery()->where('a.english', 'LIKE', "{$parts[0]}%")->first();
        }

        return $result;
    }

    return $baseQuery()->where('a.english', 'LIKE', "%{$parts[0]}%")->first();
};

$areas = DB::table('areas')->orderBy('id')->get();
echo 'areas in table: '.$areas->count().PHP_EOL;

$lines          = [];
$selfResolving  = 0;
$misResolving   = 0;
$unresolvable   = 0;
$slugToAreas    = [];   // slug => [area ids that produce it]
$misResolveList = [];

foreach ($areas as $a) {
    $slug = Str::slug($a->english);
    if ($slug === '') {
        $lines[] = "EMPTY_SLUG\tid={$a->id}\tenglish=[{$a->english}]";
        continue;
    }

    $slugToAreas[$slug][] = $a->id;

    // The controller receives the slug with hyphens, and explodes on spaces.
    // Mirror that: hyphens become spaces before lookup.
    $lookupName = str_replace('-', ' ', $slug);
    $resolved   = $findAreaByName($lookupName);

    $resolvedId = $resolved->id ?? 0;

    if ($resolvedId === 0) {
        $unresolvable++;
        $status = 'UNRESOLVED';
    } elseif ((int) $resolvedId === (int) $a->id) {
        $selfResolving++;
        $status = 'SELF';
    } else {
        $misResolving++;
        $status = 'OTHER';
        $misResolveList[] = [
            'slug'        => $slug,
            'wanted_id'   => $a->id,
            'wanted_name' => $a->english,
            'got_id'      => $resolvedId,
            'got_name'    => $resolved->english ?? '?',
        ];
    }

    // Canonical slug of whatever we actually got — if it differs from the
    // requested slug, line ~253 issues a 301 to a DIFFERENT area's page.
    $resolvedCanonical = $resolved ? Str::slug($resolved->english) : '';
    $redirects         = ($resolvedCanonical !== '' && $resolvedCanonical !== $slug) ? 'REDIRECTS' : '';

    $lines[] = sprintf(
        "%s\t%s\twanted=%d\tgot=%d\t%s",
        $slug, $status, $a->id, $resolvedId, $redirects
    );
}

// ---------------------------------------------------------------
echo PHP_EOL.'=== RESOLUTION SUMMARY ==='.PHP_EOL;
echo "  resolves to itself : {$selfResolving}".PHP_EOL;
echo "  resolves to OTHER  : {$misResolving}".PHP_EOL;
echo "  unresolvable       : {$unresolvable}".PHP_EOL;

$collisions = array_filter($slugToAreas, fn ($ids) => count($ids) > 1);
echo '  slug collisions    : '.count($collisions).PHP_EOL;

// ---------------------------------------------------------------
echo PHP_EOL.'=== COLLISIONS: which area currently WINS ==='.PHP_EOL;
echo '(the winner MUST keep the bare slug after migration)'.PHP_EOL.PHP_EOL;

foreach ($collisions as $slug => $ids) {
    $lookupName = str_replace('-', ' ', $slug);
    $winner     = $findAreaByName($lookupName);
    $winnerId   = $winner->id ?? 0;

    echo "  {$slug}".PHP_EOL;
    foreach ($ids as $id) {
        $a  = DB::table('areas')->where('id', $id)->first();
        $t  = DB::table('towns')->where('id', $a->town_id)->first();
        $p  = $t ? DB::table('prefectures')->where('id', $t->prefecture_id)->first() : null;
        $n  = DB::table('jobs')->where('area_id', $id)->where('job_status_id', 3)->count();
        $mark = ((int) $id === (int) $winnerId) ? ' <<< WINS TODAY' : '';
        printf("      id=%-5d %-28s pref=%-12s published=%-4d%s".PHP_EOL,
            $id, '['.trim($a->english).']', $p->english ?? '?', $n, $mark);
    }
    echo PHP_EOL;
}

// ---------------------------------------------------------------
// Areas whose own slug resolves to a DIFFERENT area are unreachable today.
if ($misResolving > 0) {
    echo '=== UNREACHABLE AREAS (slug resolves elsewhere) ==='.PHP_EOL;
    echo '(these have no working URL right now — substring LIKE steals them)'.PHP_EOL.PHP_EOL;
    foreach (array_slice($misResolveList, 0, 60) as $m) {
        printf("  %-28s wanted id=%-5d [%s]  ->  got id=%-5d [%s]".PHP_EOL,
            $m['slug'], $m['wanted_id'], trim($m['wanted_name']),
            $m['got_id'], trim($m['got_name']));
    }
    if (count($misResolveList) > 60) {
        echo '  ... and '.(count($misResolveList) - 60).' more'.PHP_EOL;
    }
    echo PHP_EOL;
}

// ---------------------------------------------------------------
// The frozen baseline. Sort it so the post-migration diff is meaningful.
sort($lines);
$out = '/tmp/area-slug-snapshot.txt';
file_put_contents($out, implode(PHP_EOL, $lines).PHP_EOL);

echo '=== CONSERVATION COUNTS (must match after migration) ==='.PHP_EOL;
echo '  areas total     : '.DB::table('areas')->count().PHP_EOL;
echo '  towns total     : '.DB::table('towns')->count().PHP_EOL;
echo '  published jobs  : '.DB::table('jobs')->where('job_status_id', 3)->count().PHP_EOL;
echo '  jobs with area  : '.DB::table('jobs')->whereNotNull('area_id')->count().PHP_EOL;

echo PHP_EOL."baseline written to {$out}".PHP_EOL;
echo 'Keep it. After migration, re-run reading the new slug column and diff.'.PHP_EOL;
echo 'Any line changing for a NON-colliding slug means a URL moved.'.PHP_EOL;
