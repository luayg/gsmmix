<?php

namespace App\Services\Api;

class DhruClient
{
    protected string $endpoint;
    protected string $username;
    protected string $key;

    public function __construct(string $baseUrl, string $username, string $key)
    {
        $baseUrl = rtrim($baseUrl, '/');
        $this->endpoint = preg_match('~/api/index\.php$~i', $baseUrl)
            ? $baseUrl
            : $baseUrl . '/api/index.php';

        $this->username = $username;
        $this->key      = $key;
    }

    /** استدعاء DHRU v2 */
    protected function request(string $action, array $extra = []): array
    {
        $post = array_merge([
            'username'      => $this->username,
            'apiaccesskey'  => $this->key,
            'requestformat' => 'JSON',
            'action'        => $action,
        ], $extra);

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT        => 40,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ERROR' => [['MESSAGE' => $err]]];
        }

        $json = json_decode($response, true);
        return is_array($json) ? $json : ['ERROR' => [['MESSAGE' => 'invalid_json']], 'RAW' => $response];
    }

    /** تفريغ كتلة SUCCESS */
    protected function successBlock(array $resp): array
    {
        $succ = $resp['SUCCESS'] ?? [];
        if (is_array($succ) && array_key_exists(0, $succ)) {
            $succ = $succ[0];
        }
        return is_array($succ) ? $succ : [];
    }

    /** الحصول على LIST من الاستجابة */
    protected function listBlock(array $resp): array
    {
        $succ = $this->successBlock($resp);
        $list = $succ['LIST'] ?? ($succ['SERVICES'] ?? []);
        return is_array($list) ? $list : [];
    }

    /** تسطيح مجموعات الخدمات إلى مصفوفة مسطّحة، مع فلترة نوع الخدمة اختياريًا */
    protected function flattenFromList(array $list, ?string $typeFilter = null): array
    {
        $out = [];

        // إذا كانت القائمة أصلًا مسطّحة
        $first = is_array($list) ? reset($list) : null;
        if (is_array($first) && isset($first['SERVICEID'])) {
            foreach ($list as $srv) {
                if ($typeFilter && strtoupper($srv['SERVICETYPE'] ?? '') !== $typeFilter) {
                    continue;
                }
                $srv['group'] = $srv['GROUPNAME'] ?? ($srv['GROUP'] ?? '');
                $out[] = $srv;
            }
            return array_values($out);
        }

        // حالة مجموعات: مرّ على كل مجموعة وخدماتها
        foreach ($list as $groupName => $group) {
            $gName    = $group['GROUPNAME'] ?? (is_string($groupName) ? $groupName : '');
            $services = $group['SERVICES'] ?? [];
            foreach ($services as $srv) {
                if ($typeFilter && strtoupper($srv['SERVICETYPE'] ?? '') !== $typeFilter) {
                    continue;
                }
                $srv['group'] = $gName;
                $out[] = $srv;
            }
        }

        return $out;
    }

    /** معلومات الحساب */
    public function accountInfo(): array
    {
        $resp = $this->request('accountinfo');
        $succ = $this->successBlock($resp);
        $info = $succ['AccountInfo'] ?? $succ['AccoutInfo'] ?? [];

        $credits = 0.0;
        $creditRaw = $info['creditraw'] ?? $info['credit'] ?? null;
        if ($creditRaw !== null) {
            $num = preg_replace('/[^\d\.\-]/', '', (string) $creditRaw);
            if ($num !== '') $credits = (float) $num;
        }

        return [
            'credits'  => $credits,
            'currency' => $info['currency'] ?? null,
            'version'  => $resp['apiversion'] ?? null,
            'raw'      => $resp,
        ];
    }

    /** RAW: ترجع كل المجموعات والخدمات (IMEI + SERVER + REMOTE) */
    public function allServicesRaw(): array
    {
        return $this->request('imeiservicelist');
    }

    /** RAW: خدمات الملف */
    public function fileServicesRaw(): array
    {
        return $this->request('fileservicelist');
    }

    /** قائمة IMEI مسطّحة */
    public function imeiServices(): array
    {
        $list = $this->listBlock($this->allServicesRaw());
        return $this->flattenFromList($list, 'IMEI');
    }

    /** قائمة SERVER مسطّحة (تُرشّح من imeiservicelist) */
    public function serverServices(): array
    {
        $list = $this->listBlock($this->allServicesRaw());
        return $this->flattenFromList($list, 'SERVER');
    }

    /** قائمة FILE مسطّحة (من fileservicelist) */
    public function fileServices(): array
    {
        $list = $this->listBlock($this->fileServicesRaw());
        return $this->flattenFromList($list, null);
    }
}
