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
            return redirect('/admin/login');
        }

        if (!$user->isBackendUser()) {
            return redirect('/');
        }

        return $next($request);
    }
}
