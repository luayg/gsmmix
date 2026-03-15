<?php

namespace App\Console\Commands;

use App\Models\ImeiOrder;

class DispatchPendingImeiOrders extends BaseDispatchPendingOrders
{
    protected $signature = 'orders:dispatch-pending-imei {--limit=50}';
    protected $description = 'Dispatch pending IMEI orders that are queued (waiting) and not yet sent to provider (no remote_id).';

    protected function orderModelClass(): string
    {
        return ImeiOrder::class;
    }

    protected function dispatchKind(): string
    {
        return 'imei';
    }
}