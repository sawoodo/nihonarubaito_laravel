<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecondaryApply;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecondaryApplyController extends Controller
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

        $from = now()->startOfMonth()->format('d/m/Y');
        $to = now()->format('d/m/Y');

        return view('admin.secondary-applies.index', [
            'activeSideMenu' => 'secondary_applies',
            'from'           => $from,
            'to'             => $to,
        ]);
    }

    public function list(Request $request)
    {
        $this->authorizeAdmin();

        $from = $request->input('from');
        $to = $request->input('to');

        $query = DB::table('secondary_applies as sa')
            ->select(['sa.*', 'j.title'])
            ->join('jobs as j', 'j.job_no', '=', 'sa.job_no')
            ->orderByDesc('sa.id');

        if ($from && $to) {
            $fromDate = \Carbon\Carbon::createFromFormat('d/m/Y', $from)->format('Y-m-d');
            $toDate = \Carbon\Carbon::createFromFormat('d/m/Y', $to)->format('Y-m-d');
            $query->whereRaw("DATE(apply_date) BETWEEN ? AND ?", [$fromDate, $toDate]);
        }

        $applies = $query->get();

        $data = [];
        foreach ($applies as $apply) {
            $link = url("admin/jobs/{$apply->job_no}/view");

            $data[] = [
                "<a href=\"{$link}\" class=\"btn btn-xs tw-btn-purple tip\" title=\"View\" target=\"blank\">{$apply->job_no}</a>",
                $apply->first_name ?? '',
                $apply->last_name ?? '',
                $apply->email ?? '',
                $apply->phone ?? '',
                $apply->apply_date ? date('d-m-Y', strtotime($apply->apply_date)) : '',
            ];
        }

        return response()->json([
            'recordsTotal' => count($applies),
            'data' => $data,
        ]);
    }
}
