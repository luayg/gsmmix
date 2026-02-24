?php

namespace App\Console\Commands;

use App\Models\ServerOrder;
use App\Services\Orders\OrderDispatcher;
use Illuminate\Console\Command;

class DispatchPendingServerOrders extends Command
{
    protected $signature = 'orders:dispatch-pending-server {--limit=50}';
    protected $description = 'Dispatch pending Server orders that are queued (waiting) and not yet sent to provider (no remote_id).';

    public function handle(OrderDispatcher $dispatcher): int
    {
        $limit = (int)$this->option('limit');

        $orders = ServerOrder::query()
            ->where('api_order', 1)
            ->where('status', 'waiting')
            ->where(function ($q) {
                $q->whereNull('remote_id')->orWhere('remote_id', '');
            })
            ->where(function ($q) {
                $q->whereNull('processing')->orWhere('processing', 0)->orWhere('processing', false);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $this->info("Dispatching {$orders->count()} pending Server orders...");

        foreach ($orders as $o) {
            try {
                $dispatcher->send('server', (int)$o->id);
                $this->line(" - Sent attempt for order #{$o->id}");
            } catch (\Throwable $e) {
                $this->error(" - Failed order #{$o->id}: " . $e->getMessage());
            }
        }

        $this->info('Done.');
        return 0;
    }
}