<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

final class RegisterController extends Controller
{
    public function create() { return view('auth.register'); }
    public function store(Request $request)
    {
        $data=$request->validate([
            'name'=>'required|string|max:120','username'=>'required|string|min:3|max:60|alpha_dash|unique:users,username',
            'email'=>'required|email:rfc|max:255|unique:users,email','password'=>['required','confirmed',Password::min(10)->letters()->mixedCase()->numbers()],
        ]);
        $user=User::create(['name'=>$data['name'],'username'=>strtolower($data['username']),'email'=>strtolower($data['email']),'password'=>Hash::make($data['password']),'status'=>'active','balance'=>0,'group_id'=>Group::query()->orderBy('id')->value('id')]);
        Auth::login($user); $request->session()->regenerate();
        return redirect()->route('customer.dashboard');
    }
}
