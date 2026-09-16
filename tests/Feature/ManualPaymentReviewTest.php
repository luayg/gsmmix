<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\SecurityTestCase;

final class ManualPaymentReviewTest extends SecurityTestCase
{
    private Currency $currency;
    private PaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('users', fn (Blueprint $table) => $table->decimal('balance', 14, 4)->default(0));
        (require database_path('migrations/2026_09_16_000200_create_languages_and_currencies_tables.php'))->up();
        (require database_path('migrations/2026_09_16_000300_create_payment_tables.php'))->up();
        (require database_path('migrations/2026_09_16_000400_expand_admin_settings.php'))->up();
        (require database_path('migrations/2026_09_16_000700_add_system_payment_gateways.php'))->up();
        (require database_path('migrations/2025_10_06_000000_create_finances_tables.php'))->up();
        (require database_path('migrations/2026_09_16_000500_create_invoices_and_content_pages.php'))->up();

        $this->currency = Currency::query()->where('code', 'USD')->firstOrFail();
        $this->gateway = PaymentGateway::create([
            'name' => 'Manual bank transfer',
            'slug' => 'manual-bank-transfer',
            'driver' => 'manual',
            'is_system' => false,
            'active' => true,
            'instructions' => 'Send the funds to the company account.',
        ]);
        $this->gateway->currencies()->attach($this->currency);
        Storage::fake('local');
    }

    public function test_customer_can_submit_receipt_and_open_review_page_without_blade_error(): void
    {
        $customer = $this->user();

        $response = $this->actingAs($customer)->post(route('customer.payments.store'), [
            'amount' => '25',
            'gateway_id' => $this->gateway->id,
            'currency_id' => $this->currency->id,
            'proof' => UploadedFile::fake()->image('receipt.jpg', 600, 900),
        ]);

        $payment = PaymentTransaction::query()->firstOrFail();
        $response->assertRedirect(route('customer.payments.show', $payment));
        $this->get(route('customer.payments.show', $payment))
            ->assertOk()
            ->assertSee('waiting for administrator review');
        $this->assertSame('review', $payment->status);
        Storage::disk('local')->assertExists($payment->proof_path);
    }

    public function test_manual_submission_requires_a_receipt(): void
    {
        $this->actingAs($this->user())->post(route('customer.payments.store'), [
            'amount' => '25',
            'gateway_id' => $this->gateway->id,
            'currency_id' => $this->currency->id,
        ])->assertSessionHasErrors('proof');

        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_admin_can_view_proof_and_approve_exactly_once(): void
    {
        $customer = $this->user();
        $admin = $this->user('Administrator');
        $payment = $this->paymentFor($customer->id, 'review', 'payment-proofs/receipt.jpg');
        Storage::disk('local')->put($payment->proof_path, UploadedFile::fake()->image('receipt.jpg')->getContent());

        $this->actingAs($admin)
            ->get(route('admin.finances.payment-reviews.index'))
            ->assertOk()
            ->assertSee($payment->uuid);
        $this->get(route('admin.finances.payment-reviews.proof', $payment))->assertOk();

        $this->post(route('admin.finances.payment-reviews.approve', $payment), ['reference' => 'BANK-123'])
            ->assertRedirect(route('admin.finances.payment-reviews.show', $payment));
        $this->assertSame('25.0000', $customer->fresh()->balance);
        $this->assertDatabaseHas('payment_transactions', ['id' => $payment->id, 'status' => 'paid', 'approved_by' => $admin->id]);
        $this->assertDatabaseCount('finance_transactions', 1);

        $this->post(route('admin.finances.payment-reviews.approve', $payment));
        $this->assertSame('25.0000', $customer->fresh()->balance);
        $this->assertDatabaseCount('finance_transactions', 1);
    }

    public function test_rejection_preserves_balance_and_shows_reason_to_customer(): void
    {
        $customer = $this->user();
        $admin = $this->user('Administrator');
        $payment = $this->paymentFor($customer->id);

        $this->actingAs($admin)->post(route('admin.finances.payment-reviews.reject', $payment), [
            'reason' => 'The receipt could not be verified.',
        ])->assertRedirect(route('admin.finances.payment-reviews.show', $payment));

        $this->assertSame('0.0000', $customer->fresh()->balance);
        $this->assertDatabaseCount('finance_transactions', 0);
        $this->actingAs($customer)->get(route('customer.payments.show', $payment))
            ->assertOk()
            ->assertSee('The receipt could not be verified.');
    }

    public function test_finance_view_permission_cannot_approve_payments(): void
    {
        $staff = $this->user();
        $staff->givePermissionTo(['admin.access', 'finances.view']);
        $payment = $this->paymentFor($this->user()->id);

        $this->actingAs($staff)->get(route('admin.finances.payment-reviews.show', $payment))->assertOk();
        $this->post(route('admin.finances.payment-reviews.approve', $payment))->assertForbidden();
    }

    private function paymentFor(int $userId, string $status = 'review', ?string $proof = null): PaymentTransaction
    {
        return PaymentTransaction::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'payment_gateway_id' => $this->gateway->id,
            'currency_id' => $this->currency->id,
            'currency_code' => 'USD',
            'exchange_rate' => '1',
            'amount_base' => '25',
            'fee_base' => '0',
            'payable_base' => '25',
            'payable_currency' => '25',
            'status' => $status,
            'proof_path' => $proof,
        ]);
    }
}
