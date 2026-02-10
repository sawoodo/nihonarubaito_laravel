<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AreaController extends Controller
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

        return view('admin.areas.index', [
            'activeSideMenu' => 'areas',
        ]);
    }

    private function list()
    {
        $areas = DB::table('areas')
            ->join('towns', 'areas.town_id', '=', 'towns.id')
            ->join('prefectures', 'towns.prefecture_id', '=', 'prefectures.id')
            ->select(
                'areas.id',
                'prefectures.english as prefecture_name',
                'areas.chinese',
                'areas.english',
                'areas.japanese',
                'areas.korean',
                'areas.vietnamese'
            )
            ->orderByDesc('areas.id')
            ->get();

        $data = [];
        foreach ($areas as $area) {
            $editUrl = url("admin/areas/{$area->id}/edit");
            $data[] = [
                (string) $area->id,
                $area->prefecture_name,
                $area->chinese,
                $area->english,
                $area->japanese,
                $area->korean,
                $area->vietnamese,
                ["<a href=\"{$editUrl}\" class=\"btn btn-xs tw-btn-purple tip\" title=\"Edit\"><i class=\"fa fa-pencil-square-o\"></i></a>"],
            ];
        }

        return response()->json([
            'recordsTotal' => count($data),
            'data'         => $data,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $this->authorizeAdmin();

        $area = Area::with('town.prefecture')->find($id);

        if (!$area) {
            abort(404);
        }

        if ($request->isMethod('post')) {
            $request->validate([
                'english'    => 'required|string',
                'japanese'   => 'required|string',
                'vietnamese' => 'required|string',
            ]);

            $area->update([
                'chinese'    => $request->input('chinese', ''),
                'english'    => $request->input('english'),
                'japanese'   => $request->input('japanese'),
                'korean'     => $request->input('korean', ''),
                'vietnamese' => $request->input('vietnamese'),
            ]);

            return redirect()->route('admin.areas.index')
                ->with('success', 'Area has been updated successfully.');
        }

        $prefectureName = $area->town && $area->town->prefecture
            ? $area->town->prefecture->english
            : 'N/A';

        return view('admin.areas.edit', [
            'activeSideMenu'  => 'areas',
            'area'            => $area,
            'prefecture_name' => $prefectureName,
        ]);
    }
}
