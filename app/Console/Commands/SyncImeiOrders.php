<?php

namespace App\Console\Commands;

use App\Models\ApiProvider;
use App\Models\ImeiOrder;
use App\Services\Orders\DhruOrderGateway;
use App\Services\Orders\OrderFinanceService;
use App\Services\Orders\UnlockbaseOrderGateway;
use App\Services\Orders\WebxOrderGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncImeiOrders extends Command
{
    protected $signature = 'orders:sync-imei {--limit=50} {--only-id=} {--include-final}';
    protected $description = 'Sync IMEI orders status/result from providers (DHRU/WebX/UnlockBase/GSMHub)';

    private function finance(): OrderFinanceService
    {
        return app(OrderFinanceService::class);
    }

    private function providerBaseUrl(ApiProvider $p): string
    {
        $u = rtrim((string)$p->url, '/');
        $u = preg_replace('~/api/index\.php$~', '', $u) ?? $u;
        return rtrim($u, '/');
    }

    private function resolveImageUrl(ApiProvider $p, string $src): ?string
    {
        $src = trim($src);
        if ($src === '') return null;
        if (str_starts_with($src, 'data:image/')) return $src;
        if (str_starts_with($src, '//')) return 'https:' . $src;
        if (preg_match('~^https?://~i', $src)) return $src;

        $base = $this->providerBaseUrl($p);
        if ($base === '') return null;

        if (str_starts_with($src, '/')) return $base . $src;
        return $base . '/' . $src;
    }

    private function extractFirstImgSrc(string $html): ?string
    {
        if (!preg_match('~<img[^>]+src=["\']([^"\']+)["\']~i', $html, $m)) {
            return null;
        }
        return $m[1] ?? null;
    }

    private function cleanHtmlResultToLines(string $html): array
    {
        $html = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", " ", $text) ?? $text;

        $lines = array_map('trim', explode("\n", $text));
        $lines = array_values(array_filter($lines, fn($l) => $l !== ''));
        return $lines;
    }

    private function linesToKeyValue(array $lines): array
    {
        $items = [];
        foreach ($lines as $line) {
            $pos = mb_strpos($line, ':');
            if ($pos !== false) {
                $key = trim(mb_substr($line, 0, $pos));
                $val = trim(mb_substr($line, $pos + 1));
                if ($key !== '' && $val !== '') {
                    $items[] = ['label' => $key, 'value' => $val];
                    continue;
                }
            }
            $items[] = ['label' => '', 'value' => $line];
        }
        return $items;
    }

    private function normalizeArr($value): array
    {
        if (is_array($value)) return $value;

        if (is_string($value)) {
            $s = trim($value);
            if ($s !== '') {
                $decoded = json_decode($s, true);
                if (is_array($decoded)) return $decoded;
                return ['raw' => $value];
            }
        }
        return [];
    }

    private function mapDhruStatusToLocal(int $statusInt): string
    {
        if ($statusInt === 4) return 'success';
        if ($statusInt === 3) return 'rejected';
        if (in_array($statusInt, [0, 1, 2], true)) return 'inprogress';
        return 'inprogress';
    }

    private function normalizeGatewayStatus(string $st): string
    {
        $st = strtolower(trim($st));
        if (in_array($st, ['success', 'rejected', 'cancelled', 'canceled', 'inprogress', 'waiting'], true)) {
            return $st === 'canceled' ? 'cancelled' : $st;
        }
        return 'inprogress';
    }

    private function pickGatewayType(ApiProvider $provider): string
    {
        return strtolower(trim((string)($provider->type ?? 'dhru')));
    }

    public function handle(
        DhruOrderGateway $dhru,
        WebxOrderGateway $webx,
        UnlockbaseOrderGateway $unlockbase
    ): int {
        $limit = (int)$this->option('limit');
        if ($limit < 1) $limit = 50;
        if ($limit > 500) $limit = 500;

        $onlyId = $this->option('only-id');
        $includeFinal = (bool)$this->option('include-final');

        $q = ImeiOrder::query()
            ->where('api_order', 1)
            ->whereNotNull('remote_id');

        if (!empty($onlyId)) {
            $q->where('id', (int)$onlyId);
        } elseif (!$includeFinal) {
            $q->whereIn('status', ['waiting', 'inprogress']);
        }

        $q->orderBy('id', 'asc');

        $orders = $q->limit($limit)->get();

        if ($orders->isEmpty()) {
            $this->info('No IMEI orders to sync.');
            return 0;
        }

        $this->info("Syncing {$orders->count()} IMEI orders...");

        $synced = 0;

        foreach ($orders as $order) {
            try {
                $order->load(['service', 'provider']);

                $providerId = (int)($order->supplier_id ?? $order->service?->supplier_id ?? 0);
                $provider = $providerId ? ApiProvider::find($providerId) : null;

                if (!$provider || (int)$provider->active !== 1) continue;

                $ref = trim((string)$order->remote_id);
                if ($ref === '') continue;

                $ptype = $this->pickGatewayType($provider);

                $res = match ($ptype) {
                    'unlockbase' => $unlockbase->getImeiOrder($provider, $ref),
                    'gsmhub'     => app(\App\Services\Orders\GsmhubOrderGateway::class)->getImeiOrder($provider, $ref),
                    'webx'       => $webx->getImeiOrder($provider, $ref),
                    default      => $dhru->getImeiOrder($provider, $ref),
                };

                $req = $this->normalizeArr($order->request ?? []);
                $req['last_status_check'] = now()->toDateTimeString();
                $req['status_check_raw']  = $res['response_raw'] ?? null;
                $order->request = $req;

                if (!is_array($res)) {
                    $order->status = 'waiting';
                    $order->processing = 0;
                    $order->save();
                    continue;
                }

                if ($ptype !== 'dhru') {
                    $newStatus = $this->normalizeGatewayStatus((string)($res['status'] ?? 'inprogress'));

                    if ($newStatus === 'waiting' && !empty($order->remote_id)) {
                        $newStatus = 'inprogress';
                    }

                    $order->status = $newStatus;
                    $final = in_array($newStatus, ['success', 'rejected', 'cancelled'], true);
                    $order->processing = $final ? 0 : 1;
                    if ($final) {
                        $order->replied_at = $order->replied_at ?: now();
                    }

                    $ui = is_array($res['response_ui'] ?? null) ? $res['response_ui'] : [];
                    $ui['reference_id']  = $order->remote_id;
                    $ui['provider_type'] = $ptype;

                    $rt = (string)($ui['result_text'] ?? '');
                    if ($rt !== '') {
                        $imgSrc = $this->extractFirstImgSrc($rt);
                        if ($imgSrc) {
                            $imgUrl = $this->resolveImageUrl($provider, $imgSrc);
                            if ($imgUrl) $ui['result_image'] = $imgUrl;
                        }
                        $lines = $this->cleanHtmlResultToLines($rt);
                        $ui['result_items'] = $ui['result_items'] ?? $this->linesToKeyValue($lines);
                    }

                    $order->response = $ui;
                    $order->save();

                    if (in_array($newStatus, ['rejected', 'cancelled'], true)) {
                        $this->finance()->refundOrderIfNeeded($order, 'sync_' . $newStatus . '_non_dhru');
                    }

                    $synced++;
                    continue;
                }

                $raw = $res['response_raw'] ?? null;

                if (!is_array($raw)) {
                    $order->status = 'waiting';
                    $order->processing = 0;
                    $order->save();
                    continue;
                }

                if (isset($raw['ERROR'][0]['MESSAGE'])) {
                    $msg = (string)$raw['ERROR'][0]['MESSAGE'];
                    $m = strtolower($msg);

                    if (
                        str_contains($m, 'command not found') ||
                        str_contains($m, 'invalid action') ||
                        (str_contains($m, 'parameter') && str_contains($m, 'required'))
                    ) {
                        $order->response = [
                            'type' => 'info',
                            'message' => $msg,
                            'reference_id' => $order->remote_id,
                        ];
                        $order->status = 'inprogress';
                        $order->processing = 1;
                        $order->save();
                        continue;
                    }

                    $order->status = 'rejected';
                    $order->processing = 0;
                    $order->replied_at = now();
                    $order->response = [
                        'type' => 'error',
                        'message' => $msg,
                        'reference_id' => $order->remote_id,
                    ];
                    $order->save();

                    $this->finance()->refundOrderIfNeeded($order, 'sync_rejected_error');
                    $synced++;
                    continue;
                }

                if (isset($raw['SUCCESS'][0]) && is_array($raw['SUCCESS'][0])) {
                    $s0 = $raw['SUCCESS'][0];

                    $statusInt = (int)($s0['STATUS'] ?? -1);
                    $code = $s0['CODE'] ?? null;

                    $newStatus = $this->mapDhruStatusToLocal($statusInt);

                    if ($newStatus === 'waiting' && !empty($order->remote_id)) {
                        $newStatus = 'inprogress';
                    }

                    $order->status = $newStatus;
                    $order->processing = in_array($newStatus, ['success', 'rejected', 'cancelled'], true) ? 0 : 1;

                    if (in_array($newStatus, ['success', 'rejected', 'cancelled'], true)) {
                        $order->replied_at = $order->replied_at ?: now();
                    }

                    $ui = [
                        'type' => ($newStatus === 'rejected') ? 'error' : (($newStatus === 'success') ? 'success' : 'info'),
                        'message' => ($newStatus === 'success')
                            ? 'Result available'
                            : (($newStatus === 'rejected') ? 'Rejected' : 'In progress'),
                        'reference_id' => $order->remote_id,
                        'dhru_status' => $statusInt,
                    ];

                    if (!empty($code)) {
                        $rawResult = is_string($code)
                            ? $code
                            : json_encode($code, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                        $imgSrc = $this->extractFirstImgSrc($rawResult);
                        if ($imgSrc) {
                            $imgUrl = $this->resolveImageUrl($provider, $imgSrc);
                            if ($imgUrl) $ui['result_image'] = $imgUrl;
                        }

                        $lines = $this->cleanHtmlResultToLines($rawResult);
                        $items = $this->linesToKeyValue($lines);

                        $ui['result_text']  = implode("\n", $lines);
                        $ui['result_items'] = $items;
                    }

                    $order->response = $ui;
                    $order->save();

                    if (in_array($newStatus, ['rejected', 'cancelled'], true)) {
                        $this->finance()->refundOrderIfNeeded($order, 'sync_' . $newStatus . '_status');
                    }

                    $synced++;
                    continue;
                }

                $order->status = 'inprogress';
                $order->processing = 1;
                $order->save();

            } catch (\Throwable $e) {
                Log::error('SyncImeiOrders error', [
                    'order_id' => $order->id ?? null,
                    'err' => $e->getMessage(),
                ]);

                $order->status = 'waiting';
                $order->processing = 0;
                $order->response = ['type' => 'queued', 'message' => 'Sync error, will retry: ' . $e->getMessage()];
                $order->save();
            }
        }

        $this->info("Done. Synced: {$synced} orders.");
        return 0;
    }
}