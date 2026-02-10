<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Work;
use Illuminate\Http\Request;

class WorkController extends Controller
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

        return view('admin.works.index', [
            'activeSideMenu' => 'works',
        ]);
    }

    private function list()
    {
        $works = Work::orderByDesc('id')->get();

        $data = [];
        foreach ($works as $work) {
            $editUrl = url("admin/works/{$work->id}/edit");
            $data[] = [
                $work->chinese,
                $work->english,
                $work->japanese,
                $work->korean,
                $work->vietnamese,
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
                'english' => 'required|unique:work,english',
            ], [
                'english.unique' => 'English already exists.',
            ]);

            Work::create([
                'chinese'    => $request->input('chinese', ''),
                'english'    => $request->input('english'),
                'japanese'   => $request->input('japanese', ''),
                'korean'     => $request->input('korean', ''),
                'vietnamese' => $request->input('vietnamese', ''),
            ]);

            return redirect()->route('admin.works.index')
                ->with('success', 'Work has been created successfully.');
        }

        return view('admin.works.create', [
            'activeSideMenu' => 'works',
        ]);
    }

    public function edit(Request $request, $id)
    {
        $this->authorizeAdmin();

        $work = Work::find($id);

        if (!$work) {
            abort(404);
        }

        if ($request->isMethod('post')) {
            $request->validate([
                'english' => 'required|unique:work,english,' . $id,
            ], [
                'english.unique' => 'English already exists.',
            ]);

            $work->update([
                'chinese'    => $request->input('chinese', ''),
                'english'    => $request->input('english'),
                'japanese'   => $request->input('japanese', ''),
                'korean'     => $request->input('korean', ''),
                'vietnamese' => $request->input('vietnamese', ''),
            ]);

            return redirect()->route('admin.works.index')
                ->with('success', 'Work has been updated successfully.');
        }

        return view('admin.works.edit', [
            'activeSideMenu' => 'works',
            'work'           => $work,
        ]);
    }
}
