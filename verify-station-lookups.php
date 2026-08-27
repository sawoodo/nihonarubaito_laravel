<?php
/**
 * Verify that extracted station slugs actually return jobs.
 *
 * Extracts stations from job.station field via regex, converts to slugs,
 * then runs the ACTUAL search logic that ListingController uses to verify
 * each slug returns >0 jobs.
 *
 * Threshold: 10+ jobs (stations with fewer are too weak to add to sitemap)
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Job;

echo "Extracting stations from job.station field...\n";

// Step 1: Extract stations via regex (same pattern as station-mentions.php)
$stationCounts = [];
foreach (DB::table('jobs')->where('job_status_id', 3)->pluck('station') as $s) {
    if (preg_match_all('/from ([A-Z][A-Za-z-]+) Station/', (string)$s, $m)) {
        foreach ($m[1] as $n) {
            $k = Str::slug($n);
            $stationCounts[$k] = ($stationCounts[$k] ?? 0) + 1;
        }
    }
}

echo "Extracted ".count($stationCounts)." distinct stations\n\n";

// Step 2: Filter to threshold (10+ jobs minimum)
$threshold = 10;
$candidates = array_filter($stationCounts, fn($n) => $n >= $threshold);
arsort($candidates);

echo "Stations with {$threshold}+ mentions: ".count($candidates)."\n\n";

// Step 3: Verify each slug via actual Job::search() logic
echo "Verifying each slug returns jobs via ACTUAL search...\n\n";

$langId = 1;    // English
$langName = 'english';
$verified = [];
$failed = [];

foreach ($candidates as $slug => $extractedCount) {
    // Convert slug back to search query (same as ListingController does)
    $query = str_replace('-', ' ', $slug);

    // Run the ACTUAL search (same call ListingController makes)
    $actualCount = Job::search($langId, $langName, $query, 0, 0)->count();

    if ($actualCount > 0) {
        $verified[$slug] = $actualCount;
        echo "OK    {$slug}  extracted={$extractedCount}  actual={$actualCount}\n";
    } else {
        $failed[$slug] = $extractedCount;
        echo "FAIL  {$slug}  extracted={$extractedCount}  actual=0  (hyphenation mismatch?)\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Verified (returns jobs): ".count($verified)."\n";
echo "Failed (returns 0):      ".count($failed)."\n";

if (count($failed) > 0) {
    echo "\nFailed stations:\n";
    foreach ($failed as $slug => $count) {
        echo "  - {$slug} (extracted {$count} mentions but search returns 0)\n";
    }
    echo "\nThese are likely hyphenation mismatches where the regex extracts\n";
    echo "'Gion-Shijo' but the search for 'gion shijo' doesn't match 'Gion-Shijo'.\n";
}

echo "\n";
if (count($verified) > 0) {
    echo "Safe to add ".count($verified)." verified stations to sitemap.\n";
    exit(0);
} else {
    echo "FAILED: No stations passed verification.\n";
    exit(1);
}
