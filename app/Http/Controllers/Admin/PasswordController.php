<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class PasswordController extends Controller
{
    public function edit(Request $request)
    {
        $memberId = session('member_id');

        if ($request->isMethod('post')) {
            $request->validate([
                'currentPass'  => 'required',
                'newPass'      => 'required|min:6',
                'newPassAgain' => 'required|min:6|same:newPass',
            ]);

            $user = User::find($memberId);

            // Verify current password (CI3 SHA256 + encryption_key)
            $currentHash = hash('sha256', $request->input('currentPass') . 'WdNEmNQES6IXmVcKCI1QoypA4sOUHpWC');

            if ($user->password !== $currentHash) {
                // Also try bcrypt for Laravel-created passwords
                if (!password_verify($request->input('currentPass'), $user->password)) {
                    return redirect()->route('admin.change-password')
                        ->with('error', 'Invalid current password.');
                }
            }

            // Hash new password with SHA256 (CI3 compatible)
            $newHash = hash('sha256', $request->input('newPassAgain') . 'WdNEmNQES6IXmVcKCI1QoypA4sOUHpWC');

            $user->update(['password' => $newHash]);

            return redirect()->route('admin.change-password')
                ->with('success', 'Password has been updated.');
        }

        return view('admin.change-password', [
            'activeSideMenu' => 'change_password',
        ]);
    }
}
