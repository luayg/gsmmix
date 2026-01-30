<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use Illuminate\Support\Facades\Http;

class OrderSender
{
    /**
     * إرسال IMEI/SERVER عبر DHRU (placeimeiorder).
     * (DHRU يستخدم نفس action للـ IMEI و SERVER حسب نوع الخدمة نفسها)
     */
    public function sendDhruImeiOrServer(ApiProvider $provider, array $payload): array
    {
        $url = rtrim((string)$provider->url, '/') . '/api/index.php';

        $base = [
            'username'     => (string)$provider->username,
            'apiaccesskey' => (string)$provider->api_key,
            'requestformat'=> 'JSON',
        ];

        $post = array_merge($base, [
            'action'     => 'placeimeiorder',
            'parameters' => $payload['parameters_xml'], // <PARAMETERS>...</PARAMETERS>
        ]);

        $resp = Http::asForm()->timeout(60)->post($url, $post);
        $json = $resp->json();

        return [
            'http_status' => $resp->status(),
            'json' => $json,
            'raw'  => $resp->body(),
        ];
    }

    /**
     * استخراج رسالة مفهومة من DHRU response (SUCCESS/ERROR).
     */
    public function dhruExtractMessage(?array $json): array
    {
        $out = [
            'ok' => false,
            'reference_id' => null,
            'message' => null,
            'full_description' => null,
        ];

        if (!is_array($json)) {
            $out['message'] = 'Empty response';
            return $out;
        }

        // SUCCESS
        if (!empty($json['SUCCESS']) && is_array($json['SUCCESS'])) {
            $first = $json['SUCCESS'][0] ?? [];
            $out['ok'] = true;
            $out['message'] = $first['MESSAGE'] ?? 'SUCCESS';
            $out['reference_id'] = $first['REFERENCEID'] ?? null;
            return $out;
        }

        // ERROR (مثل CreditprocessError)
        if (!empty($json['ERROR']) && is_array($json['ERROR'])) {
            $first = $json['ERROR'][0] ?? [];
            $out['ok'] = false;
            $out['message'] = $first['MESSAGE'] ?? 'ERROR';
            $out['full_description'] = $first['FULL_DESCRIPTION'] ?? null;
            return $out;
        }

        // Sometimes provider returns strange format
        $out['message'] = $json['message'] ?? 'Unknown response format';
        return $out;
    }

    /**
     * بناء XML parameters للـ DHRU placeimeiorder
     * ID = remote service id
     * IMEI = device
     * CUSTOMFIELD = base64(json) إذا موجود
     */
    public function buildDhruParametersXml(array $fields): string
    {
        $xml = '<PARAMETERS>';

        foreach ($fields as $k => $v) {
            if ($v === null || $v === '') continue;
            $key = strtoupper($k);
            $val = htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $xml .= "<{$key}>{$val}</{$key}>";
        }

        $xml .= '</PARAMETERS>';
        return $xml;
    }
}
