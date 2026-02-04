<?php

namespace App\Console\Commands;

use App\Models\ImeiOrder;
use App\Services\Orders\OrderDispatcher;
use Illuminate\Console\Command;

class DispatchPendingImeiOrders extends Command
{
    protected $signature = 'orders:dispatch-pending-imei {--limit=50}';
    protected $description = 'Dispatch pending IMEI orders that are queued (waiting) and not yet sent to provider (no remote_id).';

    public function handle(OrderDispatcher $dispatcher): int
    {
        $limit = (int)$this->option('limit');

        $orders = ImeiOrder::query()
            ->where('api_order', 1)
            ->where('status', 'waiting')
            ->where(function ($q) {
                $q->whereNull('remote_id')->orWhere('remote_id', '');
            })
            ->where(function ($q) {
                // processing قد يكون 0/false/null
                $q->whereNull('processing')->orWhere('processing', 0)->orWhere('processing', false);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $this->info("Dispatching {$orders->count()} pending IMEI orders...");

        foreach ($orders as $o) {
            try {
                $dispatcher->send('imei', (int)$o->id);
                $this->line(" - Sent attempt for order #{$o->id}");
            } catch (\Throwable $e) {
                // لا نوقف الكل
                $this->error(" - Failed order #{$o->id}: " . $e->getMessage());
            }
        }

        $this->info("Done.");
        return 0;
    }
}
