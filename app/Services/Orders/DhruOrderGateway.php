<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;

class DhruOrderGateway
{
    public function fetchBalance(ApiProvider $provider): array
    {
        return $this->post($provider, [
            'action' => 'accountinfo',
        ]);
    }

    /**
     * IMEI + SERVER (كلاهما placeimeiorder في DHRU)
     */
    public function placeImeiOrder(ApiProvider $provider, array $xmlParams): array
    {
        $xml = $this->toXmlParameters($xmlParams);

        return $this->post($provider, [
            'action'     => 'placeimeiorder',
            'parameters' => $xml,
        ]);
    }

    public function getImeiOrder(ApiProvider $provider, string $referenceId): array
    {
        return $this->post($provider, [
            'action' => 'getimeiorder',
            'ID'     => $referenceId,
        ]);
    }

    /**
     * File orders
     */
    public function placeFileOrder(ApiProvider $provider, int|string $serviceRemoteId, string $fileName, string $fileBase64): array
    {
        return $this->post($provider, [
            'action'    => 'placefileorder',
            'ID'        => (string)$serviceRemoteId,
            'FILENAME'  => $fileName,
            'FILEDATA'  => $fileBase64,
        ]);
    }

    public function getFileOrder(ApiProvider $provider, string $referenceId): array
    {
        return $this->post($provider, [
            'action' => 'getfileorder',
            'ID'     => $referenceId,
        ]);
    }

    public function normalizeStatus(array $resp): array
    {
        // Returns: ['ok'=>bool, 'status'=>'FAILED|INPROGRESS|SUCCESS', 'message'=>string, 'reference_id'=>?string, 'raw'=>array]

        $message = '';
        $reference = null;

        if (isset($resp['ERROR'])) {
            $message = $this->pickMessage($resp['ERROR']);
            return ['ok'=>false, 'status'=>'FAILED', 'message'=>$message, 'reference_id'=>null, 'raw'=>$resp];
        }

        if (isset($resp['SUCCESS'])) {
            $message = $this->pickMessage($resp['SUCCESS']);
            $reference = $this->pickReferenceId($resp['SUCCESS']);
            // نجاح "استلام الطلب" عادة يعني INPROGRESS
            return ['ok'=>true, 'status'=>'INPROGRESS', 'message'=>$message ?: 'Order received', 'reference_id'=>$reference, 'raw'=>$resp];
        }

        // شكل غير متوقع
        $message = 'Unknown response';
        return ['ok'=>false, 'status'=>'FAILED', 'message'=>$message, 'reference_id'=>null, 'raw'=>$resp];
    }

    private function pickMessage($block): string
    {
        // block usually array of arrays
        if (is_array($block)) {
            foreach ($block as $row) {
                if (is_array($row)) {
                    if (!empty($row['FULL_DESCRIPTION'])) return (string)$row['FULL_DESCRIPTION'];
                    if (!empty($row['MESSAGE'])) return (string)$row['MESSAGE'];
                    if (!empty($row['message'])) return (string)$row['message'];
                }
            }
        }
        return '';
    }

    private function pickReferenceId($block): ?string
    {
        if (is_array($block)) {
            foreach ($block as $row) {
                if (is_array($row) && !empty($row['REFERENCEID'])) return (string)$row['REFERENCEID'];
            }
        }
        return null;
    }

    private function post(ApiProvider $provider, array $extra): array
    {
        $url = rtrim((string)$provider->url, '/').'/api/index.php';

        $payload = array_merge([
            'username'      => (string)$provider->username,
            'apiaccesskey'  => (string)$provider->api_key,
            'requestformat' => 'JSON',
        ], $extra);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $err) {
            return ['ERROR' => [['MESSAGE' => 'CurlError', 'FULL_DESCRIPTION' => $err ?: 'Unknown curl error']], 'http_status' => $code];
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return ['ERROR' => [['MESSAGE' => 'BadJSON', 'FULL_DESCRIPTION' => $body]], 'http_status' => $code];
        }

        $json['http_status'] = $code;
        return $json;
    }

    private function toXmlParameters(array $params): string
    {
        // Build: <PARAMETERS><IMEI>..</IMEI><ID>..</ID> ...</PARAMETERS>
        $xml = '<PARAMETERS>';
        foreach ($params as $k => $v) {
            $k = strtoupper(trim((string)$k));
            $val = htmlspecialchars((string)$v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $xml .= "<{$k}>{$val}</{$k}>";
        }
        $xml .= '</PARAMETERS>';
        return $xml;
    }
}
