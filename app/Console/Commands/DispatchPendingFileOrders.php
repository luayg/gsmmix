<?php

namespace App\Console\Commands;

use App\Models\FileOrder;
use App\Services\Orders\OrderDispatcher;
use Illuminate\Console\Command;

class DispatchPendingFileOrders extends Command
{
    protected $signature = 'orders:dispatch-pending-file {--limit=50}';
    protected $description = 'Dispatch pending File orders that are queued (waiting) and not yet sent to provider (no remote_id).';

    public function handle(OrderDispatcher $dispatcher): int
    {
        $limit = (int)$this->option('limit');
        if ($limit < 1) $limit = 50;
        if ($limit > 500) $limit = 500;

        $orders = FileOrder::query()
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

        $attempted = $orders->count();
        $sent = 0;
        $failed = 0;

        $this->info("Dispatching {$attempted} pending File orders...");

        foreach ($orders as $o) {
            $dispatcher->send('file', (int)$o->id);

            $o->refresh();
            $remoteId = trim((string)$o->remote_id);

            if ($remoteId !== '') {
                $sent++;
                $this->line(" - Sent order #{$o->id} => remote_id={$remoteId}");
                continue;
            }

            $failed++;
            $reason = $this->extractOrderReason($o);
            $this->warn(" - Not sent order #{$o->id}: {$reason}");
        }

        $this->info("Done. attempted={$attempted} sent={$sent} failed={$failed}.");

        if ($attempted > 0 && $sent === 0) {
            $this->warn('No file orders received remote_id from provider. Check provider credentials, upload field name, file validation, and REQUIRED fields payload.');
        }

        return 0;
    }

    private function extractOrderReason(FileOrder $order): string
    {
        $response = is_array($order->response)
            ? $order->response
            : (is_string($order->response) ? (json_decode($order->response, true) ?: []) : []);

        $request = is_array($order->request)
            ? $order->request
            : (is_string($order->request) ? (json_decode($order->request, true) ?: []) : []);

        $candidates = [
            data_get($response, 'message'),
            data_get($response, 'dhru_comments'),
            data_get($response, 'result_text'),
            data_get($request, 'dispatch_error'),
            data_get($request, 'response_raw.ERROR.0.MESSAGE'),
            data_get($request, 'response_raw.message'),
            data_get($request, 'response_raw.raw'),
            data_get($request, 'request.params._file_field'),
            data_get($request, 'request.params._file_field_tried.0'),
        ];

        foreach ($candidates as $msg) {
            $msg = trim((string)$msg);
            if ($msg !== '') {
                return $msg;
            }
        }

        return 'Unknown reason (remote_id still empty after dispatch).';
    }
}