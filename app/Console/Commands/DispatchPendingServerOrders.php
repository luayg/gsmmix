<?php

namespace App\Console\Commands;

use App\Models\ServerOrder;

class DispatchPendingServerOrders extends BaseDispatchPendingOrders
{
    protected $signature = 'orders:dispatch-pending-server {--limit=50}';
    protected $description = 'Dispatch pending Server orders that are queued (waiting) and not yet sent to provider (no remote_id).';

    protected function orderModelClass(): string
    {
        return ServerOrder::class;
    }

    protected function dispatchKind(): string
    {
        return 'server';
    }
}