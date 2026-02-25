<?php

namespace App\Console\Commands;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\User;
use App\Services\Orders\DhruOrderGateway;
use App\Services\Orders\WebxOrderGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncFileOrders extends Command
{
    protected $signature = 'orders:sync-file {--limit=50} {--only-id=}';
    protected $description = 'Sync File Orders status/result from providers (DHRU/WebX)';

    public function handle(DhruOrderGateway $dhru, WebxOrderGateway $webx): int
    {
        $limit = (int)$this->option('limit');
        if ($limit < 1) $limit = 50;
        if ($limit > 500) $limit = 500;

        $onlyId = $this->option('only-id');

        $q = FileOrder::query()
            ->with(['service', 'provider'])
            ->where('api_order', 1)
            ->whereNotNull('remote_id')
            ->whereIn('status', ['waiting', 'inprogress']);

        if (!empty($onlyId)) {
            $q->where('id', (int)$onlyId);
        }

        $orders = $q->orderBy('id', 'asc')->limit($limit)->get();

        if ($orders->isEmpty()) {
            $this->info('No file orders to sync.');
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

                $ptype = strtolower(trim((string)($provider->type ?? 'dhru')));

                // WebX supports file-orders; UnlockBase doesn't => default to DHRU
                $res = match ($ptype) {
                    'webx' => $webx->getFileOrder($provider, $ref),
                    default => $dhru->getFileOrder($provider, $ref),
                };

                // ✅ always store last check + raw
                $req = $this->normalizeResponseArray($order->request);
                $req['last_status_check'] = now()->toDateTimeString();
                $req['status_check_raw']  = $res['response_raw'] ?? null;
                $order->request = $req;

                if (!is_array($res)) {
                    // queued / invalid response => keep waiting
                    $order->status = 'waiting';
                    $order->processing = 0;
                    $order->save();
                    continue;
                }

                // Non-DHRU (WebX) normalized status path
                if ($ptype === 'webx') {
                    $newStatus = strtolower(trim((string)($res['status'] ?? 'inprogress')));
                    if ($newStatus === 'canceled') $newStatus = 'cancelled';
                    if (!in_array($newStatus, ['success','rejected','cancelled','inprogress','waiting'], true)) {
                        $newStatus = 'inprogress';
                    }

                    $respArr = $this->normalizeResponseArray($order->response);
                    $ui = is_array($res['response_ui'] ?? null) ? $res['response_ui'] : [];
                    $respArr = array_merge($respArr, $ui, [
                        'provider_type' => 'webx',
                        'reference_id' => $order->remote_id,
                    ]);

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
                        $this->refundIfNeeded($order, 'api_rejected');
                    }

                    $synced++;
                    $this->info("Order #{$order->id} synced => {$newStatus} (WEBX)");
                    continue;
                }

                // ===== DHRU parsing (existing behavior) =====
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

                // لو CODE يحتوي HTML نخزنه كـ provider_reply_html
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
                Log::error('SyncFileOrders error', [
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

    private function mapDhruStatusToLocal(int $statusInt): string
    {
        // DHRU: 0 pending, 1 inprogress, 3 rejected, 4 success
        if ($statusInt === 4) return 'success';
        if ($statusInt === 3) return 'rejected';
        if (in_array($statusInt, [0,1,2], true)) return 'inprogress';
        return 'inprogress';
    }

    private function extractDhruStatusCodeComments($raw): array
    {
        // raw expected:
        // ['SUCCESS' => [ ['STATUS'=>4,'CODE'=>'...','COMMENTS'=>'...'] ] ]
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
            // اعتبره inprogress حتى لا نخرب الطلب بسبب error response
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

        // HTML واضح
        if ($code !== '' && (
            stripos($code, '<table') !== false ||
            stripos($code, '<br') !== false ||
            stripos($code, '<p') !== false ||
            stripos($code, '<a ') !== false ||
            stripos($code, '<img') !== false
        )) {
            return $code;
        }

        // نص فقط => نعرضه داخل div
        $text = $code !== '' ? $code : $comments;
        $text = trim($text);
        if ($text === '') return '';

        $safe = e($text);
        return '<div style="white-space:pre-wrap;">' . $safe . '</div>';
    }

    private function refundIfNeeded(FileOrder $order, string $reason): void
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