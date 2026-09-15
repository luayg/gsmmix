<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentQuote;
use Illuminate\Support\Str;
use Tests\Support\SecurityTestCase;

final class PaymentGatewaySettingsTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_09_16_000200_create_languages_and_currencies_tables.php'))->up();
        (require database_path('migrations/2026_09_16_000300_create_payment_tables.php'))->up();
        $this->actingAs($this->user('Administrator'));
    }

    public function test_active_manual_gateway_can_be_configured_and_quoted(): void
    {
        $currency = Currency::query()->where('code', 'USD')->firstOrFail();
        $this->post(route('admin.settings.payment.store'), $this->payload([
            'currency_ids' => [$currency->id], 'active' => '1', 'fixed_fee' => '1', 'percent_fee' => '2',
        ]))->assertRedirect()->assertSessionHas('ok');
        $gateway = PaymentGateway::query()->where('slug', 'bank-transfer')->firstOrFail();
        $this->assertTrue($gateway->active);
        $this->assertTrue($gateway->currencies->contains($currency));
        $quote = app(PaymentQuote::class)->calculate('100', $gateway, $currency);
        $this->assertSame('3.00000000', $quote['fee_base']);
        $this->assertSame('103.00000000', $quote['payable_base']);
        $this->assertSame('103.00000000', $quote['payable_currency']);
    }

    public function test_electronic_credentials_are_encrypted_hidden_and_preserved_when_blank(): void
    {
        $currency = Currency::query()->firstOrFail();
        $secret = 'processor-secret-value';
        $this->post(route('admin.settings.payment.store'), $this->payload([
            'name' => 'Stripe', 'slug' => 'stripe', 'driver' => 'stripe', 'active' => '0',
            'currency_ids' => [$currency->id], 'api_key' => 'public-test-key', 'api_secret' => $secret,
        ]))->assertRedirect();
        $gateway = PaymentGateway::query()->where('slug', 'stripe')->firstOrFail();
        $raw = $gateway->getRawOriginal('credentials');
        $this->assertStringNotContainsString($secret, $raw);
        $this->assertSame($secret, $gateway->credentials['api_secret']);
        $this->assertArrayNotHasKey('credentials', $gateway->toArray());
        $this->get(route('admin.settings.payment'))->assertOk()->assertDontSee($secret);

        $this->put(route('admin.settings.payment.update', $gateway), $this->payload([
            'name' => 'Stripe updated', 'slug' => 'stripe', 'driver' => 'stripe', 'active' => '0',
            'currency_ids' => [$currency->id], 'api_key' => '', 'api_secret' => '', 'webhook_secret' => '',
        ]))->assertRedirect();
        $this->assertSame($raw, $gateway->fresh()->getRawOriginal('credentials'));
    }

    public function test_unimplemented_electronic_driver_cannot_be_activated(): void
    {
        $currency = Currency::query()->firstOrFail();
        $this->post(route('admin.settings.payment.store'), $this->payload([
            'slug' => 'unsafe-live', 'driver' => 'paypal', 'active' => '1', 'currency_ids' => [$currency->id],
        ]))->assertSessionHasErrors('active');
        $this->assertDatabaseMissing('payment_gateways', ['slug' => 'unsafe-live']);
    }

    public function test_gateway_with_financial_history_cannot_be_deleted(): void
    {
        $currency = Currency::query()->firstOrFail();
        $this->post(route('admin.settings.payment.store'), $this->payload(['currency_ids' => [$currency->id]]))->assertRedirect();
        $gateway = PaymentGateway::query()->firstOrFail();
        PaymentTransaction::create([
            'uuid' => (string) Str::uuid(), 'user_id' => auth()->id(), 'payment_gateway_id' => $gateway->id,
            'currency_id' => $currency->id, 'currency_code' => $currency->code, 'exchange_rate' => '1',
            'amount_base' => '10', 'fee_base' => '0', 'payable_base' => '10', 'payable_currency' => '10', 'status' => 'review',
        ]);
        $this->delete(route('admin.settings.payment.destroy', $gateway))->assertSessionHasErrors('gateway');
        $this->assertDatabaseHas('payment_gateways', ['id' => $gateway->id]);
    }

    public function test_payment_settings_remain_administrator_only(): void
    {
        $staff = $this->user();
        $staff->givePermissionTo(['admin.access', 'settings.view', 'settings.create', 'settings.edit', 'settings.delete']);
        $this->actingAs($staff);
        $this->get(route('admin.settings.payment'))->assertForbidden();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Bank transfer', 'slug' => 'bank-transfer', 'driver' => 'manual',
            'description' => 'Manual transfer', 'instructions' => 'Upload proof for review.',
            'payment_details' => 'Bank: Test bank; IBAN: TEST123', 'fixed_fee' => '0', 'percent_fee' => '0',
            'minimum_amount' => '1', 'maximum_amount' => '10000', 'sandbox' => '1', 'active' => '0',
            'ordering' => 0, 'currency_ids' => [], 'client_id' => '', 'api_key' => '', 'api_secret' => '', 'webhook_secret' => '',
        ], $overrides);
    }
}
