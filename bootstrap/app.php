<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(\App\Http\Middleware\ApplyCacheHeaders::class);

        $middleware->validateCsrfTokens(except: [
            'jobs/areas/get',
            'admin/jobs/attach-image',
            'admin/jobs/detach-image',
            'admin/jobs/image/attach',
            'admin/jobs/image/detach',
            'admin/jobs/get-areas',
            'admin/jobs/get_areas',
            'admin/jobs/create-from-xml/upload-file',
            'admin/application-logs/list',
            'admin/secondary-applies/list',
            'admin/images/list',
            'admin/images/upload',
            'admin/images/*/update-info',
            'admin/images/*/update',
            'admin/blog-posts/list',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'cache' => \App\Http\Middleware\CacheControl::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
