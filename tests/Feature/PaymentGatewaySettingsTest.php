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
        (require database_path('migrations/2026_09_16_000400_expand_admin_settings.php'))->up();
        (require database_path('migrations/2026_09_16_000700_add_system_payment_gateways.php'))->up();
        $this->actingAs($this->user('Administrator'));
    }

    public function test_fixed_gateways_and_manual_section_are_rendered_in_order(): void
    {
        $this->get(route('admin.settings.payment'))->assertOk()
            ->assertSeeInOrder(['PayPal Express Checkout','Binance Pay Merchant','USDT Auto Pay','Manual payment methods']);
    }

    public function test_active_manual_gateway_can_be_created_and_quoted(): void
    {
        $currency = Currency::query()->where('code','USD')->firstOrFail();
        $this->post(route('admin.settings.payment.store'), $this->manual(['currency_ids'=>[$currency->id],'active'=>'1','fixed_fee'=>'1','percent_fee'=>'2']))->assertRedirect()->assertSessionHas('ok');
        $gateway = PaymentGateway::query()->where('slug','bank-transfer')->firstOrFail();
        $this->assertFalse($gateway->is_system);
        $quote = app(PaymentQuote::class)->calculate('100',$gateway,$currency);
        $this->assertSame('3.00000000',$quote['fee_base']);
    }

    public function test_paypal_credentials_are_encrypted_and_fixed_identity_is_immutable(): void
    {
        $currency = Currency::query()->firstOrFail();
        $paypal = PaymentGateway::query()->where('slug','paypal')->firstOrFail();
        $secret = 'paypal-secret';
        $this->put(route('admin.settings.payment.update',$paypal), $this->automatic([
            'name'=>'Tampered','slug'=>'tampered','driver'=>'manual','currency_ids'=>[$currency->id],
            'client_id'=>'client-id','client_secret'=>$secret,'paypal_webhook_id'=>'WH-123',
        ]))->assertRedirect();
        $paypal->refresh();
        $this->assertSame('PayPal Express Checkout',$paypal->name);
        $this->assertSame('paypal',$paypal->driver);
        $this->assertStringNotContainsString($secret,$paypal->getRawOriginal('credentials'));
        $this->assertSame($secret,$paypal->credentials['client_secret']);
        $this->assertArrayNotHasKey('credentials',$paypal->toArray());
    }

    public function test_automatic_gateway_cannot_activate_without_required_credentials(): void
    {
        $currency = Currency::query()->firstOrFail();
        $paypal = PaymentGateway::query()->where('slug','paypal')->firstOrFail();
        $this->put(route('admin.settings.payment.update',$paypal),$this->automatic(['active'=>'1','currency_ids'=>[$currency->id]]))
            ->assertSessionHasErrors(['client_id','client_secret','paypal_webhook_id']);
    }

    public function test_system_gateway_cannot_be_deleted(): void
    {
        $paypal = PaymentGateway::query()->where('slug','paypal')->firstOrFail();
        $this->delete(route('admin.settings.payment.destroy',$paypal))->assertSessionHasErrors('gateway');
        $this->assertDatabaseHas('payment_gateways',['id'=>$paypal->id]);
    }

    public function test_manual_gateway_with_history_cannot_be_deleted(): void
    {
        $currency = Currency::query()->firstOrFail();
        $this->post(route('admin.settings.payment.store'),$this->manual(['currency_ids'=>[$currency->id]]))->assertRedirect();
        $gateway = PaymentGateway::query()->where('slug','bank-transfer')->firstOrFail();
        PaymentTransaction::create(['uuid'=>(string)Str::uuid(),'user_id'=>auth()->id(),'payment_gateway_id'=>$gateway->id,'currency_id'=>$currency->id,'currency_code'=>$currency->code,'exchange_rate'=>'1','amount_base'=>'10','fee_base'=>'0','payable_base'=>'10','payable_currency'=>'10','status'=>'review']);
        $this->delete(route('admin.settings.payment.destroy',$gateway))->assertSessionHasErrors('gateway');
    }

    public function test_payment_settings_remain_administrator_only(): void
    {
        $staff=$this->user(); $staff->givePermissionTo(['admin.access','settings.view','settings.create','settings.edit','settings.delete']);
        $this->actingAs($staff); $this->get(route('admin.settings.payment'))->assertForbidden();
    }

    private function manual(array $overrides=[]): array
    {
        return array_replace($this->automatic(),['name'=>'Bank transfer','slug'=>'bank-transfer','driver'=>'manual','payment_details'=>'IBAN TEST','ordering'=>0],$overrides);
    }

    private function automatic(array $overrides=[]): array
    {
        return array_replace(['description'=>'','instructions'=>'','fixed_fee'=>'0','percent_fee'=>'0','tax_percent'=>'0','minimum_amount'=>'1','maximum_amount'=>'10000','sandbox'=>'1','active'=>'0','currency_ids'=>[]],$overrides);
    }
}
