<?php

namespace App\Services\Settings;

use App\Models\Currency;
use InvalidArgumentException;

final class CurrencyConverter
{
    /** Rates are units of the target currency for one unit of the base currency. */
    public function fromBase(string $amount, Currency $currency, int $scale = 8): string
    {
        $this->guard($amount, $currency);
        return bcmul($amount, (string) $currency->exchange_rate, $scale);
    }

    public function toBase(string $amount, Currency $currency, int $scale = 8): string
    {
        $this->guard($amount, $currency);
        return bcdiv($amount, (string) $currency->exchange_rate, $scale);
    }

    public function format(string $amount, Currency $currency): string
    {
        $number = number_format((float) $amount, $currency->decimal_places, $currency->decimal_separator, $currency->thousands_separator);
        return $currency->symbol_position === 'after' ? $number.' '.$currency->symbol : $currency->symbol.$number;
    }

    private function guard(string $amount, Currency $currency): void
    {
        if (!is_numeric($amount) || bccomp((string) $currency->exchange_rate, '0', 8) <= 0) {
            throw new InvalidArgumentException('A numeric amount and positive exchange rate are required.');
        }
    }
}
