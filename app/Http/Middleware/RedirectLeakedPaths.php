<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectLeakedPaths
{
    /**
     * Retired Hamamatsu ward slugs → 2024 reorganization successors.
     *
     * The six pre-2024 wards were deleted from `areas` 2026-07-21 (Part G). The
     * area-slug fallback sends any orphaned slug to the lowest-id surviving area
     * in the same town — tenryu-ku — which is the WRONG successor for all six.
     * Official mapping (city.hamamatsu.shizuoka.jp): Naka/Higashi/Nishi/Minami +
     * Kita(Mikatahara) → Chuo-ku; Kita(remainder) + Hamakita → Hamana-ku.
     * Do not remove: without these, ~184 jobs' worth of equity consolidates on
     * the wrong ward page.
     */
    private const RETIRED_WARD_SLUGS = [
        '/part-time-jobs-in-hamamatsu-city-naka-ku'    => '/part-time-jobs-in-hamamatsu-chuo-ku',
        '/part-time-jobs-in-hamamatsu-city-higashi-ku' => '/part-time-jobs-in-hamamatsu-chuo-ku',
        '/part-time-jobs-in-hamamatsu-nishi-ku'        => '/part-time-jobs-in-hamamatsu-chuo-ku',
        '/part-time-jobs-in-hamamatsu-minami-ku'       => '/part-time-jobs-in-hamamatsu-chuo-ku',
        '/part-time-jobs-in-hamamatsu-city-kita-ku'    => '/part-time-jobs-in-hamamatsu-hamana-ku',
        '/part-time-jobs-in-hamamatsu-hamakita'        => '/part-time-jobs-in-hamamatsu-hamana-ku',
    ];

    /**
     * Handle leaked /laravel/public/index.php/* URLs from migration era.
     *
     * These URLs were briefly indexed by Google during Laravel migration when
     * base URL was misconfigured. Now serving 200, should 301 to clean URLs.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $uri = $request->getRequestUri();

        // Match: /laravel/public/index.php/part-time-jobs-in-osaka
        if (preg_match('#^/laravel/public/index\.php/(.+)$#', $uri, $matches)) {
            $cleanPath = '/' . $matches[1];
            $fullUrl = $request->getScheme() . '://' . $request->getHost() . $cleanPath;
            return redirect($fullUrl, 301);
        }

        // Match: /laravel/public/index.php (bare, no query string)
        if ($uri === '/laravel/public/index.php' && empty($request->getQueryString())) {
            $fullUrl = $request->getScheme() . '://' . $request->getHost() . '/';
            return redirect($fullUrl, 301);
        }

        // Retired Hamamatsu ward slugs — exact path match, query string preserved
        $path = rtrim($request->getPathInfo(), '/') ?: '/';
        if (isset(self::RETIRED_WARD_SLUGS[$path])) {
            $qs = $request->getQueryString();
            $fullUrl = $request->getScheme() . '://' . $request->getHost()
                . self::RETIRED_WARD_SLUGS[$path] . ($qs ? '?' . $qs : '');
            return redirect($fullUrl, 301);
        }

        return $next($request);
    }
}
