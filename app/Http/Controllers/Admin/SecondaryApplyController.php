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

        $from = $request->input('from', now()->startOfMonth()->format('d/m/Y'));
        $to = $request->input('to', now()->format('d/m/Y'));

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
            ->select([
                'sa.id', 'sa.job_no', 'sa.first_name', 'sa.last_name',
                'sa.email', 'sa.phone', 'sa.created_at as apply_date',
                'j.title',
            ])
            ->leftJoin('jobs as j', 'sa.job_no', '=', 'j.job_no')
            ->orderByDesc('sa.id');

        if ($from) {
            $fromDate = \Carbon\Carbon::createFromFormat('d/m/Y', $from)->format('Y-m-d');
            $query->where('sa.created_at', '>=', $fromDate);
        }
        if ($to) {
            $toDate = \Carbon\Carbon::createFromFormat('d/m/Y', $to)->format('Y-m-d');
            $query->where('sa.created_at', '<=', $toDate . ' 23:59:59');
        }

        $applies = $query->get();

        $data = [];
        foreach ($applies as $apply) {
            $data[] = [
                '<a href="' . url("admin/jobs/{$apply->job_no}/view") . '">' . $apply->job_no . '</a>',
                $apply->first_name ?? '',
                $apply->last_name ?? '',
                $apply->email ?? '',
                $apply->phone ?? '',
                $apply->apply_date ? \Carbon\Carbon::parse($apply->apply_date)->format('d-m-Y') : '',
            ];
        }

        return response()->json(['data' => $data]);
    }
}
