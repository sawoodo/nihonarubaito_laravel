<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FbPost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FbScheduledPostController extends Controller
{
    private const PER_PAGE = 20;

    private function authorizeAdmin()
    {
        if (session('user')->role_id !== User::ROLE_ADMIN) {
            abort(403, 'You are not authorized.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $q = $request->input('q', '');
        $createdAt = $request->input('created_at', '');
        $scheduledAt = $request->input('scheduled_at', '');
        $prefectureId = $request->input('prefecture_id', '');

        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $query = FbPost::select('fb_posts.*', 'l.english as language', 'p.english as prefecture_name')
            ->leftJoin('languages as l', 'fb_posts.lang_id', '=', 'l.id')
            ->leftJoin('prefectures as p', 'fb_posts.prefecture_id', '=', 'p.id');

        // Prefecture filter
        if ($prefectureId) {
            $query->where('fb_posts.prefecture_id', $prefectureId);
        }

        // Content search (3-char minimum enforced client-side, guarded server-side)
        if ($q && mb_strlen(trim($q)) >= 3) {
            $query->where('fb_posts.content', 'like', "%{$q}%");
        }

        if ($createdAt) {
            $query->whereDate('fb_posts.created_at', $this->parseDatepickerDate($createdAt));
        }
        if ($scheduledAt) {
            $query->whereDate('fb_posts.scheduled_at', $this->parseDatepickerDate($scheduledAt));
        }

        $totalRows = (clone $query)->count();

        $posts = $query->orderByDesc('fb_posts.id')
            ->skip($offset)
            ->take(self::PER_PAGE)
            ->get();

        $pagination = $this->buildPagination(
            url('admin/fb-scheduled-posts'),
            $totalRows,
            self::PER_PAGE,
            $page,
            $request->only(['q', 'created_at', 'scheduled_at', 'prefecture_id'])
        );

        // Get all prefectures for dropdown
        $prefectures = \App\Models\Prefecture::orderBy('english')->get();

        return view('admin.fb-scheduled-posts.index', [
            'activeSideMenu' => 'fb_scheduled_posts',
            'fb_posts'       => $posts,
            'pagination'     => $pagination,
            'prefectures'    => $prefectures,
            'q'              => $q,
            'created_at'     => $createdAt,
            'scheduled_at'   => $scheduledAt,
            'prefecture_id'  => $prefectureId,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $this->authorizeAdmin();

        $post = FbPost::find($id);

        if (!$post) {
            return redirect()->route('admin.fb-scheduled-posts.index')
                ->with('error', 'Scheduled post not found.');
        }

        if ($request->isMethod('post')) {
            $request->validate([
                'scheduled_at' => 'required',
            ]);

            $post->update([
                'scheduled_at' => $request->input('scheduled_at'),
            ]);

            return redirect()->route('admin.fb-scheduled-posts.index')
                ->with('success', 'Scheduled post has been updated.');
        }

        return view('admin.fb-scheduled-posts.edit', [
            'activeSideMenu' => 'fb_scheduled_posts',
            'fb_post'        => $post,
        ]);
    }

    public function postOnFb($id)
    {
        $this->authorizeAdmin();

        $post = FbPost::find($id);

        if (!$post) {
            return redirect()->route('admin.fb-scheduled-posts.index')
                ->with('error', 'Scheduled post not found.');
        }

        if ($post->published) {
            return redirect()->route('admin.fb-scheduled-posts.index')
                ->with('error', 'This post has already been published.');
        }

        // Mark as published (FB posting code is stubbed out in CI3 too)
        $post->update([
            'published' => true,
            'run_at'    => now(),
            'run_type'  => 'Manually',
        ]);

        return redirect()->route('admin.fb-scheduled-posts.index')
            ->with('success', 'Post has been marked as published.');
    }

    private function buildPagination(string $baseUrl, int $totalRows, int $perPage, int $currentPage, array $queryParams = []): string
    {
        $totalPages = (int) ceil($totalRows / $perPage);
        if ($totalPages <= 1) {
            return '';
        }

        $qs = http_build_query(array_filter($queryParams, fn($v) => $v !== ''));

        $html = '<ul class="pagination pagination-sm">';

        if ($currentPage > 1) {
            $html .= '<li><a href="' . $baseUrl . '?page=1' . ($qs ? "&{$qs}" : '') . '"><span class="glyphicon glyphicon-step-backward"></span></a></li>';
        }

        $start = max(1, $currentPage - 3);
        $end = min($totalPages, $currentPage + 3);

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $currentPage) {
                $html .= '<li class="active"><span>' . $i . '</span></li>';
            } else {
                $html .= '<li><a href="' . $baseUrl . '?page=' . $i . ($qs ? "&{$qs}" : '') . '">' . $i . '</a></li>';
            }
        }

        if ($currentPage < $totalPages) {
            $html .= '<li><a href="' . $baseUrl . '?page=' . $totalPages . ($qs ? "&{$qs}" : '') . '"><span class="glyphicon glyphicon-step-forward"></span></a></li>';
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Convert datepicker dd/mm/yyyy format to Y-m-d for MySQL.
     * Matches CI3's mysql_date() helper.
     */
    private function parseDatepickerDate(string $date): ?string
    {
        $date = str_replace('/', '-', $date);
        $parsed = strtotime($date);

        return $parsed ? date('Y-m-d', $parsed) : null;
    }
}
