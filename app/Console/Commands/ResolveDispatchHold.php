<?php

namespace App\Console\Commands;

use App\Services\Orders\DispatchHoldRecoveryService;
use Illuminate\Console\Command;

class ResolveDispatchHold extends Command
{
    protected $signature = 'orders:resolve-dispatch-hold
        {kind : imei|server|file|smm}
        {id : Local order ID}
        {remote_id : Provider order/reference ID}
        {--note= : Optional operator note}';

    protected $description = 'Safely attach a recovered provider remote ID to an order on dispatch hold';

    public function handle(DispatchHoldRecoveryService $service): int
    {
        try {
            $order = $service->resolve(
                (string)$this->argument('kind'),
                (int)$this->argument('id'),
                (string)$this->argument('remote_id'),
                $this->option('note') !== null ? (string)$this->option('note') : null,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Resolved %s order #%d => remote_id=%s; status=%s',
            strtolower((string)$this->argument('kind')),
            (int)$order->getKey(),
            (string)$order->remote_id,
            (string)$order->status,
        ));

        return self::SUCCESS;
    }
}
