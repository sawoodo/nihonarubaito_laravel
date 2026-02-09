<?php

namespace App\Http\Controllers;

use App\Models\Language;

class LanguageController extends Controller
{
    public function index(int $langId = 1)
    {
        $lang = Language::find($langId);
        $langName = $lang ? strtolower($lang->english) : 'english';

        session([
            'user_lang'     => $langId,
            'lang_name'     => $langName,
            'lang_selected' => true,
        ]);

        // Force https:// in redirect URL
        $url = str_replace('http://', 'https://', url('/'));

        return redirect($url, 301);
    }
}
