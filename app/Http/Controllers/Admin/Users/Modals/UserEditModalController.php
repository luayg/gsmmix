<?php

namespace App\Http\Controllers\Admin\Users\Modals;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class UserEditModalController extends Controller
{
    public function view(User $user)
    {
        $user->loadMissing(['group','roles']);
        return view('admin.users.modals.edit', compact('user'));
    }

    public function update(Request $r, User $user)
    {
        $data = $r->validate([
            'name'     => ['required','string','max:255'],
            'email'    => ['required','email','max:255',"unique:users,email,{$user->id}"],
            'username' => ['nullable','string','max:255',"unique:users,username,{$user->id}"],
            'group_id' => ['nullable','integer','exists:groups,id'],
            'status'   => ['required','in:active,inactive'],
            'roles'    => ['array'],
            'roles.*'  => ['string'],
        ]);

        $user->fill([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'username' => $data['username'] ?? null,
            'group_id' => $data['group_id'] ?? null,
            'status'   => $data['status'],
        ])->save();

        if (method_exists($user, 'syncRoles') && Schema::hasTable('roles')) {
            $user->syncRoles($data['roles'] ?? []);
        }

        return response()->json(['ok'=>true,'msg'=>'User updated']);
    }
}
