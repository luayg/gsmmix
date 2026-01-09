<?php

namespace App\Http\Controllers\Admin\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class UserShowJsonController extends Controller
{
    public function __invoke(User $user)
    {
        $user->loadMissing(['group:id,name']);
        if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
            $user->loadMissing('roles:id,name');
        }

        return response()->json([
            'id'       => $user->id,
            'name'     => (string) $user->name,
            'email'    => (string) $user->email,
            'username' => (string) $user->username,
            'group'    => optional($user->group)->name ?: '-',
            'roles'    => $user->roles?->pluck('name')->values() ?? [],
            'status'   => $user->status,
            'created'  => optional($user->created_at)->format('Y-m-d H:i'),
        ]);
    }

    // modal view
    public function view(User $user)
    {
        $user->loadMissing('group');
        if (Schema::hasTable('roles')) $user->loadMissing('roles');
        return view('admin.users.modals.view', compact('user'));
    }
}
