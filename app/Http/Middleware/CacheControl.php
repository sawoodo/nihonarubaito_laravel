<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Route-level middleware: marks the request with the desired cache duration.
 * Usage in routes: ->middleware('cache:1800')
 *
 * The actual Cache-Control headers are applied by ApplyCacheHeaders (global middleware)
 * which runs at the outermost layer, after session cookies have been set.
 */
class CacheControl
{
    public function handle(Request $request, Closure $next, string $maxAge = '1800')
    {
        $request->attributes->set('cache_max_age', (int) $maxAge);
        return $next($request);
    }
}
