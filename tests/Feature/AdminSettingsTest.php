<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Settings\AppSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use App\Models\MailTemplate;
use Tests\Support\SecurityTestCase;

final class AdminSettingsTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_09_16_000100_create_settings_table.php'))->up();
        (require database_path('migrations/2026_09_16_000200_create_languages_and_currencies_tables.php'))->up();
        (require database_path('migrations/2026_09_16_000300_create_payment_tables.php'))->up();
        (require database_path('migrations/2026_09_16_000400_expand_admin_settings.php'))->up();
    }

    public function test_settings_are_restricted_to_administrators(): void
    {
        $viewer = $this->user();
        $viewer->givePermissionTo(['admin.access', 'settings.view']);
        $this->actingAs($viewer);
        $this->get(route('admin.settings.general'))->assertForbidden();
        $this->get(route('admin.settings.mail'))->assertForbidden();
        $this->put(route('admin.settings.general.update'), $this->generalPayload())->assertForbidden();
        $this->put(route('admin.settings.mail.update'), $this->mailPayload())->assertForbidden();

    }

    public function test_general_settings_are_validated_and_saved_with_boolean_values(): void
    {
        $this->actingAs($this->user('Administrator'));
        $this->put(route('admin.settings.general.update'), $this->generalPayload([
            'site_name' => 'GSM JO', 'registration_enabled' => '1', 'service_smm_enabled' => '0',
        ]))->assertRedirect()->assertSessionHas('ok');
        $settings = app(AppSettings::class)->group('general');
        $this->assertSame('GSM JO', $settings['general.site_name']);
        $this->assertTrue($settings['general.registration_enabled']);
        $this->assertFalse($settings['general.service_smm_enabled']);
        $this->assertSame('Asia/Amman', $settings['general.timezone']);
    }

    public function test_mail_password_is_encrypted_and_never_rendered_or_flashed(): void
    {
        $this->actingAs($this->user('Administrator'));
        $secret = 'smtp-secret-test-value';
        $this->put(route('admin.settings.mail.update'), $this->mailPayload(['password' => $secret]))
            ->assertRedirect()->assertSessionHas('ok');
        $row = Setting::query()->where('setting_key', 'mail.password')->firstOrFail();
        $raw = $row->getRawOriginal('value');
        $this->assertNotSame($secret, $raw);
        $this->assertSame($secret, Crypt::decryptString($raw));
        $this->assertTrue($row->is_encrypted);
        $this->get(route('admin.settings.mail'))->assertOk()->assertDontSee($secret);
        $this->from(route('admin.settings.mail'))->put(route('admin.settings.mail.update'), $this->mailPayload([
            'from_address' => 'not-an-email', 'password' => 'must-not-be-flashed',
        ]))->assertRedirect(route('admin.settings.mail'))
            ->assertSessionHasErrors('from_address')->assertSessionMissing('_old_input.password');
    }

    public function test_blank_password_keeps_the_existing_encrypted_value(): void
    {
        $this->actingAs($this->user('Administrator'));
        $this->put(route('admin.settings.mail.update'), $this->mailPayload(['password' => 'first-secret']))->assertRedirect();
        $before = DB::table('settings')->where('setting_key', 'mail.password')->value('value');
        $this->put(route('admin.settings.mail.update'), $this->mailPayload(['password' => '']))->assertRedirect();
        $this->assertSame($before, DB::table('settings')->where('setting_key', 'mail.password')->value('value'));
    }

    public function test_mail_templates_can_be_managed_and_preview_strips_scripts(): void
    {
        $this->actingAs($this->user('Administrator'));
        $this->post(route('admin.settings.mail.templates.store'), [
            'key' => 'orders.status', 'name' => 'Order status', 'subject' => 'Order {{order_id}}',
            'body' => '<p onclick="bad()">Updated</p><script>alert(1)</script>', 'audience' => 'user', 'active' => '1',
        ])->assertRedirect()->assertSessionHas('ok');
        $template = MailTemplate::query()->firstOrFail();
        $this->get(route('admin.settings.mail.templates.preview', $template))->assertOk()->assertSee('Updated')->assertDontSee('onclick')->assertDontSee('alert(1)');
    }

    private function generalPayload(array $overrides = []): array
    {
        return array_replace([
            'site_name' => 'GSM Mix', 'site_tagline' => '', 'contact_email' => 'admin@example.test',
            'contact_phone' => '+962700000000', 'timezone' => 'Asia/Amman', 'date_format' => 'Y-m-d',
            'registration_enabled' => '0', 'service_imei_enabled' => '1', 'service_server_enabled' => '1',
            'service_file_enabled' => '1', 'service_smm_enabled' => '1', 'store_enabled' => '1',
        ], $overrides);
    }

    private function mailPayload(array $overrides = []): array
    {
        return array_replace([
            'mailer' => 'smtp', 'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls',
            'username' => 'mailer@example.test', 'password' => '', 'from_address' => 'noreply@example.test',
            'from_name' => 'GSM Mix', 'timeout' => 10,
        ], $overrides);
    }
}
