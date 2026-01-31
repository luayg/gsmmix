<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use Illuminate\Support\Facades\Http;

class DhruOrderGateway
{
    public function place(string $kind, ApiProvider $provider, $remoteServiceId, $order): array
    {
        $apiUrl = rtrim((string)$provider->url, '/') . '/api/index.php';

        $paramsXml = $this->buildParametersXml($kind, $remoteServiceId, $order);

        $payload = [
            'username'     => $provider->username,
            'apiaccesskey' => $provider->api_key,
            'requestformat'=> 'JSON',
            'action'       => 'placeimeiorder', // نفس endpoint لـ IMEI/SERVER حسب Dhru doc (placeimeiorder)
            'parameters'   => $paramsXml,
        ];

        $r = Http::asForm()->timeout(60)->post($apiUrl, $payload);
        $raw = (string)$r->body();

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'status' => 'rejected',
                'request' => $payload,
                'response_raw' => $raw,
            ];
        }

        // ✅ تحليل SUCCESS/ERROR
        // مثال success: {"SUCCESS":[{"MESSAGE":"Order received","REFERENCEID":"3489..."}]}
        // مثال error: {"ERROR":[{"MESSAGE":"CreditprocessError","FULL_DESCRIPTION":"You have not enough credit"}]}
        $status = 'inprogress';
        $remoteId = null;

        if (!empty($decoded['ERROR'])) {
            $status = 'rejected';
        }

        if (!empty($decoded['SUCCESS'][0]['REFERENCEID'])) {
            $remoteId = $decoded['SUCCESS'][0]['REFERENCEID'];
            $status = 'inprogress';
        }

        // إذا الرد فيه “not enough credit” نخليه rejected صريح
        $flat = strtolower($raw);
        if (str_contains($flat, 'not enough credit')) {
            $status = 'rejected';
        }

        return [
            'status' => $status,
            'remote_id' => $remoteId,
            'request' => $payload,
            'response' => $decoded,
            'response_raw' => $raw,
        ];
    }

    private function buildParametersXml(string $kind, $serviceId, $order): string
    {
        // Dhru expects:
        // <PARAMETERS><IMEI>...</IMEI><ID>...</ID><CUSTOMFIELD>base64(json)</CUSTOMFIELD></PARAMETERS>
        // For SERVER: still ID + optional fields SN/REFERENCE/... حسب Requires.Custom.
        $device = trim((string)$order->device);

        $xml = '<PARAMETERS>';
        if ($kind === 'imei') {
            $xml .= '<IMEI>'.htmlspecialchars($device, ENT_QUOTES).'</IMEI>';
            $xml .= '<ID>'.htmlspecialchars((string)$serviceId, ENT_QUOTES).'</ID>';
        } elseif ($kind === 'server') {
            $xml .= '<ID>'.htmlspecialchars((string)$serviceId, ENT_QUOTES).'</ID>';
            // نضعه كـ SN افتراضياً (كثير خدمات Server تطلب SN/Custom)
            $xml .= '<SN>'.htmlspecialchars($device, ENT_QUOTES).'</SN>';
            if (!empty($order->quantity)) {
                $xml .= '<QNT>'.(int)$order->quantity.'</QNT>';
            }
        } elseif ($kind === 'file') {
            $xml .= '<ID>'.htmlspecialchars((string)$serviceId, ENT_QUOTES).'</ID>';
            // device info كـ REFERENCE
            $xml .= '<REFERENCE>'.htmlspecialchars($device, ENT_QUOTES).'</REFERENCE>';
        } else {
            $xml .= '<ID>'.htmlspecialchars((string)$serviceId, ENT_QUOTES).'</ID>';
        }

        $xml .= '</PARAMETERS>';
        return $xml;
    }
}
