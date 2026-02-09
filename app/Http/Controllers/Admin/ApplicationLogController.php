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

        $query = DB::table('application_logs as log')
            ->select([
                'log.*',
                'j.title',
                'c.english as category',
                DB::raw("CONCAT(cu.first_name, ' ', cu.last_name) as created_by_name"),
                DB::raw("CONCAT(uu.first_name, ' ', uu.last_name) as updated_by_name"),
                DB::raw('ROW_NUMBER() OVER (PARTITION BY log.job_no ORDER BY order_date) as apply_count'),
            ])
            ->leftJoin('jobs as j', 'log.job_no', '=', 'j.job_no')
            ->leftJoin('categories as c', 'j.job_category_id', '=', 'c.id')
            ->leftJoin('users as cu', 'j.created_by', '=', 'cu.id')
            ->leftJoin('users as uu', 'j.updated_by', '=', 'uu.id');

        if ($from) {
            $fromDate = \Carbon\Carbon::createFromFormat('d/m/Y', $from)->format('Y-m-d');
            $query->where('log.click_date', '>=', $fromDate);
        }
        if ($to) {
            $toDate = \Carbon\Carbon::createFromFormat('d/m/Y', $to)->format('Y-m-d');
            $query->where('log.click_date', '<=', $toDate);
        }

        $logs = $query->orderByDesc('log.id')->get();

        $data = [];
        foreach ($logs as $log) {
            $applyCountStyle = '';
            if ($log->apply_count == 1) {
                $applyCountStyle = 'color: green;';
            } elseif ($log->apply_count == 2) {
                $applyCountStyle = 'color: orange;';
            } elseif ($log->apply_count >= 3) {
                $applyCountStyle = 'color: red;';
            }

            $data[] = [
                $log->job_no,
                $log->merchant_name ?? '',
                $log->click_date ?? '',
                $log->order_date ?? '',
                $log->title ?? '',
                $log->category ?? '',
                '<span style="' . $applyCountStyle . '">' . $log->apply_count . '</span>',
                $log->created_by_name ?? '',
                $log->updated_by_name ?? '',
                $log->apply_link ?? '',
            ];
        }

        return response()->json(['data' => $data]);
    }
}
