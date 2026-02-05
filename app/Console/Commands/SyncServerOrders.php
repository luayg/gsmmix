<?php

namespace App\Console\Commands;

use App\Models\ApiProvider;
use App\Models\ServerOrder;
use App\Models\User;
use App\Services\Orders\DhruOrderGateway;
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
            $this->info('No server orders to sync.');
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

                $ref = (string)$order->remote_id;
                if (trim($ref) === '') {
                    $this->warn("Order #{$order->id}: remote_id empty.");
                    continue;
                }

                // ✅ Requires DhruOrderGateway::getServerOrder($provider, $ref)
                $res = $gw->getServerOrder($provider, $ref);

                // لو الاتصال فشل (queued) نتركها
                if (!is_array($res)) {
                    $this->warn("Order #{$order->id}: invalid gateway response.");
                    continue;
                }

                $raw = $res['response_raw'] ?? $res;
                [$statusInt, $code, $comments] = $this->extractDhruStatusCodeComments($raw);

                // نفس منطق ملفاتك: إذا status 3/4 لكن code فاضي => اعتبره 1 (in progress)
                if (in_array($statusInt, [3, 4], true) && trim($code) === '') {
                    $statusInt = 1;
                }

                $newStatus = $this->mapDhruStatusToLocal($statusInt);

                // ✅ build response payload
                $respArr = $this->normalizeResponseArray($order->response);

                $respArr['dhru_status'] = $statusInt;
                $respArr['dhru_comments'] = $comments;
                $respArr['result_text'] = $code;

                // لو CODE يحتوي HTML نخزنه كـ provider_reply_html مباشرة ليظهر عندك في edit/view
                $providerHtml = $this->asProviderReplyHtml($code, $comments);
                if ($providerHtml !== '') {
                    $respArr['provider_reply_html'] = $providerHtml;
                    $respArr['provider_reply_updated_at'] = now()->toDateTimeString();
                } else {
                    // fallback رسالة
                    $respArr['message'] = trim($comments) !== '' ? trim($comments) : (trim($code) !== '' ? trim($code) : '—');
                }

                // ✅ update order
                $final = in_array($newStatus, ['success','rejected','cancelled'], true);

                $order->status = $newStatus;
                $order->processing = $final ? 0 : 1;

                if ($final) {
                    $order->replied_at = $order->replied_at ?: now();
                }

                // نخزن request/response الخام أيضًا للمراجعة
                $req = $this->normalizeResponseArray($order->request);
                $req['sync_last_at'] = now()->toDateTimeString();
                $req['sync_raw'] = $raw;

                $order->request = $req;
                $order->response = $respArr;

                $order->save();

                // ✅ refund if rejected
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
                $this->warn("Order #{$order->id}: error => ".$e->getMessage());
            }
        }

        $this->info("Done. Synced: {$synced} orders.");
        return 0;
    }

    private function mapDhruStatusToLocal(int $statusInt): string
    {
        // الأكثر شيوعًا في DHRU:
        // 0: pending/waiting, 1: in progress, 3: rejected, 4: success
        if ($statusInt === 4) return 'success';
        if ($statusInt === 3) return 'rejected';
        if (in_array($statusInt, [0,1,2], true)) return 'inprogress';

        // unknown => keep inprogress to avoid wrong finalization
        return 'inprogress';
    }

    private function extractDhruStatusCodeComments($raw): array
    {
        // raw expected example:
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
            // اعتبره inprogress (ما نخرب الطلب)
            return [1, '', $comments];
        }

        // fallback
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

        // إذا كان HTML واضح
        if ($code !== '' && (stripos($code, '<table') !== false || stripos($code, '<br') !== false || stripos($code, '<p') !== false)) {
            return $code;
        }

        // إذا نص فقط: نعرضه داخل div بسيط
        $text = $code !== '' ? $code : $comments;
        $text = trim($text);
        if ($text === '') return '';

        $safe = e($text);
        return '<div style="white-space:pre-wrap;">'.$safe.'</div>';
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
