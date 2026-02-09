<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    private function authorizeAdmin()
    {
        if (session('user')->role_id !== User::ROLE_ADMIN) {
            abort(403, 'You are not authorized.');
        }
    }

    public function index()
    {
        $this->authorizeAdmin();

        $categories = Category::orderByDesc('id')->get();

        return view('admin.categories.index', [
            'activeSideMenu' => 'categories',
            'categories'     => $categories,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin();

        if ($request->isMethod('post')) {
            $request->validate([
                'english'    => 'required|unique:categories,english',
                'japanese'   => 'required|unique:categories,japanese',
                'vietnamese' => 'required|unique:categories,vietnamese',
            ], [
                'english.unique'    => 'English already exists.',
                'japanese.unique'   => 'Japanese already exists.',
                'vietnamese.unique' => 'Vietnamese already exists.',
            ]);

            Category::create([
                'chinese'    => $request->input('chinese', ''),
                'english'    => $request->input('english'),
                'japanese'   => $request->input('japanese'),
                'korean'     => $request->input('korean', ''),
                'vietnamese' => $request->input('vietnamese'),
            ]);

            return redirect()->route('admin.categories.index')
                ->with('success', 'Category has been created successfully.');
        }

        return view('admin.categories.create', [
            'activeSideMenu' => 'categories',
        ]);
    }

    public function edit(Request $request, $id)
    {
        $this->authorizeAdmin();

        $category = Category::find($id);

        if (!$category) {
            abort(404);
        }

        if ($request->isMethod('post')) {
            $request->validate([
                'english'    => 'required|unique:categories,english,' . $id,
                'japanese'   => 'required|unique:categories,japanese,' . $id,
                'vietnamese' => 'required|unique:categories,vietnamese,' . $id,
            ], [
                'english.unique'    => 'English already exists.',
                'japanese.unique'   => 'Japanese already exists.',
                'vietnamese.unique' => 'Vietnamese already exists.',
            ]);

            $category->update([
                'chinese'    => $request->input('chinese', ''),
                'english'    => $request->input('english'),
                'japanese'   => $request->input('japanese'),
                'korean'     => $request->input('korean', ''),
                'vietnamese' => $request->input('vietnamese'),
            ]);

            return redirect()->route('admin.categories.index')
                ->with('success', 'Category has been updated successfully.');
        }

        return view('admin.categories.edit', [
            'activeSideMenu' => 'categories',
            'category'       => $category,
        ]);
    }
}
