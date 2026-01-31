<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\ImeiOrder;
use Illuminate\Support\Facades\Http;

class DhruOrderGateway
{
    public function placeImeiOrder(ApiProvider $provider, ImeiOrder $order): array
    {
        $serviceRemoteId = (string)($order->service?->remote_id ?? '');
        $imeiOrSn = (string)$order->device;

        $payload = [
            'username'      => (string)($provider->username ?? ''),
            'apiaccesskey'  => (string)($provider->api_key ?? ''),
            'requestformat' => 'JSON',
            'action'        => 'placeimeiorder',
            'parameters'    => '<PARAMETERS>'
                .'<IMEI>'.e($imeiOrSn).'</IMEI>'
                .'<ID>'.e($serviceRemoteId).'</ID>'
                .'</PARAMETERS>',
        ];

        $url = rtrim((string)$provider->url, '/').'/api/index.php';

        $resp = Http::asForm()
            ->timeout(60)
            ->post($url, $payload);

        $raw = (string)$resp->body();
        $json = json_decode($raw, true);

        $result = [
            'ok' => false,
            'status' => 'rejected',
            'remote_id' => null,
            'request' => json_encode(['url'=>$url,'payload'=>$payload], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'response' => $raw,
        ];

        // Dhru SUCCESS
        if (is_array($json) && isset($json['SUCCESS'][0])) {
            $s0 = $json['SUCCESS'][0];
            $ref = $s0['REFERENCEID'] ?? null;

            $result['ok'] = true;
            $result['remote_id'] = $ref;
            $result['status'] = 'inprogress'; // تم استلام الطلب
            return $result;
        }

        // Dhru ERROR
        if (is_array($json) && isset($json['ERROR'][0])) {
            $e0 = $json['ERROR'][0];
            $msg = (string)($e0['MESSAGE'] ?? 'Unknown error');
            $result['ok'] = false;
            $result['status'] = 'rejected';
            $result['response'] = json_encode($json, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

            // مثال: credit error
            if (stripos($msg, 'credit') !== false) {
                $result['status'] = 'rejected';
            }
            return $result;
        }

        // Unknown format
        return $result;
    }
}
