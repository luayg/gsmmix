<?php

namespace App\Console\Commands;

use App\Models\ApiProvider;
use App\Models\ImeiOrder;
use App\Models\User;
use App\Services\Orders\DhruOrderGateway;
use Illuminate\Console\Command;

class SyncImeiOrders extends Command
{
    protected $signature = 'orders:sync-imei {--limit=50}';
    protected $description = 'Sync IMEI orders status/result using DHRU getimeiorder';

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

    private function refundIfNeeded(ImeiOrder $order, string $reason = 'rejected'): void
    {
        try {
            $req = (array)($order->request ?? []);
            if (!empty($req['refunded_at'])) return;

            $userId = (int)($order->user_id ?? 0);
            if ($userId <= 0) return;

            $amount = (float)($order->price ?? 0);
            if ($amount <= 0) {
                $req['refunded_at'] = now()->toDateTimeString();
                $req['refund_amount'] = 0;
                $req['refund_reason'] = $reason;
                $order->request = $req;
                $order->save();
                return;
            }

            \DB::transaction(function () use ($order, $userId, $amount, $reason) {
                $u = User::query()->lockForUpdate()->find($userId);
                if (!$u) return;

                $u->balance = (float)($u->balance ?? 0) + $amount;
                $u->save();

                $req = (array)($order->request ?? []);
                $req['refunded_at'] = now()->toDateTimeString();
                $req['refund_amount'] = $amount;
                $req['refund_reason'] = $reason;

                $order->request = $req;
                $order->save();
            });
        } catch (\Throwable $e) {
            // لا نوقف السينك
        }
    }

    public function handle(DhruOrderGateway $dhru): int
    {
        $limit = (int)$this->option('limit');

        $orders = ImeiOrder::query()
            ->where('api_order', 1)
            ->whereIn('status', ['waiting','inprogress'])
            ->whereNotNull('remote_id')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $this->info("Syncing {$orders->count()} IMEI orders...");

        foreach ($orders as $order) {
            $order->load(['service','provider']);

            $providerId = (int)($order->supplier_id ?? $order->service?->supplier_id ?? 0);
            $provider = $providerId ? ApiProvider::find($providerId) : null;

            if (!$provider || (int)$provider->active !== 1) {
                continue;
            }

            $ref = (string)$order->remote_id;
            if ($ref === '') continue;

            $res = $dhru->getImeiOrder($provider, $ref);

            $order->request = array_merge((array)$order->request, [
                'last_status_check' => now()->toDateTimeString(),
                'status_check_raw'  => $res['response_raw'] ?? null,
            ]);

            $raw = $res['response_raw'] ?? null;

            if (!is_array($raw)) {
                $order->save();
                continue;
            }

            // ERROR برسائل تقنية => لا نرفض
            if (isset($raw['ERROR'][0]['MESSAGE'])) {
                $m = strtolower((string)$raw['ERROR'][0]['MESSAGE']);
                if (
                    str_contains($m, 'command not found') ||
                    str_contains($m, 'invalid action') ||
                    (str_contains($m, 'parameter') && str_contains($m, 'required'))
                ) {
                    $order->response = [
                        'type' => 'info',
                        'message' => $raw['ERROR'][0]['MESSAGE'],
                        'reference_id' => $order->remote_id,
                    ];
                    $order->save();
                    continue;
                }

                // ✅ rejected نهائي => refund
                $order->status = 'rejected';
                $order->replied_at = now();
                $order->response = [
                    'type' => 'error',
                    'message' => $raw['ERROR'][0]['MESSAGE'],
                    'reference_id' => $order->remote_id,
                ];
                $order->save();

                $this->refundIfNeeded($order, 'sync_error_rejected');
                continue;
            }

            if (isset($raw['SUCCESS'][0])) {
                $s0 = $raw['SUCCESS'][0];

                $statusNum = (int)($s0['STATUS'] ?? -1);
                $code = $s0['CODE'] ?? null;

                if ($statusNum === 0 || $statusNum === 1) {
                    $order->status = 'inprogress';
                } elseif ($statusNum === 3) {
                    $order->status = 'rejected';
                    $order->replied_at = now();
                } elseif ($statusNum === 4) {
                    $order->status = 'success';
                    $order->replied_at = now();
                }

                $ui = [
                    'type' => ($order->status === 'rejected') ? 'error' : (($order->status === 'success') ? 'success' : 'info'),
                    'message' => $order->status === 'success'
                        ? 'Result available'
                        : ($order->status === 'rejected' ? 'Rejected' : 'In progress'),
                    'reference_id' => $order->remote_id,
                ];

                if (!empty($code)) {
                    $rawResult = is_string($code)
                        ? $code
                        : json_encode($code, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

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

                // ✅ rejected نهائي من STATUS=3 => refund
                if ($order->status === 'rejected') {
                    $this->refundIfNeeded($order, 'sync_status_rejected');
                }

                continue;
            }

            $order->save();
        }

        $this->info("Done.");
        return 0;
    }
}
