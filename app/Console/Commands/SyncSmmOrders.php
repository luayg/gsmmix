<?php

namespace App\Console\Commands;

use App\Models\ApiProvider;
use App\Models\SmmOrder;
use App\Services\Orders\OrderDispatcher;
use App\Services\Orders\OrderFinanceService;
use App\Services\Orders\SmmOrderGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncSmmOrders extends Command
{
    protected $signature = 'orders:sync-smm {--limit=50} {--only-id=}';
    protected $description = 'Sync SMM Orders status/result from SMM providers';

    private function finance(): OrderFinanceService
    {
        return app(OrderFinanceService::class);
    }

    public function handle(SmmOrderGateway $gateway): int
    {
        $limit = (int)$this->option('limit');
        if ($limit < 1) $limit = 50;
        if ($limit > 500) $limit = 500;

        $onlyId = $this->option('only-id');

        if (empty($onlyId)) {
            $dispatched = $this->dispatchPendingSmmWithoutRemoteId($limit);
            if ($dispatched > 0) {
                $this->info("Dispatched {$dispatched} pending SMM orders before sync.");
            }
        }

        $q = SmmOrder::query()
            ->with(['service', 'provider'])
            ->where('api_order', 1)
            ->whereNotNull('remote_id')
            ->whereIn('status', ['waiting', 'inprogress']);

        if (!empty($onlyId)) {
            $q->where('id', (int)$onlyId);
        }

        $orders = $q->orderBy('id', 'asc')->limit($limit)->get();

        if ($orders->isEmpty()) {
            $pendingDispatch = SmmOrder::query()
                ->where('api_order', 1)
                ->where('status', 'waiting')
                ->where(function ($q) {
                    $q->whereNull('remote_id')->orWhere('remote_id', '');
                })
                ->count();

            if ($pendingDispatch > 0) {
                $this->info("No SMM orders to sync yet. {$pendingDispatch} order(s) are still waiting without remote_id after dispatch attempt.");
                $this->info('This usually means provider/API validation/connection issue. Check each order response/request payload.');
            } else {
                $this->info('No SMM orders to sync.');
            }
            return 0;
        }

        $synced = 0;

        foreach ($orders as $order) {
            try {
                $provider = $order->provider ?: ($order->supplier_id ? ApiProvider::find($order->supplier_id) : null);
                if (!$provider || !(int)$provider->active) {
                    $this->warn("Order #{$order->id}: provider missing/inactive.");
                    continue;
                }

                $ref = trim((string)$order->remote_id);
                if ($ref === '') {
                    $this->warn("Order #{$order->id}: remote_id empty.");
                    continue;
                }

                $res = $gateway->getSmmOrderStatus($provider, $ref);

                $req = $this->normalizeResponseArray($order->request);
                $req['last_status_check'] = now()->toDateTimeString();
                $req['status_check_raw']  = $res['response_raw'] ?? null;
                $order->request = $req;

                if (!is_array($res)) {
                    $order->status = 'waiting';
                    $order->processing = 0;
                    $order->save();
                    continue;
                }

                $newStatus = strtolower(trim((string)($res['status'] ?? 'inprogress')));
                if ($newStatus === 'canceled') $newStatus = 'cancelled';

                if (!in_array($newStatus, ['success','rejected','cancelled','inprogress','waiting'], true)) {
                    $newStatus = 'inprogress';
                }

                if ($newStatus === 'waiting' && !empty($order->remote_id)) {
                    $newStatus = 'inprogress';
                }

                $respArr = $this->normalizeResponseArray($order->response);
                $ui = is_array($res['response_ui'] ?? null) ? $res['response_ui'] : [];
                $respArr = array_merge($respArr, $ui, [
                    'provider_type' => 'smm',
                    'reference_id'  => $order->remote_id,
                    'provider_status' => (string)($res['provider_status'] ?? ''),
                ]);

                $raw = is_array($res['response_raw']['raw'] ?? null) ? $res['response_raw']['raw'] : [];
                if (!empty($raw)) {
                    if (array_key_exists('charge', $raw)) {
                        $respArr['charge'] = $raw['charge'];
                    }
                    if (array_key_exists('start_count', $raw)) {
                        $respArr['start_count'] = $raw['start_count'];
                    }
                    if (array_key_exists('remains', $raw)) {
                        $respArr['remains'] = $raw['remains'];
                    }
                    if (array_key_exists('currency', $raw)) {
                        $respArr['currency'] = $raw['currency'];
                    }
                }

                $final = in_array($newStatus, ['success','rejected','cancelled'], true);
                $order->status = $newStatus;
                $order->processing = $final ? 0 : 1;
                if ($final) {
                    $order->replied_at = $order->replied_at ?: now();
                }

                $req = $this->normalizeResponseArray($order->request);
                $req['sync_last_at'] = now()->toDateTimeString();
                $req['sync_raw'] = $res['response_raw'] ?? null;
                $order->request = $req;

                $order->response = $respArr;
                $order->save();

                if ($newStatus === 'rejected') {
                    $this->finance()->refundOrderIfNeeded($order, 'api_rejected');
                }

                if ($newStatus === 'cancelled') {
                    $this->finance()->refundOrderIfNeeded($order, 'api_cancelled');
                }

                $synced++;
                $this->info("Order #{$order->id} synced => {$newStatus} (SMM)");
            } catch (\Throwable $e) {
                Log::error('SyncSmmOrders error', [
                    'order_id' => $order->id ?? null,
                    'err' => $e->getMessage(),
                ]);
                $this->warn("Order #{$order->id}: error => " . $e->getMessage());

                $order->status = 'waiting';
                $order->processing = 0;
                $order->response = ['type'=>'queued','message'=>'Sync error, will retry: '.$e->getMessage()];
                $order->save();
            }
        }

        $this->info("Done. Synced: {$synced} orders.");
        return 0;
    }

    private function dispatchPendingSmmWithoutRemoteId(int $limit): int
    {
        /** @var OrderDispatcher $dispatcher */
        $dispatcher = app(OrderDispatcher::class);

        $pending = SmmOrder::query()
            ->where('api_order', 1)
            ->where('status', 'waiting')
            ->where(function ($q) {
                $q->whereNull('remote_id')->orWhere('remote_id', '');
            })
            ->where(function ($q) {
                $q->whereNull('processing')->orWhere('processing', 0)->orWhere('processing', false);
            })
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        $attempted = $pending->count();
        $sent = 0;
        $failed = 0;

        foreach ($pending as $order) {
            try {
                $dispatcher->send('smm', (int)$order->id);
            } catch (\Throwable $e) {
                Log::warning('SyncSmmOrders pre-dispatch failed', [
                    'order_id' => $order->id,
                    'err' => $e->getMessage(),
                ]);
            }

            $order->refresh();
            if (trim((string)$order->remote_id) !== '') {
                $sent++;
                continue;
            }

            $failed++;
            $reason = $this->extractDispatchFailureReason($order);

            $this->warn("Pre-dispatch failed for order #{$order->id}: {$reason}");

            Log::warning('SyncSmmOrders pre-dispatch no remote_id after dispatch', [
                'order_id' => $order->id,
                'status' => $order->status,
                'reason' => $reason,
                'response' => $order->response,
                'request' => $order->request,
            ]);
        }

        if ($attempted > 0) {
            $this->info("Pre-dispatch SMM queue: attempted={$attempted} sent={$sent} failed={$failed}.");
        }

        return $sent;
    }

    private function extractDispatchFailureReason(SmmOrder $order): string
    {
        $response = $this->normalizeResponseArray($order->response);
        $request = $this->normalizeResponseArray($order->request);

        $candidates = [
            data_get($response, 'message'),
            data_get($response, 'result_text'),
            data_get($request, 'dispatch_error'),
            data_get($request, 'response_raw.message'),
            data_get($request, 'response_raw.raw'),
        ];

        foreach ($candidates as $msg) {
            $msg = trim((string)$msg);
            if ($msg !== '') return $msg;
        }

        return 'Unknown reason (remote_id still empty after dispatch).';
    }

    private function normalizeResponseArray($value): array
    {
        if (is_array($value)) return $value;

        if (is_string($value)) {
            $s = trim($value);
            if ($s !== '') {
                $j = json_decode($s, true);
                if (is_array($j)) return $j;
                return ['raw' => $value];
            }
        }

        return [];
    }
}