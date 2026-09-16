<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FbPost;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FbScheduledPostsV2Controller extends Controller
{
    private const PER_PAGE = 25;

    // Territory routing: prefecture_id → tab
    private const TOKYO_IDS = [1];
    private const KANTO_IDS = [2, 5, 6, 11, 18, 19];
    private const OSAKA_IDS = [3, 7, 13, 28, 30, 39];

    private function authorizeAdmin()
    {
        if (session('user')->role_id !== User::ROLE_ADMIN) {
            abort(403, 'You are not authorized.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $tab = $request->input('tab', 'tokyo');
        $page = max(1, (int) $request->input('page', 1));

        // Base query: unpublished posts with prefecture name (LEFT JOIN to catch NULLs)
        $query = FbPost::select('fb_posts.*', 'p.english as prefecture_name')
            ->leftJoin('prefectures as p', 'fb_posts.prefecture_id', '=', 'p.id')
            ->where('fb_posts.published', 0);

        // Apply territory filter based on tab
        match ($tab) {
            'kanto' => $query->whereIn('fb_posts.prefecture_id', self::KANTO_IDS),
            'osaka' => $query->whereIn('fb_posts.prefecture_id', self::OSAKA_IDS),
            'other' => $query->where(function ($q) {
                $mapped = array_merge(self::TOKYO_IDS, self::KANTO_IDS, self::OSAKA_IDS);
                $q->whereNotIn('fb_posts.prefecture_id', $mapped)
                  ->orWhereNull('fb_posts.prefecture_id');
            }),
            default => $query->whereIn('fb_posts.prefecture_id', self::TOKYO_IDS),
        };

        $query->orderByDesc('fb_posts.id');

        // Paginate
        $offset = ($page - 1) * self::PER_PAGE;
        $totalRows = (clone $query)->count();
        $posts = $query->skip($offset)->take(self::PER_PAGE)->get();

        // Get counts for all tabs
        $counts = [
            'tokyo' => $this->getTabCount('tokyo'),
            'kanto' => $this->getTabCount('kanto'),
            'osaka' => $this->getTabCount('osaka'),
            'other' => $this->getTabCount('other'),
        ];

        return view('admin.fb-scheduled-posts-v2.index', [
            'activeSideMenu' => 'fb_scheduled_posts_v2',
            'posts' => $posts,
            'tab' => $tab,
            'counts' => $counts,
            'currentPage' => $page,
            'totalPages' => (int) ceil($totalRows / self::PER_PAGE),
            'totalRows' => $totalRows,
        ]);
    }

    private function getTabCount(string $tab): int
    {
        $query = FbPost::where('published', 0);

        match ($tab) {
            'kanto' => $query->whereIn('prefecture_id', self::KANTO_IDS),
            'osaka' => $query->whereIn('prefecture_id', self::OSAKA_IDS),
            'other' => $query->where(function ($q) {
                $mapped = array_merge(self::TOKYO_IDS, self::KANTO_IDS, self::OSAKA_IDS);
                $q->whereNotIn('prefecture_id', $mapped)
                  ->orWhereNull('prefecture_id');
            }),
            default => $query->whereIn('prefecture_id', self::TOKYO_IDS),
        };

        return $query->count();
    }
}
