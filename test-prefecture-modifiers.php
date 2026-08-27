<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$modifiers = [
    'hand-cash'     => 'hand cash',
    'daily-payment' => 'daily payment',
    'bed-making'    => 'bed making',
];

$totalUrls = 0;

foreach ($modifiers as $urlSlug => $searchTerm) {
    $prefs = DB::table('jobs as j')
        ->join('prefectures as p', 'j.prefecture_id', '=', 'p.id')
        ->where('j.job_status_id', 3)
        ->where(function ($q) use ($searchTerm) {
            $q->where('j.title', 'like', "%{$searchTerm}%")
              ->orWhere('j.description', 'like', "%{$searchTerm}%");
        })
        ->select('p.english', DB::raw('count(*) as n'))
        ->groupBy('p.id', 'p.english')
        ->having('n', '>=', 2)
        ->pluck('p.english');

    echo "=== {$urlSlug} ({$prefs->count()} prefectures)\n";
    foreach ($prefs as $prefName) {
        $url = "/{$urlSlug}-jobs-in-" . Str::slug($prefName);
        echo "  {$url}\n";
        $totalUrls++;
    }
    echo "\n";
}

echo "Total URLs: {$totalUrls}\n";
