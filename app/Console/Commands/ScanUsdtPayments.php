<?php

namespace App\Console\Commands;

use App\Models\PaymentGateway;
use App\Services\Payments\UsdtPaymentScanner;
use Illuminate\Console\Command;

final class ScanUsdtPayments extends Command
{
    protected $signature = 'payments:scan-usdt';
    protected $description = 'Scan the configured USDT network and settle confirmed customer payments';
    public function handle(UsdtPaymentScanner $scanner): int
    {
        $gateway = PaymentGateway::query()->where('slug','usdt')->where('is_system',true)->first();
        if (!$gateway || !$gateway->active) return self::SUCCESS;
        $this->info($scanner->scan($gateway).' payment(s) settled.');
        return self::SUCCESS;
    }
}
