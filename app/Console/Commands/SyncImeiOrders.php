<?php

namespace App\Console\Commands;

use App\Models\ApiProvider;
use App\Models\ImeiOrder;
use App\Services\Orders\DhruOrderGateway;
use Illuminate\Console\Command;

class SyncImeiOrders extends Command
{
    protected $signature = 'orders:sync-imei {--limit=50}';
    protected $description = 'Sync IMEI orders status/result using DHRU getimeiorder';

    private function providerBaseUrl(ApiProvider $p): string
    {
        $u = rtrim((string)$p->url, '/');
        // لو كان مخزن api/index.php احذفه للحصول على base
        $u = preg_replace('~/api/index\.php$~', '', $u) ?? $u;
        return rtrim($u, '/');
    }

    private function resolveImageUrl(ApiProvider $p, string $src): ?string
    {
        $src = trim($src);
        if ($src === '') return null;

        // data URI
        if (str_starts_with($src, 'data:image/')) return $src;

        // protocol-relative
        if (str_starts_with($src, '//')) return 'https:' . $src;

        // absolute
        if (preg_match('~^https?://~i', $src)) return $src;

        // relative => ركّبه على base
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
        // بدّل <br> إلى \n
        $html = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $html) ?? $html;

        // احذف كل الوسوم (span/img/..)
        $text = strip_tags($html);

        // decode entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // نظّف المسافات
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

                $order->status = 'rejected';
                $order->replied_at = now();
                $order->response = [
                    'type' => 'error',
                    'message' => $raw['ERROR'][0]['MESSAGE'],
                    'reference_id' => $order->remote_id,
                ];
                $order->save();
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

                    // ✅ استخراج صورة إن وجدت
                    $imgSrc = $this->extractFirstImgSrc($rawResult);
                    if ($imgSrc) {
                        $imgUrl = $this->resolveImageUrl($provider, $imgSrc);
                        if ($imgUrl) $ui['result_image'] = $imgUrl;
                    }

                    // ✅ تنظيف وتحويل لجدول
                    $lines = $this->cleanHtmlResultToLines($rawResult);
                    $items = $this->linesToKeyValue($lines);

                    $ui['result_text']  = implode("\n", $lines);
                    $ui['result_items'] = $items;
                }

                $order->response = $ui;
                $order->save();
                continue;
            }

            $order->save();
        }

        $this->info("Done.");
        return 0;
    }
}
