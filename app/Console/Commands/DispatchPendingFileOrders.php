<?php

namespace App\Console\Commands;

use App\Models\FileOrder;

class DispatchPendingFileOrders extends BaseDispatchPendingOrders
{
    protected $signature = 'orders:dispatch-pending-file {--limit=50}';
    protected $description = 'Dispatch pending File orders that are queued (waiting) and not yet sent to provider (no remote_id).';

    protected function orderModelClass(): string
    {
        return FileOrder::class;
    }

    protected function dispatchKind(): string
    {
        return 'file';
    }
}