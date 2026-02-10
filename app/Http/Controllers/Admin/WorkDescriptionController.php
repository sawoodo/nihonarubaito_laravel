<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkDescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkDescriptionController extends Controller
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

        if ($request->ajax() || $request->wantsJson()) {
            return $this->list();
        }

        return view('admin.work-descriptions.index', [
            'activeSideMenu' => 'work_descriptions',
        ]);
    }

    private function list()
    {
        $descriptions = DB::table('work_descriptions')
            ->join('work', 'work_descriptions.work_id', '=', 'work.id')
            ->select(
                'work_descriptions.id',
                'work.english as work_name',
                'work_descriptions.chinese',
                'work_descriptions.english',
                'work_descriptions.japanese',
                'work_descriptions.korean',
                'work_descriptions.vietnamese'
            )
            ->orderByDesc('work_descriptions.id')
            ->get();

        $data = [];
        foreach ($descriptions as $desc) {
            $editUrl = url("admin/work-descriptions/{$desc->id}/edit");
            $data[] = [
                $desc->work_name,
                $desc->chinese,
                $desc->english,
                $desc->japanese,
                $desc->korean,
                $desc->vietnamese,
                ["<a href=\"{$editUrl}\" class=\"btn btn-xs tw-btn-purple tip\" title=\"Edit\"><i class=\"fa fa-pencil-square-o\"></i></a>"],
            ];
        }

        return response()->json([
            'recordsTotal' => count($data),
            'data'         => $data,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin();

        if ($request->isMethod('post')) {
            $request->validate([
                'work_id' => 'required|integer|exists:work,id|unique:work_descriptions,work_id',
                'english' => 'required|string',
            ], [
                'work_id.unique' => 'A description for this work already exists.',
            ]);

            WorkDescription::create([
                'work_id'    => $request->input('work_id'),
                'chinese'    => $request->input('chinese', ''),
                'english'    => $request->input('english'),
                'japanese'   => $request->input('japanese', ''),
                'korean'     => $request->input('korean', ''),
                'vietnamese' => $request->input('vietnamese', ''),
            ]);

            return redirect()->route('admin.work-descriptions.index')
                ->with('success', 'Work description has been created successfully.');
        }

        $works = Work::orderBy('english')->pluck('english', 'id')->toArray();

        return view('admin.work-descriptions.create', [
            'activeSideMenu' => 'work_descriptions',
            'works'          => $works,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $this->authorizeAdmin();

        $description = WorkDescription::with('work')->find($id);

        if (!$description) {
            abort(404);
        }

        if ($request->isMethod('post')) {
            $request->validate([
                'work_id' => 'required|integer|exists:work,id|unique:work_descriptions,work_id,' . $id,
                'english' => 'required|string',
            ], [
                'work_id.unique' => 'A description for this work already exists.',
            ]);

            $description->update([
                'work_id'    => $request->input('work_id'),
                'chinese'    => $request->input('chinese', ''),
                'english'    => $request->input('english'),
                'japanese'   => $request->input('japanese', ''),
                'korean'     => $request->input('korean', ''),
                'vietnamese' => $request->input('vietnamese', ''),
            ]);

            return redirect()->route('admin.work-descriptions.index')
                ->with('success', 'Work description has been updated successfully.');
        }

        $works = Work::orderBy('english')->pluck('english', 'id')->toArray();

        return view('admin.work-descriptions.edit', [
            'activeSideMenu' => 'work_descriptions',
            'description'    => $description,
            'works'          => $works,
        ]);
    }
}
