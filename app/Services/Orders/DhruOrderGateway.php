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
    private function endpoint(ApiProvider $provider): string
    {
        $url = rtrim((string) $provider->url, '/');
        return $url . '/api/index.php';
    }

    private function basePayload(ApiProvider $provider, string $action): array
    {
        return [
            'username'      => (string) ($provider->username ?? ''),
            'apiaccesskey'  => (string) ($provider->api_key ?? ''),
            'requestformat' => 'JSON',
            'action'        => $action,
        ];
    }

    private function xmlEscape(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function send(ApiProvider $provider, string $action, string $parametersXml): array
    {
        $payload = $this->basePayload($provider, $action);
        $payload['parameters'] = $parametersXml;

        $url = $this->endpoint($provider);

        $resp = Http::asForm()->timeout(60)->post($url, $payload);

        $raw = (string) $resp->body();
        $json = json_decode($raw, true);

        $result = [
            'ok'        => false,
            'status'    => 'rejected',
            'remote_id' => null,
            'request'   => ['url' => $url, 'payload' => $payload],
            'response'  => is_array($json) ? $json : ['raw' => $raw, 'http_status' => $resp->status()],
        ];

        if (is_array($json) && isset($json['SUCCESS'][0])) {
            $s0 = $json['SUCCESS'][0];
            $ref = $s0['REFERENCEID'] ?? null;

            $result['ok'] = true;
            $result['remote_id'] = $ref;
            $result['status'] = 'inprogress'; // تم الاستلام عند المزوّد
            return $result;
        }

        if (is_array($json) && isset($json['ERROR'][0])) {
            $result['ok'] = false;
            $result['status'] = 'rejected';
            return $result;
        }

        return $result;
    }

    public function placeImeiOrder(ApiProvider $provider, ImeiOrder $order): array
    {
        $serviceRemoteId = (string) ($order->service?->remote_id ?? '');
        $imeiOrSn = (string) ($order->device ?? '');

        $xml = '<PARAMETERS>'
            . '<IMEI>' . $this->xmlEscape($imeiOrSn) . '</IMEI>'
            . '<ID>' . $this->xmlEscape($serviceRemoteId) . '</ID>'
            . '</PARAMETERS>';

        return $this->send($provider, 'placeimeiorder', $xml);
    }

    /**
     * تفعيل إرسال Server فعلياً:
     * - يستخدم action: placeserverorder (في أغلب DHRU)
     * - REQUIRED تُرسل كـ JSON داخل XML (إذا لم تحتاجها بعض الخدمات، تمرّ فارغة).
     */
    public function placeServerOrder(ApiProvider $provider, ServerOrder $order): array
    {
        $serviceRemoteId = (string) ($order->service?->remote_id ?? '');
        $quantity = (int) ($order->quantity ?? 1);

        $required = [];
        if (is_array($order->params) && isset($order->params['required']) && is_array($order->params['required'])) {
            $required = $order->params['required'];
        }

        $comments = (string) ($order->comments ?? '');

        $requiredJson = json_encode($required, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $xml = '<PARAMETERS>'
            . '<ID>' . $this->xmlEscape($serviceRemoteId) . '</ID>'
            . '<QUANTITY>' . $this->xmlEscape((string)$quantity) . '</QUANTITY>'
            . '<REQUIRED>' . $this->xmlEscape((string)$requiredJson) . '</REQUIRED>'
            . '<COMMENTS>' . $this->xmlEscape($comments) . '</COMMENTS>'
            . '</PARAMETERS>';

        return $this->send($provider, 'placeserverorder', $xml);
    }

    /**
     * تفعيل إرسال File فعلياً:
     * - يتطلب storage_path موجود وقابل للقراءة.
     * - action: placefileorder (في أغلب DHRU)
     */
    public function placeFileOrder(ApiProvider $provider, FileOrder $order): array
    {
        $serviceRemoteId = (string) ($order->service?->remote_id ?? '');
        $comments = (string) ($order->comments ?? '');

        $path = (string) ($order->storage_path ?? '');
        if ($path === '' || !Storage::exists($path)) {
            return [
                'ok' => false,
                'status' => 'rejected',
                'remote_id' => null,
                'request' => null,
                'response' => ['ERROR' => [['MESSAGE' => 'File storage_path missing or file not found in storage.']]],
            ];
        }

        $raw = Storage::get($path);
        $filename = (string) ($order->device ?? basename($path));
        $b64 = base64_encode($raw);

        $xml = '<PARAMETERS>'
            . '<ID>' . $this->xmlEscape($serviceRemoteId) . '</ID>'
            . '<FILENAME>' . $this->xmlEscape($filename) . '</FILENAME>'
            . '<FILEDATA>' . $this->xmlEscape($b64) . '</FILEDATA>'
            . '<COMMENTS>' . $this->xmlEscape($comments) . '</COMMENTS>'
            . '</PARAMETERS>';

        return $this->send($provider, 'placefileorder', $xml);
    }
}
