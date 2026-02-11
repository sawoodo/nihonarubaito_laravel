<?php

namespace App\View\Composers;

use App\Models\Language;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FrontendComposer
{
    public function compose(View $view): void
    {
        $langId = session('user_lang', 1);
        $langName = session('lang_name', 'english');

        // Load language content file (same format as CI3)
        $contentFile = resource_path("lang/{$langName}/content.php");
        if (!file_exists($contentFile)) {
            $contentFile = resource_path('lang/english/content.php');
        }
        $content = (object) require $contentFile;

        // Get languages with flags (excluding Chinese=2 and Korean=5 in topbar)
        $langWithFlags = Language::all();

        // Preload all areas grouped by prefecture (eliminates /areas/get AJAX call)
        $validLangs = ['english', 'japanese', 'vietnamese', 'chinese', 'korean'];
        $areaLang = in_array($langName, $validLangs) ? $langName : 'english';
        $preloadedAreas = DB::table('areas as a')
            ->join('towns as t', 'a.town_id', '=', 't.id')
            ->select('a.id', "a.{$areaLang} as name", 't.prefecture_id')
            ->orderBy('a.id')
            ->get()
            ->groupBy('prefecture_id')
            ->map(fn($group) => $group->map(fn($a) => [$a->id, $a->name])->values());

        $view->with([
            'site_name'       => config('app.site_name', 'Part-time Jobs in Japan | Japan Job Search | nihonarubaito.com'),
            'content'         => $content,
            'lang_with_flags' => $langWithFlags,
            'user_lang'       => $langId,
            'lang_selected'   => session('lang_selected', 0),
            'loggedin'        => session('loggedin', false),
            'user'            => session('user'),
            'user_lang_name'  => $langName,
            'js'              => ['mix' => ['global_js' => 'global/index.js']],
            'preloadedAreas'  => $preloadedAreas,
        ]);
    }
}
