<?php

namespace App\View\Composers;

use Illuminate\View\View;

class AdminComposer
{
    public function compose(View $view)
    {
        $user = session('user');

        $view->with([
            'site_name'  => config('app.name', 'Nihon Arubaito'),
            'role_id'    => $user->role_id ?? null,
            'admin_user' => $user,
        ]);
    }
}
