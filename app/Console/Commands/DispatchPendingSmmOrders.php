<?php

namespace App\Console\Commands;

use App\Models\SmmOrder;

class DispatchPendingSmmOrders extends BaseDispatchPendingOrders
{
    protected $signature = 'orders:dispatch-pending-smm {--limit=50}';
    protected $description = 'Dispatch pending SMM orders that are queued (waiting) and not yet sent to provider (no remote_id).';

    protected function orderModelClass(): string
    {
        return SmmOrder::class;
    }

    protected function dispatchKind(): string
    {
        return 'smm';
    }
}