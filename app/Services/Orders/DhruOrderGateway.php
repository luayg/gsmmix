<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DhruOrderGateway
{
    private function endpoint(ApiProvider $p): string
    {
        $base = rtrim((string)$p->url, '/');
        if (str_ends_with($base, 'api/index.php')) return $base;
        return $base . '/api/index.php';
    }

    private function basePayload(ApiProvider $p, string $action): array
    {
        return [
            'username'      => (string)($p->username ?? ''),
            'apiaccesskey'  => (string)($p->api_key ?? ''),
            'requestformat' => 'JSON',
            'action'        => $action,
        ];
    }

    private function xmlEscape(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function normalizeUi(array $json): array
    {
        if (isset($json['SUCCESS'][0])) {
            $s0 = $json['SUCCESS'][0];
            return [
                'type' => 'success',
                'message' => $s0['MESSAGE'] ?? 'OK',
                'reference_id' => $s0['REFERENCEID'] ?? null,
            ];
        }

        if (isset($json['ERROR'][0])) {
            $e0 = $json['ERROR'][0];
            return [
                'type' => 'error',
                'message' => $e0['MESSAGE'] ?? 'Error',
            ];
        }

        return ['type' => 'unknown', 'message' => 'Unknown response'];
    }

    private function isRetryableErrorMessage(?string $msg): bool
    {
        if ($msg === null) return false;
        $m = mb_strtolower(trim($msg));

        $needles = [
            'ip', 'not allowed', 'whitelist', 'blocked', 'forbidden',
            'timeout', 'timed out',
            'connection', 'connect', 'refused', 'reset',
            'temporarily', 'temporary', 'try again',
            'maintenance', 'unavailable', 'service unavailable',
            'server busy', 'busy',
            'too many requests', 'rate limit', 'limit exceeded',
            'bad gateway', 'gateway', 'cloudflare',
            'dns', 'resolve', 'host',
        ];

        foreach ($needles as $n) {
            if (str_contains($m, $n)) return true;
        }

        return false;
    }

    private function send(ApiProvider $p, string $action, string $parametersXml): array
    {
        $payload = $this->basePayload($p, $action);
        $payload['parameters'] = $parametersXml;

        $url = $this->endpoint($p);

        try {
            $resp = Http::asForm()->timeout(60)->post($url, $payload);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => ['url'=>$url,'payload'=>$payload,'exception'=>$e->getMessage()],
                'response_raw' => ['error'=>'connection_failed','message'=>$e->getMessage()],
                'response_ui' => ['type'=>'queued','message'=>'Provider unreachable, queued.'],
            ];
        }

        $raw = (string)$resp->body();
        $json = json_decode($raw, true);

        if (!$resp->successful() || !is_array($json)) {
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => ['url'=>$url,'payload'=>$payload,'http_status'=>$resp->status()],
                'response_raw' => is_array($json) ? $json : ['raw'=>$raw,'http_status'=>$resp->status()],
                'response_ui' => ['type'=>'queued','message'=>'Temporary provider error, queued.'],
            ];
        }

        if (isset($json['SUCCESS'][0])) {
            $s0 = $json['SUCCESS'][0];
            $ref = $s0['REFERENCEID'] ?? null;

            return [
                'ok' => true,
                'retryable' => false,
                'status' => 'inprogress',
                'remote_id' => $ref,
                'request' => ['url'=>$url,'payload'=>$payload,'http_status'=>$resp->status()],
                'response_raw' => $json,
                'response_ui' => $this->normalizeUi($json),
            ];
        }

        $errMsg = null;
        if (isset($json['ERROR'][0]['MESSAGE'])) {
            $errMsg = (string)$json['ERROR'][0]['MESSAGE'];
        }

        if ($this->isRetryableErrorMessage($errMsg)) {
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => ['url'=>$url,'payload'=>$payload,'http_status'=>$resp->status()],
                'response_raw' => $json,
                'response_ui' => [
                    'type' => 'queued',
                    'message' => $errMsg ? ('Temporary provider issue: '.$errMsg) : 'Temporary provider issue, queued.',
                ],
            ];
        }

        return [
            'ok' => false,
            'retryable' => false,
            'status' => 'rejected',
            'remote_id' => null,
            'request' => ['url'=>$url,'payload'=>$payload,'http_status'=>$resp->status()],
            'response_raw' => $json,
            'response_ui' => $this->normalizeUi($json),
        ];
    }

    // =========================
    // PLACE ORDERS
    // =========================

    /**
     * ✅ UPDATED:
     * placeImeiOrder now supports REQUIRED fields (same as server)
     * fields source: order->params['fields'] OR legacy order->params['required']
     */
    public function placeImeiOrder(ApiProvider $p, ImeiOrder $order): array
    {
        $serviceId = (string)($order->service?->remote_id ?? '');
        $imei = (string)($order->device ?? '');

        $fields = [];
        if (is_array($order->params)) {
            $fields = $order->params['fields'] ?? $order->params['required'] ?? [];
        }
        if (!is_array($fields)) $fields = [];

        $xml = '<PARAMETERS>'
            . '<IMEI>'.$this->xmlEscape($imei).'</IMEI>'
            . '<ID>'.$this->xmlEscape($serviceId).'</ID>';

        // ✅ include REQUIRED only if not empty
        if (!empty($fields)) {
            $requiredJson = json_encode($fields, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $xml .= '<REQUIRED>'.$this->xmlEscape((string)$requiredJson).'</REQUIRED>';
        }

        $xml .= '</PARAMETERS>';

        return $this->send($p, 'placeimeiorder', $xml);
    }

    public function placeServerOrder(ApiProvider $p, ServerOrder $order): array
    {
        $serviceId = (string)($order->service?->remote_id ?? '');
        $qty = (int)($order->quantity ?? 1);

        // ✅ fields (preferred) ثم required (legacy)
        $fields = [];
        if (is_array($order->params)) {
            $fields = $order->params['fields'] ?? $order->params['required'] ?? [];
        }
        if (!is_array($fields)) $fields = [];

        $requiredJson = json_encode($fields, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        $comments = trim((string)($order->comments ?? ''));

        $xml = '<PARAMETERS>'
            . '<ID>'.$this->xmlEscape($serviceId).'</ID>'
            . '<QUANTITY>'.$this->xmlEscape((string)$qty).'</QUANTITY>'
            . '<REQUIRED>'.$this->xmlEscape((string)$requiredJson).'</REQUIRED>'
            . '<COMMENTS>'.$this->xmlEscape($comments).'</COMMENTS>'
            . '</PARAMETERS>';

        return $this->send($p, 'placeserverorder', $xml);
    }

    /**
     * ✅ UPDATED:
     * placeFileOrder supports REQUIRED if exists (safe addition)
     */
    public function placeFileOrder(ApiProvider $p, FileOrder $order): array
    {
        $serviceId = (string)($order->service?->remote_id ?? '');
        $path = (string)($order->storage_path ?? '');

        if ($path === '' || !Storage::exists($path)) {
            return [
                'ok'=>false,'retryable'=>false,'status'=>'rejected','remote_id'=>null,
                'request'=>null,
                'response_raw'=>['ERROR'=>[['MESSAGE'=>'storage_path missing or file not found']]],
                'response_ui'=>['type'=>'error','message'=>'File not found on server'],
            ];
        }

        $raw = Storage::get($path);
        $filename = (string)($order->device ?? basename($path));
        $b64 = base64_encode($raw);

        $fields = [];
        if (is_array($order->params)) {
            $fields = $order->params['fields'] ?? $order->params['required'] ?? [];
        }
        if (!is_array($fields)) $fields = [];

        $xml = '<PARAMETERS>'
            . '<ID>'.$this->xmlEscape($serviceId).'</ID>'
            . '<FILENAME>'.$this->xmlEscape($filename).'</FILENAME>'
            . '<FILEDATA>'.$this->xmlEscape($b64).'</FILEDATA>';

        if (!empty($fields)) {
            $requiredJson = json_encode($fields, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $xml .= '<REQUIRED>'.$this->xmlEscape((string)$requiredJson).'</REQUIRED>';
        }

        $xml .= '</PARAMETERS>';

        return $this->send($p, 'placefileorder', $xml);
    }

    // =========================
    // SYNC RESULT / STATUS
    // =========================

    public function getImeiOrder(ApiProvider $p, string $referenceId): array
    {
        $xml = '<PARAMETERS>'
            . '<ID>'.$this->xmlEscape($referenceId).'</ID>'
            . '</PARAMETERS>';

        return $this->send($p, 'getimeiorder', $xml);
    }

    public function getServerOrder(ApiProvider $p, string $referenceId): array
    {
        $xml = '<PARAMETERS>'
            . '<ID>'.$this->xmlEscape($referenceId).'</ID>'
            . '</PARAMETERS>';

        return $this->send($p, 'getserverorder', $xml);
    }

    public function getFileOrder(ApiProvider $p, string $referenceId): array
    {
        $xml = '<PARAMETERS>'
            . '<ID>'.$this->xmlEscape($referenceId).'</ID>'
            . '</PARAMETERS>';

        return $this->send($p, 'getfileorder', $xml);
    }
}
