<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Services\Settings\AppSettings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Models\FinanceAccount;

final class RegisterController extends Controller
{
    public function create(AppSettings $settings) { abort_unless((bool)$settings->get('general.registration_enabled',false),404); return view('auth.register'); }
    public function store(Request $request, AppSettings $settings)
    {
        abort_unless((bool)$settings->get('general.registration_enabled',false),404);
        $data=$request->validate([
            'name'=>'required|string|max:120','username'=>'required|string|min:3|max:60|alpha_dash|unique:users,username',
            'email'=>'required|email:rfc|max:255|unique:users,email','password'=>['required','confirmed',Password::min(10)->letters()->mixedCase()->numbers()],
        ]);
        $activation=(string)$settings->get('general.registration_activation','automatic');
        $user=User::create(['name'=>$data['name'],'username'=>strtolower($data['username']),'email'=>strtolower($data['email']),'password'=>Hash::make($data['password']),'status'=>$activation==='automatic'?'active':'inactive','balance'=>0,'group_id'=>$settings->get('general.default_group_id')?:Group::query()->orderBy('id')->value('id')]);
        FinanceAccount::query()->firstOrCreate(['user_id'=>$user->id],['locked_amount'=>0,'total_receipts'=>0,'paid_credits'=>0,'overdraft_limit'=>$settings->get('general.default_overdraft','0')]);
        if($activation==='automatic'){ Auth::login($user); $request->session()->regenerate(); return redirect()->route('customer.dashboard'); }
        if($activation==='email'){
            $url=URL::temporarySignedRoute('register.verify',now()->addHours(24),['user'=>$user->id]);
            Mail::raw("Verify your account by opening this link within 24 hours:\n\n{$url}",fn($message)=>$message->to($user->email)->subject('Verify your account'));
        }
        return redirect()->route('login')->with('ok',$activation==='admin'?'Your account is awaiting administrator approval.':'Check your email and open the verification link to activate your account.');
    }

    public function verify(Request $request, User $user)
    {
        abort_unless($request->hasValidSignature(),403);
        $user->forceFill(['status'=>'active','email_verified_at'=>now()])->save();
        return redirect()->route('login')->with('ok','Your email is verified. You can now sign in.');
    }
}
