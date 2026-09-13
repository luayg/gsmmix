<?php

namespace App\Console\Commands;

use App\Services\Orders\OrderDispatchClaimService;
use App\Services\Orders\OrderDispatcher;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class BaseDispatchPendingOrders extends Command
{
    abstract protected function orderModelClass(): string;

    abstract protected function dispatchKind(): string;

    protected function limitOption(): int
    {
        $limit = (int)$this->option('limit');
        if ($limit < 1) $limit = 50;
        if ($limit > 500) $limit = 500;

        return $limit;
    }

    protected function pendingOrders(int $limit): Collection
    {
        $model = $this->orderModelClass();

        /** @var class-string<Model> $model */
        return $model::query()
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
    }

    protected function extractOrderReason(Model $order): string
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

    public function handle(OrderDispatcher $dispatcher, OrderDispatchClaimService $claims): int
    {
        $limit = $this->limitOption();
        $orders = $this->pendingOrders($limit);

        $attempted = 0;
        $sent = 0;
        $failed = 0;

        $label = strtoupper($this->dispatchKind());
        $this->info("Found {$orders->count()} pending {$label} candidates...");

        foreach ($orders as $candidate) {
            $claimed = $claims->claim($this->orderModelClass(), (int)$candidate->id);
            if (!$claimed) {
                continue;
            }

            $attempted++;

            try {
                $dispatcher->send($this->dispatchKind(), (int)$claimed->id);
            } catch (\Throwable $e) {
                // OrderDispatcher handles normal provider/network failures itself.
                // Release only an unexpected local failure so the order is not stuck.
                $claims->releaseUnexpectedFailure($this->orderModelClass(), (int)$claimed->id);
            }

            /** @var Model|null $fresh */
            $model = $this->orderModelClass();
            $fresh = $model::query()->find((int)$claimed->id);
            if (!$fresh) {
                $failed++;
                continue;
            }

            $remoteId = trim((string)$fresh->remote_id);
            if ($remoteId !== '') {
                $sent++;
                $this->line(" - Sent order #{$fresh->id} => remote_id={$remoteId}");
                continue;
            }

            $failed++;
            $reason = $this->extractOrderReason($fresh);
            $this->warn(" - Not sent order #{$fresh->id}: {$reason}");
        }

        $this->info("Done. attempted={$attempted} sent={$sent} failed={$failed}.");

        if ($attempted > 0 && $sent === 0) {
            $this->warn("No {$label} orders received remote_id from provider. Check provider credentials, service mapping, required fields, and provider response.");
        }

        return self::SUCCESS;
    }
}
