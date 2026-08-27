<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('jobs:auto-trash')->daily()->at('02:00');

Schedule::command('sitemap:generate')->daily()->at('02:30');

// Prune fb_posts older than 7 days — keeps active table lean
Schedule::call(function () {
    $deleted = DB::table('fb_posts')->where('scheduled_at', '<', now()->subDays(7))->delete();
    Log::info("FB posts pruned: {$deleted} deleted, " . DB::table('fb_posts')->count() . ' remaining');
})->daily()->at('03:00');
