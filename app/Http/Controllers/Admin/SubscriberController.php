<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobCategoryPreference;
use App\Models\JobLocationPreference;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriberController extends Controller
{
    private function authorizeAdmin()
    {
        if (session('user')->role_id !== User::ROLE_ADMIN) {
            abort(403, 'You are not authorized.');
        }
    }

    public function index(Request $request, $page = null)
    {
        $this->authorizeAdmin();

        $perPage = 20;
        $totalRows = User::subscribers()
            ->join('user_info', 'users.id', '=', 'user_info.user_id')
            ->count();

        // Pagination offset
        $currentPage = max((int) $page, 1);
        $offset = ($currentPage - 1) * $perPage;

        $subscribers = User::select([
                'users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.created_at',
                'ui.age', 'ui.gender', 'ui.phone', 'ui.japanese_level',
                'c.english as nationality',
                'l.english as user_selected_lang',
                DB::raw('GROUP_CONCAT(DISTINCT jc.english) as job_categories'),
                DB::raw('GROUP_CONCAT(DISTINCT a.english) as areas'),
            ])
            ->join('user_info as ui', 'users.id', '=', 'ui.user_id')
            ->join('countries as c', 'ui.country_id', '=', 'c.id')
            ->leftJoin('languages as l', 'ui.user_selected_lang', '=', 'l.id')
            ->leftJoin('job_category_preferences as jcp', 'users.id', '=', 'jcp.user_id')
            ->leftJoin('categories as jc', 'jcp.job_category_id', '=', 'jc.id')
            ->leftJoin('job_location_preferences as jlp', 'users.id', '=', 'jlp.user_id')
            ->leftJoin('areas as a', 'jlp.area_id', '=', 'a.id')
            ->where('users.role_id', User::ROLE_SUBSCRIBER)
            ->groupBy('users.id')
            ->orderByDesc('users.id')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $pagination = $this->buildAdminPagination(
            url('admin/subscribers/page'),
            $totalRows,
            $perPage,
            $currentPage
        );

        return view('admin.subscribers.index', [
            'activeSideMenu' => 'subscribers',
            'subscribers'    => $subscribers,
            'pagination'     => $pagination,
        ]);
    }

    public function detail($id)
    {
        $this->authorizeAdmin();

        $subscriber = User::select([
                'users.id', 'users.first_name', 'users.last_name', 'users.email',
                'ui.age', 'ui.gender', 'ui.phone', 'ui.japanese_level',
                'c.english as nationality',
                'l.english as user_selected_lang',
            ])
            ->join('user_info as ui', 'users.id', '=', 'ui.user_id')
            ->join('countries as c', 'ui.country_id', '=', 'c.id')
            ->leftJoin('languages as l', 'ui.user_selected_lang', '=', 'l.id')
            ->where('users.id', $id)
            ->where('users.role_id', User::ROLE_SUBSCRIBER)
            ->first();

        if (!$subscriber) {
            abort(404);
        }

        // Get category preferences with names
        $categories = JobCategoryPreference::select('jc.english as category')
            ->from('job_category_preferences as jcp')
            ->join('categories as jc', 'jcp.job_category_id', '=', 'jc.id')
            ->where('jcp.user_id', $id)
            ->get();

        // Get location preferences with names, grouped by prefecture
        $locationRows = JobLocationPreference::select([
                'jlp.prefecture_id', 'p.english as prefecture', 'a.english as area',
            ])
            ->from('job_location_preferences as jlp')
            ->join('areas as a', 'jlp.area_id', '=', 'a.id')
            ->join('prefectures as p', 'jlp.prefecture_id', '=', 'p.id')
            ->where('jlp.user_id', $id)
            ->get();

        $locations = [];
        foreach ($locationRows as $row) {
            $locations[$row->prefecture][] = $row->area;
        }

        return view('admin.subscribers.detail', [
            'activeSideMenu' => 'subscribers',
            'subscriber'     => $subscriber,
            'categories'     => $categories,
            'locations'      => $locations,
        ]);
    }

    public function changePassword(Request $request, $id)
    {
        $this->authorizeAdmin();

        $subscriber = User::where('id', $id)
            ->where('role_id', User::ROLE_SUBSCRIBER)
            ->first();

        if (!$subscriber) {
            abort(404);
        }

        if ($request->isMethod('post')) {
            $request->validate([
                'new_password'     => 'required|min:6',
                'confirm_password' => 'required|min:6|same:new_password',
            ]);

            // Hash with SHA256 + encryption_key (CI3 compatible)
            $hashed = hash('sha256', $request->input('confirm_password') . 'WdNEmNQES6IXmVcKCI1QoypA4sOUHpWC');

            User::where('id', $id)->update(['password' => $hashed]);

            return redirect()->route('admin.subscribers.change-password', $id)
                ->with('success', 'Password has been changed.');
        }

        return view('admin.subscribers.change-password', [
            'activeSideMenu' => 'subscribers',
            'subscriber'     => $subscriber,
        ]);
    }

    private function buildAdminPagination(string $baseUrl, int $totalRows, int $perPage, int $currentPage): string
    {
        $totalPages = (int) ceil($totalRows / $perPage);

        if ($totalPages <= 1) {
            return '';
        }

        $html = '<ul class="pagination">';

        // Previous
        if ($currentPage > 1) {
            $html .= '<li><a href="' . $baseUrl . '/' . ($currentPage - 1) . '">&laquo;</a></li>';
        } else {
            $html .= '<li class="disabled"><span>&laquo;</span></li>';
        }

        // Page numbers
        $start = max(1, $currentPage - 3);
        $end = min($totalPages, $currentPage + 3);

        if ($start > 1) {
            $html .= '<li><a href="' . $baseUrl . '/1">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="disabled"><span>...</span></li>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $currentPage) {
                $html .= '<li class="active"><span>' . $i . '</span></li>';
            } else {
                $html .= '<li><a href="' . $baseUrl . '/' . $i . '">' . $i . '</a></li>';
            }
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= '<li class="disabled"><span>...</span></li>';
            }
            $html .= '<li><a href="' . $baseUrl . '/' . $totalPages . '">' . $totalPages . '</a></li>';
        }

        // Next
        if ($currentPage < $totalPages) {
            $html .= '<li><a href="' . $baseUrl . '/' . ($currentPage + 1) . '">&raquo;</a></li>';
        } else {
            $html .= '<li class="disabled"><span>&raquo;</span></li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
