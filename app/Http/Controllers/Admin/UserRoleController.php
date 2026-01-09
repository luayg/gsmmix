<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    public function edit(User $user)
    {
        $roles    = Role::orderBy('name')->get();
        $userRoleIds = $user->roles->pluck('id')->toArray();
        return view('admin.users.manage-roles', compact('user','roles','userRoleIds'));
    }

    public function sync(Request $r, User $user)
    {
        $user->syncRoles($r->input('roles', []));
        return redirect()->route('admin.users.index')->with('ok','User roles updated');
    }
}
