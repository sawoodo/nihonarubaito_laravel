<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
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

        $memberId = session('member_id');

        $users = User::select(['users.*', 'r.name as role', 'l.english as language'])
            ->join('roles as r', 'users.role_id', '=', 'r.id')
            ->leftJoin('languages as l', 'users.lang_id', '=', 'l.id')
            ->where('users.id', '!=', $memberId)
            ->whereIn('users.role_id', [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_ADVERTISER])
            ->orderBy('users.id')
            ->get();

        return view('admin.users.index', [
            'activeSideMenu' => 'users',
            'users'          => $users,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin();

        $roles = $this->getRolesDropdown();
        $langList = $this->getLanguagesDropdown();

        if ($request->isMethod('post')) {
            $request->validate([
                'first_name' => 'required',
                'last_name'  => 'required',
                'email'      => 'required|email|unique:users,email',
                'password'   => 'required|min:6',
                'role_id'    => 'required|not_in:0',
                'lang_id'    => 'required|not_in:0',
            ], [
                'email.unique'    => 'This Email already exists.',
                'role_id.not_in'  => 'Please select Role.',
                'lang_id.not_in'  => 'Please select Language.',
            ]);

            // Hash with SHA256 + encryption_key (CI3 compatible)
            $hashed = hash('sha256', $request->input('password') . 'WdNEmNQES6IXmVcKCI1QoypA4sOUHpWC');

            User::create([
                'first_name' => $request->input('first_name'),
                'last_name'  => $request->input('last_name'),
                'email'      => $request->input('email'),
                'password'   => $hashed,
                'role_id'    => $request->input('role_id'),
                'lang_id'    => $request->input('lang_id'),
            ]);

            return redirect()->route('admin.users.index')
                ->with('success', 'User has been created successfully');
        }

        return view('admin.users.create', [
            'activeSideMenu' => 'users',
            'roles'          => $roles,
            'langList'       => $langList,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $this->authorizeAdmin();

        $user = User::find($id);

        if (!$user) {
            return view('admin.users.edit', [
                'activeSideMenu' => 'users',
                'user'           => null,
                'roles'          => [],
                'langList'       => [],
            ]);
        }

        if ($request->isMethod('post')) {
            $rules = [
                'first_name' => 'required',
                'last_name'  => 'required',
                'email'      => 'required|email|unique:users,email,' . $id,
                'role_id'    => 'required|not_in:0',
            ];

            $messages = [
                'email.unique'   => 'This Email already exists. Please provide another Email.',
                'role_id.not_in' => 'Please select Role.',
            ];

            // lang_id required unless advertiser
            if ($request->input('role_id') != User::ROLE_ADVERTISER) {
                $rules['lang_id'] = 'required|not_in:0';
                $messages['lang_id.not_in'] = 'Please select Language.';
            }

            $request->validate($rules, $messages);

            $user->update([
                'first_name' => $request->input('first_name'),
                'last_name'  => $request->input('last_name'),
                'email'      => $request->input('email'),
                'role_id'    => $request->input('role_id'),
                'lang_id'    => $request->input('lang_id'),
            ]);

            return redirect()->route('admin.users.index')
                ->with('success', 'User has been updated successfully');
        }

        $roles = $this->getRolesDropdown();
        $langList = $this->getLanguagesDropdown();

        return view('admin.users.edit', [
            'activeSideMenu' => 'users',
            'user'           => $user,
            'roles'          => $roles,
            'langList'       => $langList,
        ]);
    }

    private function getRolesDropdown(): array
    {
        $roles = Role::orderBy('id')->pluck('name', 'id')->toArray();
        return [0 => 'Please select'] + $roles;
    }

    private function getLanguagesDropdown(): array
    {
        $langs = Language::orderBy('id')->pluck('english', 'id')->toArray();
        return [0 => 'Please select'] + $langs;
    }
}
