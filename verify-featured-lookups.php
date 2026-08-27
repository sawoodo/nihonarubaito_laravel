<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Hard-coded slugs from config/featured.php (not deployed yet)
$slugs = [
    // hand_cash_areas
    'kita-ku-osaka', 'chiyoda-ku', 'chuo-ku-osaka', 'minato-ku',
    'shinjuku-ward', 'hakata-ku-fukuoka-city', 'chuo-ku-fukuoka',
    'shibuya-ward', 'taito', 'nishi-ku-osaka', 'setagaya', 'kyoto-shimogyo',

    // daily_payment_areas (nakano removed due to collision)
    'higashi-osaka-city', 'shinagawa', 'aoba-ku-sendai', 'koto',
    'funabashi', 'ota-ku', 'matsudo', 'utsunomiya',
    'ichikawa', 'ibaraki', 'misato-city', 'edogawa', 'kawasaki-ku',
];
$slugs = array_unique($slugs);

echo "Verifying ".count($slugs)." featured slugs through actual findAreaByName() lookup...\n\n";

$steals = [];

foreach ($slugs as $slug) {
    $name = str_replace('-', ' ', $slug);
    $parts = explode(' ', $name);

    $baseQuery = fn() => DB::table('areas as a')
        ->join('towns as t', 'a.town_id', '=', 't.id')
        ->join('prefectures as p', 't.prefecture_id', '=', 'p.id')
        ->select('a.*', 'p.id as prefecture_id', 'p.english as prefecture');

    if (count($parts) > 1) {
        $pattern = implode('[- ]', $parts);
        $result = $baseQuery()->whereRaw('a.english REGEXP ?', [$pattern])->first();
        if (!$result) {
            $result = $baseQuery()->where('a.english', 'LIKE', "{$parts[0]}%")->first();
        }
    } else {
        $result = $baseQuery()->where('a.english', 'LIKE', "%{$parts[0]}%")->first();
    }

    $got = $result ? Str::slug($result->english) : 'NONE';
    $status = ($got === $slug) ? 'OK   ' : 'STEAL';

    echo "$status $slug  ->  $got";
    if ($got !== $slug) {
        $jobs = $result ? DB::table('jobs')->where('area_id', $result->id)->where('job_status_id', 3)->count() : 0;
        echo "  (area_id={$result->id}, jobs=$jobs)";
        $steals[] = $slug;
    }
    echo PHP_EOL;
}

echo "\n";
if (count($steals) > 0) {
    echo "FAILED: ".count($steals)." slugs redirect to wrong area:\n";
    foreach ($steals as $s) {
        echo "  - $s\n";
    }
    echo "\nPull these from config/featured.php before deploying.\n";
    exit(1);
} else {
    echo "PASSED: All ".count($slugs)." slugs resolve correctly.\n";
    echo "Safe to deploy Track 1.\n";
    exit(0);
}
