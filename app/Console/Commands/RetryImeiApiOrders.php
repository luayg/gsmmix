<?php

namespace App\Console\Commands;

use App\Models\ImeiOrder;
use App\Services\Orders\OrderDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryImeiApiOrders extends Command
{
    protected $signature = 'orders:retry-imei {--limit=20}';
    protected $description = 'Retry sending IMEI API orders that are still waiting (provider unreachable previously).';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        // نعيد المحاولة فقط للطلبات:
        // - api_order = 1 (ليست manual)
        // - status = waiting
        // - processing = 0 (غير قيد الإرسال)
        // - remote_id NULL (لم تُرسل/لم تحصل على رقم مرجعي من المزود بعد)
        $orders = ImeiOrder::query()
            ->where('api_order', 1)
            ->where('status', 'waiting')
            ->where('processing', 0)
            ->whereNull('remote_id')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $this->info("Retrying {$orders->count()} IMEI orders...");

        if ($orders->isEmpty()) {
            $this->info("Nothing to retry.");
            return 0;
        }

        $dispatcher = app(OrderDispatcher::class);

        foreach ($orders as $order) {
            try {
                // علّمها processing أثناء المحاولة
                $order->processing = 1;
                $order->save();

                // إرسال فعلي
                $dispatcher->send('imei', (int)$order->id);

                // لو send نجح غالباً سيغيّر status/remote_id داخل dispatcher
                $this->line("✔ Sent order #{$order->id}");

            } catch (\Throwable $e) {
                Log::warning('Retry dispatch failed', [
                    'id' => $order->id,
                    'err' => $e->getMessage(),
                ]);

                // رجّعها waiting
                $order->processing = 0;
                $order->status = 'waiting';

                $order->request = array_merge((array)$order->request, [
                    'dispatch_failed_at' => now()->toDateTimeString(),
                    'dispatch_error'     => $e->getMessage(),
                    'dispatch_retry'     => ((int) data_get($order->request, 'dispatch_retry', 0)) + 1,
                ]);

                $order->save();

                $this->line("✖ Failed order #{$order->id}: {$e->getMessage()}");
            }
        }

        $this->info("Done.");
        return 0;
    }
}
