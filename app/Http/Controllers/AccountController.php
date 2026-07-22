<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        // If already logged in, redirect to profile
        if (session('loggedin')) {
            return redirect('/profile');
        }

        $authFailed = false;

        if ($request->isMethod('post')) {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required|min:6',
            ]);

            $user = User::where('email', $request->input('email'))
                ->where('role_id', User::ROLE_SUBSCRIBER)
                ->first();

            if ($user && Hash::check($request->input('password'), $user->password)) {
                session([
                    'loggedin'  => true,
                    'user'      => $user,
                    'member_id' => $user->id,
                ]);
                return redirect('/profile');
            }

            $authFailed = true;
        }

        return view('account.login', [
            'authentication_failed' => $authFailed,
            'page_title'            => 'Login | Nihon Arubaito',
            'canonical'             => 'https://nihonarubaito.com/account',
            'og_url'                => 'https://nihonarubaito.com/account',
            'active_nav'            => 'account',
        ]);
    }

    public function logout()
    {
        session()->flush();
        return redirect('/');
    }
}
