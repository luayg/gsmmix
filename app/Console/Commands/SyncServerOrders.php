<?php

namespace App\Console\Commands;

use App\Models\ApiProvider;
use App\Models\ServerOrder;
use App\Models\User;
use App\Services\Orders\DhruOrderGateway;
use App\Services\Orders\OrderDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncServerOrders extends Command
{
    protected $signature = 'orders:sync-server {--limit=50} {--only-id=}';
    protected $description = 'Sync Server Orders status/result from provider (DHRU)';

    public function handle(): int
    {
        $limit = (int)$this->option('limit');
        if ($limit < 1) $limit = 50;
        if ($limit > 500) $limit = 500;

        $onlyId = $this->option('only-id');

        // ✅ First: auto-dispatch waiting server orders that were never sent (no remote_id)
        // so this command can be used alone in cron/manual runs.
        $preDispatch = ['attempted' => 0, 'sent' => 0, 'failed' => 0];
        if (empty($onlyId)) {
            $preDispatch = $this->dispatchPendingServerWithoutRemoteId($limit);
            if (($preDispatch['attempted'] ?? 0) > 0) {
                $this->info(sprintf(
                    'Pre-dispatch server queue: attempted=%d sent=%d failed=%d.',
                    (int)($preDispatch['attempted'] ?? 0),
                    (int)($preDispatch['sent'] ?? 0),
                    (int)($preDispatch['failed'] ?? 0)
                ));
            }
        }

        $q = ServerOrder::query()
            ->with(['service', 'provider'])
            ->where('api_order', 1)
            ->whereNotNull('remote_id')
            ->whereIn('status', ['waiting', 'inprogress']);

        if (!empty($onlyId)) {
            $q->where('id', (int)$onlyId);
        }

        $orders = $q->orderBy('id', 'asc')->limit($limit)->get();

        if ($orders->isEmpty()) {
            $pendingDispatch = ServerOrder::query()
                ->where('api_order', 1)
                ->where('status', 'waiting')
                ->where(function ($q) {
                    $q->whereNull('remote_id')->orWhere('remote_id', '');
                })
                ->count();

            if ($pendingDispatch > 0) {
                $attempted = (int)($preDispatch['attempted'] ?? 0);
                if ($attempted > 0) {
                    $this->warn("No server orders to sync yet. {$pendingDispatch} order(s) are still waiting without remote_id after dispatch attempt.");
                    $this->warn('This usually means provider/API validation/connection issue. Check each order response/request payload.');
                } else {
                    $this->info("No server orders to sync (remote_id missing). Pending dispatch queue: {$pendingDispatch}.");
                    $this->info('Run: php artisan orders:dispatch-pending-server --limit=50');
                }
            } else {
                $this->info('No server orders to sync.');
            }
            return 0;
        }

        /** @var DhruOrderGateway $gw */
        $gw = app(DhruOrderGateway::class);

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

                $res = $gw->getServerOrder($provider, $ref);

                // ✅ store last check + raw always
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

                $raw = $res['response_raw'] ?? $res;

                [$statusInt, $code, $comments] = $this->extractDhruStatusCodeComments($raw);

                // إذا status 3/4 لكن code فاضي => اعتبره 1 (in progress)
                if (in_array($statusInt, [3, 4], true) && trim($code) === '') {
                    $statusInt = 1;
                }

                $newStatus = $this->mapDhruStatusToLocal($statusInt);

                $respArr = $this->normalizeResponseArray($order->response);
                $respArr['dhru_status']   = $statusInt;
                $respArr['dhru_comments'] = $comments;
                $respArr['result_text']   = $code;

                $providerHtml = $this->asProviderReplyHtml($code, $comments);
                if ($providerHtml !== '') {
                    $respArr['provider_reply_html'] = $providerHtml;
                    $respArr['provider_reply_updated_at'] = now()->toDateTimeString();
                } else {
                    $respArr['message'] = trim($comments) !== '' ? trim($comments) : (trim($code) !== '' ? trim($code) : '—');
                }

                $final = in_array($newStatus, ['success','rejected','cancelled'], true);

                $order->status = $newStatus;
                $order->processing = $final ? 0 : 1;
                if ($final) {
                    $order->replied_at = $order->replied_at ?: now();
                }

                // keep raw in request for debugging
                $req = $this->normalizeResponseArray($order->request);
                $req['sync_last_at'] = now()->toDateTimeString();
                $req['sync_raw'] = $raw;
                $order->request = $req;

                $order->response = $respArr;
                $order->save();

                if ($newStatus === 'rejected') {
                    $this->refundIfNeeded($order, 'api_rejected');
                }

                $synced++;
                $this->info("Order #{$order->id} synced => {$newStatus} (DHRU {$statusInt})");

            } catch (\Throwable $e) {
                Log::error('SyncServerOrders error', [
                    'order_id' => $order->id ?? null,
                    'err' => $e->getMessage(),
                ]);
                $this->warn("Order #{$order->id}: error => " . $e->getMessage());

                // do not reject on sync crash
                $order->status = 'waiting';
                $order->processing = 0;
                $order->response = ['type'=>'queued','message'=>'Sync error, will retry: '.$e->getMessage()];
                $order->save();
            }
        }

        $this->info("Done. Synced: {$synced} orders.");
        return 0;
    }

    private function dispatchPendingServerWithoutRemoteId(int $limit): array
    {
        /** @var OrderDispatcher $dispatcher */
        $dispatcher = app(OrderDispatcher::class);

        $pending = ServerOrder::query()
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

        $attempted = 0;
        $sent = 0;
        $failed = 0;

        foreach ($pending as $order) {
            $attempted++;
            try {
                $dispatcher->send('server', (int)$order->id);
                $fresh = ServerOrder::query()->find($order->id);
                $rid = trim((string)($fresh?->remote_id ?? ''));
                if ($rid !== '') $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('SyncServerOrders pre-dispatch failed', [
                    'order_id' => $order->id,
                    'err' => $e->getMessage(),
                ]);
            }
        }

        return [
            'attempted' => $attempted,
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    private function mapDhruStatusToLocal(int $statusInt): string
    {
        if ($statusInt === 4) return 'success';
        if ($statusInt === 3) return 'rejected';
        if (in_array($statusInt, [0,1,2], true)) return 'inprogress';
        return 'inprogress';
    }

    private function extractDhruStatusCodeComments($raw): array
    {
        if (is_string($raw)) {
            $j = json_decode($raw, true);
            if (is_array($j)) $raw = $j;
        }

        if (!is_array($raw)) return [1, '', ''];

        $status = 1;
        $code = '';
        $comments = '';

        if (isset($raw['SUCCESS'][0]) && is_array($raw['SUCCESS'][0])) {
            $s0 = $raw['SUCCESS'][0];
            if (isset($s0['STATUS'])) $status = (int)$s0['STATUS'];
            if (isset($s0['CODE'])) $code = (string)$s0['CODE'];
            if (isset($s0['COMMENTS'])) $comments = (string)$s0['COMMENTS'];
            return [$status, $code, $comments];
        }

        if (isset($raw['ERROR'][0]) && is_array($raw['ERROR'][0])) {
            $e0 = $raw['ERROR'][0];
            $comments = (string)($e0['MESSAGE'] ?? 'Provider error');
            return [1, '', $comments];
        }

        return [$status, $code, $comments];
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

    private function asProviderReplyHtml(string $code, string $comments): string
    {
        $code = trim((string)$code);
        $comments = trim((string)$comments);

        if ($code !== '' && (stripos($code, '<table') !== false || stripos($code, '<br') !== false || stripos($code, '<p') !== false)) {
            return $code;
        }

        $text = $code !== '' ? $code : $comments;
        $text = trim($text);
        if ($text === '') return '';

        $safe = e($text);
        return '<div style="white-space:pre-wrap;">' . $safe . '</div>';
    }

    private function refundIfNeeded(ServerOrder $order, string $reason): void
    {
        $req = $this->normalizeResponseArray($order->request);

        if (!empty($req['refunded_at'])) return;

        $uid = (int)($order->user_id ?? 0);
        if ($uid <= 0) return;

        $amount = (float)($req['charged_amount'] ?? 0);
        if ($amount <= 0) return;

        DB::transaction(function () use ($order, $uid, $amount, $reason, $req) {
            $u = User::query()->lockForUpdate()->find($uid);
            if (!$u) return;

            $u->balance = (float)($u->balance ?? 0) + $amount;
            $u->save();

            $req['refunded_at'] = now()->toDateTimeString();
            $req['refunded_amount'] = $amount;
            $req['refunded_reason'] = $reason;

            $order->request = $req;
            $order->save();
        });
    }
}
