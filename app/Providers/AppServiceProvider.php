<?php

namespace App\Providers;

use App\View\Composers\AdminComposer;
use App\View\Composers\FrontendComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.frontend', 'pages.*', 'partials.*', 'jobs.*', 'listings.*', 'account.*', 'subscribe.*'], FrontendComposer::class);
        View::composer(['layouts.admin', 'admin.*', 'partials.admin.*'], AdminComposer::class);
    }
}
