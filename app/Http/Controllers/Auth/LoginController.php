<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Show login form
     */
    public function create()
    {
        return view('auth.login');
    }

    /**
     * Handle login
     * - supports email or username in single field: "login"
     * - checks status = active
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable'],
        ]);

        $login = trim($data['login']);
        $password = $data['password'];
        $remember = $request->boolean('remember');

        // نجرب email ثم username (حتى لو المستخدم كتب username)
        $attempts = [
            ['email' => $login, 'password' => $password, 'status' => 'active'],
            ['username' => $login, 'password' => $password, 'status' => 'active'],
        ];

        foreach ($attempts as $credentials) {
            if (Auth::attempt($credentials, $remember)) {
                $request->session()->regenerate();

                // يرجع لآخر صفحة كان يريدها (مثل API Management) أو للداشبورد
                return redirect()->intended(route('admin.dashboard'));
            }
        }

        throw ValidationException::withMessages([
            'login' => 'بيانات الدخول غير صحيحة أو الحساب غير مفعل.',
        ]);
    }

    /**
     * Logout
     */
    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
