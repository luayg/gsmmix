<?php

namespace App\Services\Api;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class DhruClient
{
    protected string $baseUrl;
    protected string $username;
    protected string $apiKey;
    protected string $requestFormat;

    public function __construct(string $baseUrl, string $username, string $apiKey, string $requestFormat = 'JSON')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->apiKey = $apiKey;
        $this->requestFormat = strtoupper($requestFormat ?: 'JSON');
    }

    protected function http(): PendingRequest
    {
        return Http::asForm()
            ->timeout(60)
            ->connectTimeout(20)
            ->withHeaders([
                'Accept' => 'application/json',
            ]);
    }

    protected function endpoint(): string
    {
        return $this->baseUrl . '/api/index.php';
    }

    /**
     * Core call:
     * - parameters can be:
     *   1) array -> converted to XML <PARAMETERS><KEY>VAL</KEY>...</PARAMETERS>
     *   2) string that starts with "<" -> sent as-is (XML)
     *   3) any other string -> sent as-is (usually base64 JSON for bulk)
     */
    public function call(string $action, array|string|null $parameters = null): array
    {
        $payload = [
            'username'      => $this->username,
            'apiaccesskey'  => $this->apiKey,
            'requestformat' => $this->requestFormat,
            'action'        => $action,
        ];

        if ($parameters !== null) {
            if (is_array($parameters)) {
                $payload['parameters'] = $this->buildXmlParameters($parameters);
            } else {
                $payload['parameters'] = $parameters;
            }
        }

        $res = $this->http()->post($this->endpoint(), $payload);

        // إذا الرد ليس JSON، سنرجع نصه كخطأ واضح
        $json = $res->json();
        if (!is_array($json)) {
            throw new \RuntimeException("DHRU non-JSON response (HTTP {$res->status()}): " . mb_substr((string)$res->body(), 0, 500));
        }

        // معالجة أخطاء DHRU القياسية
        if (isset($json['ERROR']) && is_array($json['ERROR']) && count($json['ERROR'])) {
            $msg = $json['ERROR'][0]['MESSAGE'] ?? $json['ERROR'][0]['message'] ?? 'DHRU ERROR';
            $desc = $json['ERROR'][0]['FULL_DESCRIPTION'] ?? '';
            throw new \RuntimeException(trim($msg . ' ' . $desc));
        }

        return $json;
    }

    /** XML parameters builder (مثل API kit القديم في ملف zip) */
    public function buildXmlParameters(array $params): string
    {
        $xml = '<PARAMETERS>';
        foreach ($params as $k => $v) {
            $k = strtoupper((string)$k);
            $v = htmlspecialchars((string)$v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $xml .= "<{$k}>{$v}</{$k}>";
        }
        $xml .= '</PARAMETERS>';
        return $xml;
    }

    /** Base64(JSON) helper */
    public function base64Json(array $data): string
    {
        return base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /** ========= High-level API v2 ========= */

    public function accountInfo(): array
    {
        $json = $this->call('accountinfo');

        $info = $json['SUCCESS'][0]['AccoutInfo'] ?? $json['SUCCESS'][0]['ACCOUNTINFO'] ?? null;
        if (!is_array($info)) {
            // أحيانًا يكون الاسم مختلف عند بعض المزودين
            $info = $json['SUCCESS'][0] ?? [];
        }

        return [
            'credit'    => $info['credit'] ?? null,
            'creditraw' => $info['creditraw'] ?? null,
            'mail'      => $info['mail'] ?? null,
            'currency'  => $info['currency'] ?? null,
            'raw'       => $json,
        ];
    }

    /**
     * According to docs:
     * action=imeiservicelist returns all groups/services
     * GROUPTYPE=[IMEI,SERVER,REMOTE] and each service has SERVICETYPE.
     */
    public function allServicesAndGroups(): array
    {
        $json = $this->call('imeiservicelist');

        $list = $json['SUCCESS'][0]['LIST'] ?? null;
        if (!is_array($list)) return [];

        // Normalize to: array of groups -> services
        // LIST: { "GroupName": { GROUPNAME, GROUPTYPE, SERVICES: { "6484": {...} } } }
        return $list;
    }

    public function fileServiceList(): array
    {
        $json = $this->call('fileservicelist');
        $list = $json['SUCCESS'][0]['LIST'] ?? null;
        return is_array($list) ? $list : [];
    }

    public function placeImeiOrder(array $params): string
    {
        // docs show XML inside parameters:
        // <PARAMETERS><IMEI>..</IMEI><ID>..</ID><CUSTOMFIELD>base64(json)</CUSTOMFIELD></PARAMETERS>
        $json = $this->call('placeimeiorder', $params);
        $ref = $json['SUCCESS'][0]['REFERENCEID'] ?? null;
        if (!$ref) throw new \RuntimeException('DHRU: missing REFERENCEID');
        return (string)$ref;
    }

    public function placeImeiOrderBulk(array $orders): array
    {
        // docs: parameters = base64_encode('[{"IMEI":"...","ID":123}, ...]')
        $payload = $this->base64Json($orders);
        $json = $this->call('placeimeiorderbulk', $payload);
        return $json;
    }

    public function getImeiOrder(int|string $id): array
    {
        // docs: action=getimeiorder parameters base64(json) في بعض implementations,
        // لكن example code shows XML decoded -> we'll send XML for safety.
        $json = $this->call('getimeiorder', ['id' => $id]);
        return $json;
    }

    public function getImeiOrderBulk(array $ids): array
    {
        // send base64 json like bulk
        $payload = $this->base64Json(array_map(fn($x) => ['ID' => $x], $ids));
        return $this->call('getimeiorderbulk', $payload);
    }
}
