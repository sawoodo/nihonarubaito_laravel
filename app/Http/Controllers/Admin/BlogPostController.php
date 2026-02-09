<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Language;
use App\Models\User;
use Illuminate\Http\Request;

class BlogPostController extends Controller
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

        return view('admin.blog-posts.index', [
            'activeSideMenu' => 'blog_posts',
        ]);
    }

    public function list(Request $request)
    {
        $this->authorizeAdmin();

        $posts = BlogPost::select([
                'blog_posts.*',
                'l.english as language_name',
                \DB::raw("CONCAT(cu.first_name, ' ', cu.last_name) as created_by_name"),
                \DB::raw("CONCAT(uu.first_name, ' ', uu.last_name) as updated_by_name"),
            ])
            ->leftJoin('languages as l', 'blog_posts.lang_id', '=', 'l.id')
            ->leftJoin('users as cu', 'blog_posts.created_by', '=', 'cu.id')
            ->leftJoin('users as uu', 'blog_posts.updated_by', '=', 'uu.id')
            ->orderByDesc('blog_posts.id')
            ->get();

        $data = [];
        foreach ($posts as $post) {
            $data[] = [
                $post->language_name ?? '',
                $post->slug,
                $post->title,
                \Illuminate\Support\Str::limit(strip_tags($post->post), 80),
                $post->created_by_name ?? '',
                $post->updated_by_name ?? '',
                '<a href="' . route('admin.blog-posts.edit', $post->id) . '" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-edit"></span></a>',
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin();

        $langList = Language::orderBy('id')->pluck('english', 'id')->toArray();
        $langList = [0 => 'Please select'] + $langList;

        if ($request->isMethod('post')) {
            $request->validate([
                'lang_id' => 'required|not_in:0',
                'slug'    => 'required|unique:blog_posts,slug',
                'title'   => 'required',
                'post'    => 'required',
            ], [
                'lang_id.not_in' => 'Please select Language.',
                'slug.unique'    => 'This slug already exists.',
            ]);

            $memberId = session('member_id');

            BlogPost::create([
                'lang_id'    => $request->input('lang_id'),
                'slug'       => $request->input('slug'),
                'title'      => $request->input('title'),
                'post'       => $request->input('post'),
                'created_by' => $memberId,
                'updated_by' => $memberId,
            ]);

            return redirect()->route('admin.blog-posts.index')
                ->with('success', 'Blog post has been created successfully.');
        }

        return view('admin.blog-posts.create', [
            'activeSideMenu' => 'blog_posts',
            'langList'       => $langList,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $this->authorizeAdmin();

        $post = BlogPost::find($id);

        if (!$post) {
            abort(404);
        }

        $langList = Language::orderBy('id')->pluck('english', 'id')->toArray();
        $langList = [0 => 'Please select'] + $langList;

        if ($request->isMethod('post')) {
            $request->validate([
                'lang_id' => 'required|not_in:0',
                'title'   => 'required',
                'post'    => 'required',
            ], [
                'lang_id.not_in' => 'Please select Language.',
            ]);

            $post->update([
                'lang_id'    => $request->input('lang_id'),
                'title'      => $request->input('title'),
                'post'       => $request->input('post'),
                'updated_by' => session('member_id'),
            ]);

            return redirect()->route('admin.blog-posts.index')
                ->with('success', 'Blog post has been updated successfully.');
        }

        return view('admin.blog-posts.edit', [
            'activeSideMenu' => 'blog_posts',
            'post'           => $post,
            'langList'       => $langList,
        ]);
    }
}
