<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateGeneralSettingsRequest;
use App\Http\Requests\UpdateMailSettingsRequest;
use App\Services\Settings\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SettingsController extends Controller
{
    public function __construct(private readonly AppSettings $settings) {}

    public function general()
    {
        $settings = array_replace($this->generalDefaults(), $this->settings->group('general'));
        return view('admin.settings.general', compact('settings'));
    }

    public function updateGeneral(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $current = $this->settings->group('general');
        $logo = $current['general.logo'] ?? null;
        if ($request->hasFile('logo')) {
            $newLogo = $request->file('logo')->store('settings', 'public');
            if (is_string($logo) && str_starts_with($logo, 'settings/')) {
                Storage::disk('public')->delete($logo);
            }
            $logo = $newLogo;
        }

        $values = [];
        foreach (['site_name', 'site_tagline', 'contact_email', 'contact_phone', 'timezone', 'date_format'] as $key) {
            $values['general.'.$key] = ['value' => $data[$key] ?? null];
        }
        foreach (['registration_enabled', 'service_imei_enabled', 'service_server_enabled', 'service_file_enabled', 'service_smm_enabled', 'store_enabled'] as $key) {
            $values['general.'.$key] = ['value' => $request->boolean($key), 'type' => 'boolean'];
        }
        $values['general.logo'] = ['value' => $logo];
        $this->settings->putMany('general', $values);

        return back()->with('ok', 'General settings updated.');
    }

    public function mail()
    {
        $settings = array_replace($this->mailDefaults(), $this->settings->group('mail'));
        $passwordConfigured = filled($settings['mail.password'] ?? null);
        unset($settings['mail.password']);
        return view('admin.settings.mail', compact('settings', 'passwordConfigured'));
    }

    public function updateMail(UpdateMailSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $values = [
            'mail.mailer' => ['value' => $data['mailer']],
            'mail.host' => ['value' => $data['host'] ?? null],
            'mail.port' => ['value' => $data['port'] ?? null, 'type' => 'integer'],
            'mail.encryption' => ['value' => $data['encryption'] ?? null],
            'mail.username' => ['value' => $data['username'] ?? null],
            'mail.from_address' => ['value' => $data['from_address']],
            'mail.from_name' => ['value' => $data['from_name']],
            'mail.timeout' => ['value' => $data['timeout'], 'type' => 'integer'],
        ];
        if (filled($data['password'] ?? null)) {
            $values['mail.password'] = ['value' => $data['password'], 'encrypted' => true];
        }
        $this->settings->putMany('mail', $values);
        return back()->with('ok', 'Mail settings updated.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        abort_unless($request->user('web')?->can('settings.edit'), 403);
        $data = $request->validate(['test_email' => ['required', 'email:rfc', 'max:255']]);
        try {
            $this->applyMailConfiguration();
            Mail::raw('This is a test email from '.config('app.name').'.', function ($message) use ($data): void {
                $message->to($data['test_email'])->subject('Mail settings test');
            });
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors(['test_email' => 'The test email could not be sent. Check the saved server details and application log.']);
        }
        return back()->with('ok', 'Test email sent.');
    }

    private function applyMailConfiguration(): void
    {
        $mail = array_replace($this->mailDefaults(), $this->settings->group('mail'));
        config([
            'mail.default' => $mail['mail.mailer'],
            'mail.mailers.smtp.host' => $mail['mail.host'],
            'mail.mailers.smtp.port' => $mail['mail.port'],
            'mail.mailers.smtp.scheme' => $mail['mail.encryption'] === 'ssl' ? 'smtps' : null,
            'mail.mailers.smtp.username' => $mail['mail.username'],
            'mail.mailers.smtp.password' => $mail['mail.password'],
            'mail.mailers.smtp.timeout' => $mail['mail.timeout'],
            'mail.from.address' => $mail['mail.from_address'],
            'mail.from.name' => $mail['mail.from_name'],
        ]);
        Mail::purge();
    }

    private function generalDefaults(): array
    {
        return [
            'general.site_name' => config('app.name', 'GSM Mix'), 'general.site_tagline' => '',
            'general.contact_email' => '', 'general.contact_phone' => '',
            'general.timezone' => config('app.timezone', 'UTC'), 'general.date_format' => 'Y-m-d',
            'general.registration_enabled' => false, 'general.service_imei_enabled' => true,
            'general.service_server_enabled' => true, 'general.service_file_enabled' => true,
            'general.service_smm_enabled' => true, 'general.store_enabled' => true, 'general.logo' => null,
        ];
    }

    private function mailDefaults(): array
    {
        return [
            'mail.mailer' => config('mail.default', 'log'), 'mail.host' => config('mail.mailers.smtp.host'),
            'mail.port' => (int) config('mail.mailers.smtp.port', 587), 'mail.encryption' => config('mail.mailers.smtp.scheme') === 'smtps' ? 'ssl' : 'tls',
            'mail.username' => config('mail.mailers.smtp.username'), 'mail.password' => config('mail.mailers.smtp.password'),
            'mail.from_address' => config('mail.from.address', 'hello@example.com'),
            'mail.from_name' => config('mail.from.name', config('app.name')), 'mail.timeout' => (int) (config('mail.mailers.smtp.timeout') ?: 10),
        ];
    }
}
