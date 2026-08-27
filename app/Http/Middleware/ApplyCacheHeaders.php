<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Global middleware (prepended) that applies Cache-Control headers.
 *
 * Because this is prepended to the middleware stack, it wraps ALL other
 * middleware.  On the response path it therefore runs LAST — after
 * StartSession / EncryptCookies have already attached Set-Cookie headers.
 *
 * If the route-level CacheControl middleware tagged the request with a
 * `cache_max_age` attribute, we:
 *   1. Remove every Set-Cookie header (SiteGround's nginx refuses to cache
 *      any response that carries Set-Cookie).
 *   2. Set Cache-Control: public, max-age=N, s-maxage=N.
 *   3. Set Vary: Accept-Encoding so gzip/brotli variants are cached separately.
 *
 * Requests without the attribute (admin, POST, search, etc.) are untouched.
 */
class ApplyCacheHeaders
{
    public function handle(Request $request, Closure $next)
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        $maxAge = $request->attributes->get('cache_max_age');

        if ($maxAge === null || $response->getStatusCode() >= 400) {
            return $response;
        }

        // Never cache a non-English render. A Japanese or Vietnamese session hitting
        // an expired cache entry would otherwise poison the shared CDN cache and serve
        // that language to every visitor for 30 minutes.
        // English is unaffected: user_lang defaults to 1, condition is false,
        // identical code path. Returning early preserves Set-Cookie, which makes
        // nginx refuse to cache the response.
        if ((int) session('user_lang', 1) !== 1) {
            return $response;
        }

        // Never cache an empty homepage render. If the listing query returned zero
        // jobs, returning early preserves Set-Cookie, which makes nginx refuse to
        // cache the response — so an empty page can never poison the shared CDN cache.
        if ($request->attributes->get('skip_cache_empty')) {
            return $response;
        }

        // Strip all Set-Cookie headers so SiteGround's proxy will cache
        $response->headers->remove('Set-Cookie');

        // Remove legacy no-cache directives that Laravel sets by default
        $response->headers->remove('pragma');
        $response->headers->remove('expires');

        // Set public caching headers
        $response->headers->set('Cache-Control', "public, max-age={$maxAge}, s-maxage={$maxAge}");
        $response->headers->set('Vary', 'Accept-Encoding');

        return $response;
    }
}
