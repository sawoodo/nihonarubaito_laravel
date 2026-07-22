<?php

namespace App\View\Composers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminComposer
{
    public function compose(View $view)
    {
        $user = session('user');

        $data = [
            'site_name'  => config('app.name', 'Nihon Arubaito'),
            'role_id'    => $user->role_id ?? null,
            'admin_user' => $user,
        ];

        // Expiring jobs badge count for sidebar
        if ($user) {
            $data['expiringTodayCount'] = DB::table('jobs')
                ->where('job_status_id', 3)
                ->whereNotNull('delete_at')
                ->whereDate('delete_at', now()->format('Y-m-d'))
                ->count();

            // High-confidence duplicate groups count (exact: company+pref+area+title)
            $data['highDuplicateCount'] = DB::table('jobs')
                ->select(DB::raw('COUNT(*) as cnt'))
                ->whereIn('job_status_id', [1, 3])
                ->groupBy('company_name', 'prefecture_id', 'area_id', 'title')
                ->havingRaw('COUNT(*) >= 2')
                ->get()
                ->count();
        }

        $view->with($data);
    }
}
