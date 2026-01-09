<?php

namespace App\Http\Controllers\Admin\Users\Modals;

use App\Http\Controllers\Controller;
use App\Models\User;

class UserDeleteModalController extends Controller
{
    public function view(User $user)
    {
        return view('admin.users.modals.delete', compact('user'));
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['ok'=>true,'msg'=>'User deleted']);
    }
}
