<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = session('user');

        if (!session('loggedin') || !$user) {
            // guest() stashes the URL being requested in the session as
            // `url.intended`, so AuthController can send the user back to the
            // page they actually wanted instead of always to /admin/jobs.
            // Only GETs are remembered — replaying a POST after login would
            // re-submit a form the user never saw the result of.
            return $request->isMethod('get')
                ? redirect()->guest('/admin/login')
                : redirect('/admin/login');
        }

        if (!$user->isBackendUser()) {
            return redirect('/');
        }

        return $next($request);
    }
}
