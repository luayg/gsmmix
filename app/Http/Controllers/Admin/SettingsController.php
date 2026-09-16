<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateGeneralSettingsRequest;
use App\Http\Requests\UpdateMailSettingsRequest;
use App\Services\Settings\AppSettings;
use App\Models\Group;
use App\Models\MailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Illuminate\Support\Str;

final class SettingsController extends Controller
{
    public function __construct(private readonly AppSettings $settings) {}

    public function general()
    {
        $settings = array_replace($this->generalDefaults(), $this->settings->group('general'));
        $groups = Group::query()->orderBy('name')->get(['id', 'name']);
        return view('admin.settings.general', compact('settings', 'groups'));
    }

    public function updateGeneral(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $current = $this->settings->group('general');
        $logo = $current['general.logo'] ?? null;
        $favicon = $current['general.favicon'] ?? null;
        if ($request->hasFile('logo')) {
            $newLogo = $request->file('logo')->store('settings', 'public');
            if (is_string($logo) && str_starts_with($logo, 'settings/')) {
                Storage::disk('public')->delete($logo);
            }
            $logo = $newLogo;
        }
        if ($request->hasFile('favicon')) {
            $newFavicon = $request->file('favicon')->store('settings', 'public');
            if (is_string($favicon) && str_starts_with($favicon, 'settings/')) Storage::disk('public')->delete($favicon);
            $favicon = $newFavicon;
        }

        $values = [];
        foreach (['site_name', 'site_tagline', 'contact_email', 'contact_phone', 'contact_address', 'facebook_url', 'instagram_url', 'x_url', 'linkedin_url', 'telegram_url', 'meta_title', 'meta_description', 'meta_keywords', 'timezone', 'date_format', 'registration_activation'] as $key) {
            $values['general.'.$key] = ['value' => array_key_exists($key, $data) ? $data[$key] : ($current['general.'.$key] ?? $this->generalDefaults()['general.'.$key])];
        }
        foreach (['registration_enabled', 'show_prices_to_guests', 'show_original_prices', 'allow_credit_transfers', 'use_24_hour_time', 'session_expire_on_close', 'service_imei_enabled', 'service_server_enabled', 'service_file_enabled', 'service_smm_enabled', 'store_enabled'] as $key) {
            $values['general.'.$key] = ['value' => $request->boolean($key), 'type' => 'boolean'];
        }
        foreach (['default_group_id', 'session_lifetime'] as $key) $values['general.'.$key] = ['value' => $data[$key] ?? ($current['general.'.$key] ?? $this->generalDefaults()['general.'.$key]), 'type' => 'integer'];
        foreach (['default_overdraft', 'api_low_balance_threshold'] as $key) $values['general.'.$key] = ['value' => $data[$key] ?? ($current['general.'.$key] ?? '0'), 'type' => 'decimal'];
        $values['general.logo'] = ['value' => $logo];
        $values['general.favicon'] = ['value' => $favicon];
        $this->settings->putMany('general', $values);

        return back()->with('ok', 'General settings updated.');
    }

    public function mail()
    {
        $settings = array_replace($this->mailDefaults(), $this->settings->group('mail'));
        $passwordConfigured = filled($settings['mail.password'] ?? null);
        unset($settings['mail.password']);
        $templates = MailTemplate::query()->orderBy('name')->paginate(15);
        return view('admin.settings.mail', compact('settings', 'passwordConfigured', 'templates'));
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

    public function storeMailTemplate(Request $request): RedirectResponse
    {
        abort_unless($request->user('web')?->can('settings.create'), 403);
        $data = $this->validateTemplate($request);
        MailTemplate::create($data);
        return back()->with('ok', 'Mail template created.');
    }

    public function updateMailTemplate(Request $request, MailTemplate $template): RedirectResponse
    {
        abort_unless($request->user('web')?->can('settings.edit'), 403);
        $template->update($this->validateTemplate($request, $template));
        return back()->with('ok', 'Mail template updated.');
    }

    public function destroyMailTemplate(Request $request, MailTemplate $template): RedirectResponse
    {
        abort_unless($request->user('web')?->can('settings.delete'), 403);
        $template->delete();
        return back()->with('ok', 'Mail template deleted.');
    }

    public function previewMailTemplate(Request $request, MailTemplate $template)
    {
        abort_unless($request->user('web')?->can('settings.view'), 403);
        $withoutExecutableTags = preg_replace('~<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>~is', '', $template->body) ?? '';
        $html = Str::of(strip_tags($withoutExecutableTags, '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><a><table><thead><tbody><tr><th><td>'))
            ->replaceMatches('/\son\w+\s*=\s*(["\']).*?\1/i', '')
            ->replaceMatches('/javascript\s*:/i', '');
        return response((string) $html)->header('Content-Type', 'text/html; charset=UTF-8')->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'");
    }

    private function validateTemplate(Request $request, ?MailTemplate $template = null): array
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.-]+$/', \Illuminate\Validation\Rule::unique('mail_templates', 'key')->ignore($template?->id)],
            'name' => ['required', 'string', 'max:150'], 'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'], 'audience' => ['required', \Illuminate\Validation\Rule::in(['user', 'admin', 'both'])],
            'active' => ['sometimes', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active');
        return $data;
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
            'general.contact_address' => '', 'general.facebook_url' => '', 'general.instagram_url' => '',
            'general.x_url' => '', 'general.linkedin_url' => '', 'general.telegram_url' => '',
            'general.meta_title' => '', 'general.meta_description' => '', 'general.meta_keywords' => '',
            'general.timezone' => config('app.timezone', 'UTC'), 'general.date_format' => 'Y-m-d',
            'general.registration_enabled' => false, 'general.registration_activation' => 'email',
            'general.default_group_id' => null, 'general.default_overdraft' => '0',
            'general.show_prices_to_guests' => false, 'general.show_original_prices' => false,
            'general.allow_credit_transfers' => false, 'general.use_24_hour_time' => true,
            'general.session_lifetime' => (int) config('session.lifetime', 120),
            'general.session_expire_on_close' => (bool) config('session.expire_on_close', false),
            'general.api_low_balance_threshold' => '0', 'general.service_imei_enabled' => true,
            'general.service_server_enabled' => true, 'general.service_file_enabled' => true,
            'general.service_smm_enabled' => true, 'general.store_enabled' => true, 'general.logo' => null, 'general.favicon' => null,
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
