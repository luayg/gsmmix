<?php

namespace App\Http\Controllers\Admin\Users\Modals;

use App\Http\Controllers\Controller;
use App\Models\User;

class UserFinancesModalController extends Controller
{
    public function view(User $user)
    {
        return view('admin.users.modals.finances', compact('user'));
    }
}
