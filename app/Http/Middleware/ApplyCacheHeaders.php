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

        if ($maxAge === null) {
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
