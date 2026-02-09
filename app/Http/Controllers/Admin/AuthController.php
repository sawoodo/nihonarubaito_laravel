<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        if (session('loggedin') && session('user') && session('user')->isBackendUser()) {
            return redirect('/admin/jobs');
        }

        $authFailed = false;

        if ($request->isMethod('post')) {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required',
            ]);

            $user = User::where('email', $request->input('email'))
                ->whereIn('role_id', [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_ADVERTISER])
                ->first();

            if ($user && $this->checkPassword($request->input('password'), $user->password)) {
                session([
                    'loggedin'  => true,
                    'user'      => $user,
                    'member_id' => $user->id,
                ]);
                return redirect('/admin/jobs');
            }

            $authFailed = true;
        }

        return view('admin.login', [
            'authentication_failed' => $authFailed,
        ]);
    }

    public function logout()
    {
        session()->flush();
        return redirect('/admin/login');
    }

    private function checkPassword(string $input, string $hash): bool
    {
        // CI3 uses SHA256 with encryption_key
        $ci3Hash = hash('sha256', $input . 'WdNEmNQES6IXmVcKCI1QoypA4sOUHpWC');
        if ($ci3Hash === $hash) {
            return true;
        }

        // Also support bcrypt for new users created via Laravel
        if (password_verify($input, $hash)) {
            return true;
        }

        return false;
    }
}
