<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\FinanceAccount;
use App\Models\Group;
use App\Models\User;
use App\Services\Settings\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

final class RegisterController extends Controller
{
    public function create(AppSettings $settings)
    {
        abort_unless((bool) $settings->get('general.registration_enabled', false), 404);
        return response()->view('auth.register')->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, AppSettings $settings)
    {
        abort_unless((bool) $settings->get('general.registration_enabled', false), 404);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'username' => 'required|string|min:3|max:60|alpha_dash|unique:users,username',
            'email' => 'required|email:rfc|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ]);

        $activation = (string) $settings->get('general.registration_activation', 'automatic');
        $verifyEmail = (bool) $settings->get('general.email_verification_enabled', false);
        if ($activation !== 'admin' && $verifyEmail) {
            $activation = 'email';
        }

        $user = User::create([
            'name' => $data['name'],
            'username' => strtolower($data['username']),
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'status' => $activation === 'automatic' ? 'active' : 'inactive',
            'balance' => 0,
            'group_id' => $settings->get('general.default_group_id') ?: Group::query()->orderBy('id')->value('id'),
        ]);

        FinanceAccount::query()->firstOrCreate(['user_id' => $user->id], [
            'locked_amount' => 0,
            'total_receipts' => 0,
            'paid_credits' => 0,
            'overdraft_limit' => $settings->get('general.default_overdraft', '0'),
        ]);

        if ($activation === 'automatic') {
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->route('customer.dashboard');
        }

        if ($activation === 'email') {
            $code = (string) random_int(100000, 999999);
            Cache::put($this->verificationKey($user), Hash::make($code), now()->addMinutes(15));
            $request->session()->put('registration_verification_user_id', $user->id);
            try {
                Mail::raw(
                    "Your email verification code is: {$code}\n\nThis code expires in 15 minutes.",
                    fn ($message) => $message->to($user->email)->subject('Your verification code')
                );
            } catch (Throwable $exception) {
                report($exception);
                Cache::forget($this->verificationKey($user));
                $request->session()->forget('registration_verification_user_id');
                // A failed delivery must not leave a permanently unusable account
                // that prevents the visitor from registering again after SMTP is fixed.
                $user->delete();
                throw ValidationException::withMessages([
                    'email' => $this->mailUnavailableMessage(),
                ]);
            }
            return redirect()->route('register.verify');
        }

        return redirect()->route('login')->with('ok', 'Your account is awaiting administrator approval.');
    }

    public function verificationForm(Request $request)
    {
        $user = $this->pendingUser($request);
        return response()->view('auth.verify-email', ['email' => $this->maskedEmail($user->email)])
            ->header('Cache-Control', 'no-store, private');
    }

    public function verify(Request $request)
    {
        $user = $this->pendingUser($request);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $stored = Cache::get($this->verificationKey($user));

        if (!is_string($stored) || !Hash::check($data['code'], $stored)) {
            throw ValidationException::withMessages(['code' => 'The verification code is invalid or has expired.']);
        }

        Cache::forget($this->verificationKey($user));
        $request->session()->forget('registration_verification_user_id');
        $user->forceFill(['status' => 'active', 'email_verified_at' => now()])->save();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard')->with('ok', 'Your email has been verified.');
    }

    private function pendingUser(Request $request): User
    {
        $id = $request->session()->get('registration_verification_user_id');
        abort_unless($id, 404);
        return User::query()->whereKey($id)->where('status', 'inactive')->firstOrFail();
    }

    private function verificationKey(User $user): string
    {
        return 'registration-verification:'.$user->id;
    }

    private function maskedEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return mb_substr($name, 0, 2).str_repeat('•', max(2, mb_strlen($name) - 2)).'@'.$domain;
    }

    private function mailUnavailableMessage(): string
    {
        return match (app()->getLocale()) {
            'ar' => 'تعذّر إرسال رمز التحقق حاليًا. يرجى المحاولة مرة أخرى بعد قليل أو التواصل مع الإدارة.',
            'fr' => 'Le code de vérification ne peut pas être envoyé pour le moment. Réessayez plus tard ou contactez l’administration.',
            'es' => 'No se pudo enviar el código de verificación. Inténtalo más tarde o contacta con la administración.',
            'de' => 'Der Bestätigungscode konnte momentan nicht gesendet werden. Bitte später erneut versuchen oder den Administrator kontaktieren.',
            'tr' => 'Doğrulama kodu şu anda gönderilemedi. Lütfen daha sonra tekrar deneyin veya yöneticiyle iletişime geçin.',
            default => 'The verification code could not be sent right now. Please try again later or contact the administrator.',
        };
    }
}
