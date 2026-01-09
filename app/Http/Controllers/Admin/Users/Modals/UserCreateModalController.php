<?php

namespace App\Http\Controllers\Admin\Users\Modals;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class UserCreateModalController extends Controller
{
    public function view()
    {
        return view('admin.users.modals.create');
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name'     => ['required','string','max:255'],
            'email'    => ['required','email','max:255','unique:users,email'],
            'username' => ['nullable','string','max:255','unique:users,username'],
            'group_id' => ['nullable','integer','exists:groups,id'],
            'status'   => ['required','in:active,inactive'],
            'roles'    => ['array'],
            'roles.*'  => ['string'],
        ]);

        $user = new User();
        $user->name     = $data['name'];
        $user->email    = $data['email'];
        $user->username = $data['username'] ?? null;
        $user->group_id = $data['group_id'] ?? null;
        $user->status   = $data['status'];
        $user->password = bcrypt('password');
        $user->save();

        if (method_exists($user, 'syncRoles') && !empty($data['roles']) && Schema::hasTable('roles')) {
            $user->syncRoles($data['roles']);
        }

        return response()->json(['ok'=>true,'msg'=>'User created']);
    }
}
