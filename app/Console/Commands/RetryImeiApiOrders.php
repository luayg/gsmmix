<?php

namespace App\Console\Commands;

use App\Models\ImeiOrder;
use App\Services\Orders\OrderDispatchClaimService;
use App\Services\Orders\OrderDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryImeiApiOrders extends Command
{
    protected $signature = 'orders:retry-imei {--limit=20}';
    protected $description = 'Retry sending IMEI API orders that are still waiting (provider unreachable previously).';

    public function handle(OrderDispatcher $dispatcher, OrderDispatchClaimService $claims): int
    {
        $limit = max(1, min(500, (int)$this->option('limit')));

        $orders = ImeiOrder::query()
            ->where('api_order', 1)
            ->where('status', 'waiting')
            ->where(function ($q) {
                $q->whereNull('processing')->orWhere('processing', 0)->orWhere('processing', false);
            })
            ->where(function ($q) {
                $q->whereNull('remote_id')->orWhere('remote_id', '');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $this->info("Found {$orders->count()} IMEI retry candidates...");

        $attempted = 0;
        foreach ($orders as $candidate) {
            $claimed = $claims->claim(ImeiOrder::class, (int)$candidate->id);
            if (!$claimed) {
                continue;
            }

            $attempted++;

            try {
                $dispatcher->send('imei', (int)$claimed->id);
            } catch (\Throwable $e) {
                Log::warning('Retry dispatch failed', [
                    'id' => $claimed->id,
                    'err' => $e->getMessage(),
                ]);
                $claims->releaseUnexpectedFailure(ImeiOrder::class, (int)$claimed->id);
            }

            $fresh = ImeiOrder::query()->find((int)$claimed->id);
            if (!$fresh) {
                continue;
            }

            if (trim((string)$fresh->remote_id) !== '') {
                $this->line("Sent order #{$fresh->id} => remote_id={$fresh->remote_id}");
            } else {
                $message = trim((string)data_get($fresh->response, 'message', 'Queued for another retry.'));
                $this->warn("Not sent order #{$fresh->id}: {$message}");
            }
        }

        $this->info("Done. attempted={$attempted}.");
        return self::SUCCESS;
    }
}
