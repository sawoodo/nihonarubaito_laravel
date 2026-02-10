<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApplicationLogController extends Controller
{
    private function authorizeAdmin()
    {
        if (session('user')->role_id !== User::ROLE_ADMIN) {
            abort(403, 'You are not authorized.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();

        // AJAX: DataTables JS POSTs to this URL
        if ($request->ajax() || $request->wantsJson()) {
            return $this->list($request);
        }

        $today = now()->format('d/m/Y');

        return view('admin.application-logs.index', [
            'activeSideMenu' => 'application_logs',
            'today'          => $today,
        ]);
    }

    public function list(Request $request)
    {
        $this->authorizeAdmin();

        $from = $request->input('from');
        $to = $request->input('to');

        $query = DB::table('jobs as j')
            ->select([
                'log.*',
                'j.title',
                'j.apply_link',
                'jc.english as category',
                DB::raw("CONCAT(created.first_name, ' ', created.last_name) as created_by_name"),
                DB::raw("CONCAT(updated.first_name, ' ', updated.last_name) as updated_by_name"),
                DB::raw('ROW_NUMBER() OVER (PARTITION BY log.job_no ORDER BY order_date) as apply_count'),
            ])
            ->join('categories as jc', 'j.job_category_id', '=', 'jc.id')
            ->join('application_logs as log', 'j.job_no', '=', 'log.job_no')
            ->join('users as created', 'j.user_id', '=', 'created.id')
            ->leftJoin('users as updated', 'j.updated_by', '=', 'updated.id');

        if ($from) {
            $fromDate = \Carbon\Carbon::createFromFormat('d/m/Y', $from)->format('Y-m-d');
            $query->whereRaw('DATE(order_date) >= ?', [$fromDate]);
        }
        if ($to) {
            $toDate = \Carbon\Carbon::createFromFormat('d/m/Y', $to)->format('Y-m-d');
            $query->whereRaw('DATE(order_date) <= ?', [$toDate]);
        }

        $logs = $query->orderByDesc('log.order_date')->get();

        $data = [];
        foreach ($logs as $log) {
            $link = url("admin/jobs/{$log->job_no}/view");
            $applyCount = (int) $log->apply_count;

            $class = 'tw-px-4 tw-py-1 tw-rounded-full tw-shadow-lg ';
            if ($applyCount === 1) {
                $class .= 'tw-bg-emerald-500';
            } elseif ($applyCount === 2) {
                $class .= 'tw-bg-amber-400';
            } else {
                $class .= 'tw-bg-red-500';
            }

            $data[] = [
                "<a href=\"{$link}\" class=\"btn btn-xs tw-btn-purple tip\" title=\"View\" target=\"blank\">{$log->job_no}</a>",
                $log->merchant_name ?? '',
                $log->click_date ? date('d-m-Y H:i:s', strtotime($log->click_date)) : '',
                $log->order_date ? date('d-m-Y H:i:s', strtotime($log->order_date)) : '',
                $log->title ?? '',
                $log->category ?? '',
                "<span class=\"{$class}\">{$log->apply_count}</span>",
                $log->created_by_name ?? '',
                $log->updated_by_name ?? '',
                $log->apply_link ?? '',
            ];
        }

        return response()->json([
            'recordsTotal' => count($logs),
            'data' => $data,
        ]);
    }
}
