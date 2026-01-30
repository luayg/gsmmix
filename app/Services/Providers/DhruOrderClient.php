<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use Illuminate\Support\Facades\Http;

class DhruOrderClient
{
    private function endpoint(ApiProvider $p): string
    {
        // في مشروعك provider->url غالباً ينتهي بـ /
        $base = rtrim((string)$p->url, '/');
        return $base . '/api/index.php';
    }

    private function basePayload(ApiProvider $p, string $action): array
    {
        return [
            'username' => (string)$p->username,
            'apiaccesskey' => (string)$p->api_key,
            'requestformat' => 'JSON',
            'action' => $action,
        ];
    }

    /**
     * Single order (IMEI أو SERVER) — DHRU يستخدم placeimeiorder للنوعين.
     * $parametersXml مثل:
     * <PARAMETERS><IMEI>...</IMEI><ID>123</ID><CUSTOMFIELD>base64...</CUSTOMFIELD></PARAMETERS>
     */
    public function placeOrder(ApiProvider $p, string $parametersXml): array
    {
        $payload = $this->basePayload($p, 'placeimeiorder');
        $payload['parameters'] = $parametersXml;

        $res = Http::asForm()
            ->timeout(60)
            ->post($this->endpoint($p), $payload);

        $json = $res->json();
        if (!is_array($json)) {
            return ['ok' => false, 'raw' => $res->body(), 'status' => $res->status()];
        }

        return ['ok' => true, 'data' => $json, 'status' => $res->status()];
    }

    public function getOrder(ApiProvider $p, string $referenceId): array
    {
        $payload = $this->basePayload($p, 'getimeiorder');
        $payload['parameters'] = "<PARAMETERS><ID>{$referenceId}</ID></PARAMETERS>";

        $res = Http::asForm()
            ->timeout(60)
            ->post($this->endpoint($p), $payload);

        $json = $res->json();
        if (!is_array($json)) {
            return ['ok' => false, 'raw' => $res->body(), 'status' => $res->status()];
        }

        return ['ok' => true, 'data' => $json, 'status' => $res->status()];
    }
}
