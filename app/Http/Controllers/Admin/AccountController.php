<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.account.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:60', Rule::unique('users')->ignore($user)],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users')->ignore($user)],
        ]);
        $user->update($data);

        return back()->with('ok', 'admin.account.updated');
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();
        $currentRules = $user->google_id ? ['nullable', 'string'] : ['required', 'current_password:web'];
        $data = $request->validate([
            'current_password' => $currentRules,
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        $request->session()->regenerate();

        return back()->with('ok', 'admin.password.updated');
    }
}
