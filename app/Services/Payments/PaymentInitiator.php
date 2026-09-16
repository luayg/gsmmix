<?php

namespace App\Services\Payments;

use App\Models\Currency;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

final class PaymentInitiator
{
    public function __construct(private PaymentQuote $quotes, private PayPalGateway $paypal, private BinancePayGateway $binance) {}

    /** The customer deposit page can call this service without containing provider-specific logic. */
    public function create(User $user, PaymentGateway $gateway, Currency $currency, string $amountBase, string $returnUrl, string $cancelUrl): array
    {
        if (!$gateway->is_system || !in_array($gateway->driver, ['paypal','binance_pay','usdt'], true)) throw new RuntimeException('This is not an automatic payment gateway.');
        $quote = $this->quotes->calculate($amountBase, $gateway, $currency);
        $uuid = (string) Str::uuid();
        $payment = PaymentTransaction::create($quote + [
            'uuid'=>$uuid, 'user_id'=>$user->id, 'payment_gateway_id'=>$gateway->id,
            'currency_id'=>$currency->id, 'status'=>'pending', 'metadata'=>['expires_at'=>now()->addHour()->toIso8601String()],
        ]);
        try {
            $provider = match ($gateway->driver) {
                'paypal' => $this->paypal->createOrder($gateway, $uuid, $quote['payable_currency'], $currency->code, $returnUrl.(str_contains($returnUrl,'?')?'&':'?').'payment='.urlencode($uuid), $cancelUrl),
                'binance_pay' => $this->binance->createOrder($gateway, $uuid, $quote['payable_currency'], $currency->code, route('payment.webhook','binance-pay')),
                'usdt' => $this->usdtInstructions($gateway, $payment),
            };
            $payment->forceFill(['external_id'=>$provider['external_id'] ?? null, 'metadata'=>array_merge($payment->metadata ?? [], ['provider_order'=>$provider])])->save();
            return ['payment'=>$payment->fresh(), 'provider'=>$provider];
        } catch (\Throwable $e) {
            $payment->forceFill(['status'=>'cancelled','metadata'=>array_merge($payment->metadata ?? [], ['creation_error'=>str($e->getMessage())->limit(500)])])->save();
            throw $e;
        }
    }

    private function usdtInstructions(PaymentGateway $gateway, PaymentTransaction $payment): array
    {
        $credentials = $gateway->credentials ?? [];
        $amount = (string) $payment->payable_currency;
        for ($i = 0; $i < 1000; $i++) {
            $candidate = bcadd($amount, bcdiv((string) $i, '1000000', 8), 8);
            $collision = PaymentTransaction::query()->where('payment_gateway_id',$gateway->id)->where('status','pending')->where('id','!=',$payment->id)->where('payable_currency',$candidate)->exists();
            if (!$collision) { $amount = $candidate; break; }
        }
        $payment->forceFill(['payable_currency'=>$amount])->save();
        return [
            'network'=>data_get($gateway->config,'network'), 'address'=>$credentials['wallet_address'] ?? null,
            'amount'=>$amount, 'contract_address'=>$credentials['contract_address'] ?? null,
            'expires_at'=>data_get($payment->metadata,'expires_at'),
        ];
    }
}
