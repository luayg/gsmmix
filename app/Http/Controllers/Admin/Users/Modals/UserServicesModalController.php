<?php

namespace App\Http\Controllers\Admin\Users\Modals;

use App\Http\Controllers\Controller;
use App\Models\User;

class UserServicesModalController extends Controller
{
    public function view(User $user)
    {
        return view('admin.users.modals.services', compact('user'));
    }
}
