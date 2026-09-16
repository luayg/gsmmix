<?php

namespace App\Services\Payments;

use App\Models\Currency;
use App\Models\PaymentGateway;
use DomainException;

final class PaymentQuote
{
    /** @return array{amount_base:string,fee_base:string,payable_base:string,payable_currency:string,exchange_rate:string,currency_code:string} */
    public function calculate(string $amountBase, PaymentGateway $gateway, Currency $currency): array
    {
        if (!$gateway->active || !$currency->active || !$gateway->currencies()->whereKey($currency->id)->exists()) {
            throw new DomainException('This gateway and currency combination is unavailable.');
        }
        if (!is_numeric($amountBase) || bccomp($amountBase, '0', 8) <= 0) {
            throw new DomainException('Payment amount must be positive.');
        }
        if ($gateway->minimum_amount !== null && bccomp($amountBase, (string) $gateway->minimum_amount, 8) < 0) {
            throw new DomainException('Payment amount is below the gateway minimum.');
        }
        if ($gateway->maximum_amount !== null && bccomp($amountBase, (string) $gateway->maximum_amount, 8) > 0) {
            throw new DomainException('Payment amount exceeds the gateway maximum.');
        }
        $percent = bcdiv((string) $gateway->percent_fee, '100', 12);
        $tax = bcdiv((string) ($gateway->tax_percent ?? '0'), '100', 12);
        $fee = bcadd((string) $gateway->fixed_fee, bcadd(bcmul($amountBase, $percent, 12), bcmul($amountBase, $tax, 12), 12), 8);
        $payable = bcadd($amountBase, $fee, 8);
        return [
            'amount_base' => $this->decimal($amountBase), 'fee_base' => $fee, 'payable_base' => $payable,
            'payable_currency' => bcmul($payable, (string) $currency->exchange_rate, 8),
            'exchange_rate' => (string) $currency->exchange_rate, 'currency_code' => $currency->code,
        ];
    }

    private function decimal(string $value): string { return bcadd($value, '0', 8); }
}
