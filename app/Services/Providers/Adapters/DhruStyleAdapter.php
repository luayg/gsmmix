<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteFileService;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Services\Providers\DhruClient;
use App\Services\Providers\ProviderAdapterInterface;
use Illuminate\Support\Facades\DB;

abstract class DhruStyleAdapter implements ProviderAdapterInterface
{
    abstract public function type(): string;

    public function supportsCatalog(string $kind): bool
    {
        return in_array($kind, ['imei', 'server', 'file'], true);
    }

    public function fetchBalance(\App\Models\ApiProvider $provider): float
{
    $client = \App\Services\Api\DhruClient::fromProvider($provider);
    $data = $client->accountInfo();

    // 1) حاول المسارات المعروفة (حسب docs)
    $raw =
        data_get($data, 'SUCCESS.0.AccoutInfo.creditraw') ??
        data_get($data, 'SUCCESS.0.AccoutInfo.creditRaw') ??
        data_get($data, 'SUCCESS.0.AccoutInfo.CREDITRAW') ??
        data_get($data, 'SUCCESS.0.AccoutInfo.credit') ??
        data_get($data, 'SUCCESS.0.AccoutInfo.CREDIT') ??
        data_get($data, 'SUCCESS.0.AccountInfo.creditraw') ??      // بعض المزودين يصححون الاسم
        data_get($data, 'SUCCESS.0.AccountInfo.creditRaw') ??
        data_get($data, 'SUCCESS.0.AccountInfo.CREDITRAW') ??
        data_get($data, 'SUCCESS.0.AccountInfo.credit') ??
        data_get($data, 'SUCCESS.0.AccountInfo.CREDIT');

    // 2) إذا فشل، ابحث عن أي creditraw في كامل الـ JSON
    if ($raw === null) {
        $raw = $this->deepFind($data, ['creditraw', 'creditRaw', 'CREDITRAW', 'credit', 'CREDIT']);
    }

    return $this->toFloat($raw);
}

private function deepFind($data, array $keys)
{
    if (!is_array($data)) return null;

    foreach ($keys as $k) {
        if (array_key_exists($k, $data)) return $data[$k];
    }

    foreach ($data as $v) {
        if (is_array($v)) {
            $found = $this->deepFind($v, $keys);
            if ($found !== null) return $found;
        }
    }

    return null;
}


    public function syncCatalog(ApiProvider $provider, string $kind): int
    {
        $client = DhruClient::fromProvider($provider);

        return DB::transaction(function () use ($provider, $kind, $client) {
            if ($kind === 'file') {
                $data = $client->getFileServices();
                return $this->syncFile($provider, $data);
            }

            $data = $client->getAllServicesAndGroups();
            return $this->syncImeiOrServer($provider, $kind, $data);
        });
    }

    private function extractInfo(ApiProvider $provider, array $srv): ?string
    {
        // 1) مفاتيح مباشرة شائعة
        $directKeys = [
            'INFO','info',
            'DESCRIPTION','description',
            'SERVICEINFO','serviceinfo',
            'SERVICE_INFO','service_info',
            'DETAILS','details',
            'INFO_HTML','info_html',
            'DESCRIPTION_HTML','description_html',
        ];

        foreach ($directKeys as $k) {
            if (array_key_exists($k, $srv)) {
                $v = $srv[$k];
                $txt = $this->normalizeInfoValue($v);
                if ($txt !== null) return $this->appendImageIfMissing($provider, $txt, $srv);
            }
        }

        // 2) داخل CUSTOM
        $custom = $srv['CUSTOM'] ?? $srv['custom'] ?? null;
        if (is_array($custom)) {
            foreach (['INFO','info','DESCRIPTION','description','SERVICEINFO','serviceinfo','SERVICE_INFO','service_info'] as $k) {
                if (array_key_exists($k, $custom)) {
                    $txt = $this->normalizeInfoValue($custom[$k]);
                    if ($txt !== null) return $this->appendImageIfMissing($provider, $txt, $srv);
                }
            }
        } elseif (is_string($custom)) {
            $decoded = json_decode($custom, true);
            if (is_array($decoded)) {
                foreach (['INFO','info','DESCRIPTION','description','SERVICEINFO','serviceinfo','SERVICE_INFO','service_info'] as $k) {
                    if (array_key_exists($k, $decoded)) {
                        $txt = $this->normalizeInfoValue($decoded[$k]);
                        if ($txt !== null) return $this->appendImageIfMissing($provider, $txt, $srv);
                    }
                }
            }
        }

        // 3) بحث عميق (nested) داخل كامل srv
        $found = $this->deepFind($srv, $directKeys);
        $txt = $this->normalizeInfoValue($found);
        if ($txt !== null) return $this->appendImageIfMissing($provider, $txt, $srv);

        return $this->extractImageTagFromSrv($provider, $srv);
    }

    private function appendImageIfMissing(ApiProvider $provider, $arg2, $arg3 = null): string
    {
        // Backward-safe parsing in case of mixed call order after deployments/merges.
        // Preferred order: (provider, txt, srv)
        $txt = is_string($arg2) ? $arg2 : (is_string($arg3) ? $arg3 : '');

        $srv = [];
        if (is_array($arg3)) {
            $srv = $arg3;
        } elseif (is_array($arg2)) {
            // Legacy/order-mismatch fallback: (provider, srv, txt)
            $srv = $arg2;
        }

        if ($txt === '') return '';
        if (stripos($txt, '<img') !== false) return $txt;

        $img = $this->extractImageTagFromSrv($provider, $srv);
        if (!$img) return $txt;

        return $txt."\n".$img;
    }

    private function normalizeInfoValue($value): ?string
    {
        if ($value === null) return null;

        // إذا جاءت array (مثل rich data) حولها لنص
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $s = (string)$value;
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Keep safe rich content (especially tables/icons/images) instead of flattening all HTML.
        $s = strip_tags($s, '<img><br><hr><p><div><span><small><sup><sub><b><strong><i><u><ul><ol><li><a><table><thead><tbody><tr><th><td><h1><h2><h3><h4><h5><h6>');

        // Basic sanitization for dangerous attributes/protocols.
        $s = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $s) ?? $s;
        $s = preg_replace('/\s(href|src)\s*=\s*("|\')\s*javascript:[^"\']*("|\')/iu', ' $1="#"', $s) ?? $s;
        $s = trim($s);

        return $this->clean($s);
    }

    private function extractImageTagFromSrv(ApiProvider $provider, array $srv): ?string
    {
        $keys = [
            'IMAGE','image','IMAGE_URL','image_url','IMG','img','PHOTO','photo','ICON','icon',
            'SERVICE_IMAGE','service_image','DESCRIPTION_IMAGE','description_image',
        ];

        foreach ($keys as $k) {
            if (!array_key_exists($k, $srv)) continue;
            $v = $this->extractImageUrlFromValue($provider, $srv[$k] ?? null);
            if ($v !== null) {
                return '<img src="'.e($v).'" alt="service image">';
            }
        }

        foreach ($this->flattenValues($srv) as $v) {
            $url = $this->extractImageUrlFromValue($provider, $v);
            if ($url !== null) {
                return '<img src="'.e($url).'" alt="service image">';
            }
        }

        return null;
    }

    private function extractImageUrlFromValue(ApiProvider $provider, $value): ?string
    {
        if ($value === null) return null;

        if (is_array($value)) {
            foreach ($value as $item) {
                $url = $this->extractImageUrlFromValue($provider, $item);
                if ($url !== null) return $url;
            }
            return null;
        }

        $raw = trim((string)$value);
        if ($raw === '') return null;
        $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match('~<img[^>]+src\s*=\s*["\']?([^"\'\s>]+)~iu', $raw, $m)) {
            $url = $this->normalizeImageUrl($provider, $m[1]);
            if ($url !== null) return $url;
        }

        // Scan ALL possible URL-like pieces and pick first valid normalized image URL.
        // IMPORTANT: ignore candidates that are part of HTML tags themselves (e.g. </sup> => /sup).
        preg_match_all(
            '~(https?:\/\/[^\s"\'<>]+|\/\/[^\s"\'<>]+|\/[^\s"\'<>]+|data:image\/[^\s"\'<>]+)~iu',
            $raw,
            $all,
            PREG_OFFSET_CAPTURE
        );

        foreach (($all[1] ?? []) as $hit) {
            $candidate = (string)($hit[0] ?? '');
            $offset = (int)($hit[1] ?? -1);
            if ($candidate === '' || $offset < 0) continue;

            // If immediately preceded by '<', it's likely an HTML tag fragment, not a URL.
            $prev = $offset > 0 ? substr($raw, $offset - 1, 1) : '';
            if ($prev === '<') continue;

            $url = $this->normalizeImageUrl($provider, $candidate);
            if ($url !== null) return $url;
        }

        return null;
    }



    private function isHtmlTagPath(string $url): bool
    {
        return (bool) preg_match('~^/(?:sup|sub|b|i|u|br|hr|span|div|p|small|strong|img|table|thead|tbody|tr|td|th|h[1-6])$~i', trim($url));
    }

    private function normalizeImageUrl(ApiProvider $provider, string $url): ?string
    {
        $url = trim($url);
        $url = trim($url, "\t\n\r\0\x0B.,;)");
        if ($url === '') return null;

        // Drop obviously broken captures from free text (e.g. /SN], /IMEI/SN]).
        if (preg_match("/[\\[\\]<>\"'\\s]/u", $url)) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }

        if (preg_match('~^data:image\/[a-zA-Z0-9.+-]+;base64,~', $url)) {
            return $url;
        }

        if (preg_match('~^https?://~i', $url)) {
            return $url;
        }

        // Relative paths are common in some providers (e.g. /uploads/... or /media?id=123).
        // But ignore pure HTML closing/opening tag artifacts like /sup from </sup>.
        if (str_starts_with($url, '/')) {
            if ($this->isHtmlTagPath($url)) return null;

            $base = rtrim((string) $provider->url, '/');
            if ($base !== '') {
                return $base . $url;
            }
        }

        return null;
    }

    private function flattenValues($data): array
    {
        if (!is_array($data)) {
            return [$data];
        }

        $out = [];
        foreach ($data as $value) {
            if (is_array($value)) {
                $out = array_merge($out, $this->flattenValues($value));
            } else {
                $out[] = $value;
            }
        }

        return $out;
    }

    private function syncImeiOrServer(ApiProvider $provider, string $kind, array $data): int
    {
        $target = strtoupper($kind); // IMEI or SERVER

        $list = data_get($data, 'SUCCESS.0.LIST', []);
        if (!is_array($list)) $list = [];

        $seen = [];
        $count = 0;

        foreach ($list as $groupKey => $group) {
            if (!is_array($group)) continue;

            $groupName = (string)($group['GROUPNAME'] ?? $groupKey ?? '');
            $groupType = strtoupper((string)($group['GROUPTYPE'] ?? ''));

            $services = $group['SERVICES'] ?? [];
            if (!is_array($services)) continue;

            foreach ($services as $srvKey => $srv) {
                if (!is_array($srv)) continue;

                $serviceType = strtoupper((string)($srv['SERVICETYPE'] ?? $groupType));
                if ($serviceType !== $target) continue;

                // IMPORTANT: in your doc: SERVICEID exists and is the real service id
                $remoteId = (string)($srv['SERVICEID'] ?? $srv['serviceid'] ?? $srvKey ?? '');
                if ($remoteId === '') continue;

                $seen[] = $remoteId;

                $payload = [
                    'api_provider_id' => $provider->id,
                    'remote_id' => $remoteId,
                    'name' => (string)($srv['SERVICENAME'] ?? $srv['servicename'] ?? ''),
                    'group_name' => $groupName ?: null,
                    'price' => $this->toFloat($srv['CREDIT'] ?? $srv['credit'] ?? 0),
                    'time' => $this->clean((string)($srv['TIME'] ?? $srv['time'] ?? '')),
                    'info' => $this->extractInfo($provider, $srv),
                    'min_qty' => (string)($srv['MINQNT'] ?? null),
                    'max_qty' => (string)($srv['MAXQNT'] ?? null),
                    'additional_data' => $srv,
                    'params' => [
                        'GROUPTYPE' => $groupType,
                        'SERVICETYPE' => $serviceType,
                        'CUSTOM' => $srv['CUSTOM'] ?? null,
                    ],
                ];

                if ($kind === 'imei') {
                    $payload = array_merge($payload, [
                        'network' => $this->requires($srv['Requires.Network'] ?? null),
                        'mobile' => $this->requires($srv['Requires.Mobile'] ?? null),
                        'provider' => $this->requires($srv['Requires.Provider'] ?? null),
                        'pin' => $this->requires($srv['Requires.PIN'] ?? null),
                        'kbh' => $this->requires($srv['Requires.KBH'] ?? null),
                        'mep' => $this->requires($srv['Requires.MEP'] ?? null),
                        'prd' => $this->requires($srv['Requires.PRD'] ?? null),
                        'type' => $this->requires($srv['Requires.Type'] ?? null),
                        'locks' => $this->requires($srv['Requires.Locks'] ?? null),
                        'reference' => $this->requires($srv['Requires.Reference'] ?? null),
                        'udid' => $this->requires($srv['Requires.UDID'] ?? null),
                        'serial' => $this->requires($srv['Requires.SN'] ?? null),
                        'secro' => $this->requires($srv['Requires.SecRO'] ?? null),
                        'additional_fields' => $this->extractAdditionalFields($srv),
                    ]);

                    RemoteImeiService::updateOrCreate(
                        ['api_provider_id' => $provider->id, 'remote_id' => $remoteId],
                        $payload
                    );
                } else {
                    $payload['additional_fields'] = $this->extractAdditionalFields($srv);

                    RemoteServerService::updateOrCreate(
                        ['api_provider_id' => $provider->id, 'remote_id' => $remoteId],
                        $payload
                    );
                }

                $count++;
            }
        }

        // remove old not in list (optional)
        if (!empty($seen)) {
            if ($kind === 'imei') {
                RemoteImeiService::where('api_provider_id', $provider->id)->whereNotIn('remote_id', $seen)->delete();
            } else {
                RemoteServerService::where('api_provider_id', $provider->id)->whereNotIn('remote_id', $seen)->delete();
            }
        }

        return $count;
    }

    private function syncFile(ApiProvider $provider, array $data): int
    {
        $list = data_get($data, 'SUCCESS.0.LIST', []);
        if (!is_array($list)) $list = [];

        $seen = [];
        $count = 0;

        foreach ($list as $groupKey => $group) {
            if (!is_array($group)) continue;

            $groupName = (string)($group['GROUPNAME'] ?? $groupKey ?? '');
            $services = $group['SERVICES'] ?? [];
            if (!is_array($services)) continue;

            foreach ($services as $srvKey => $srv) {
                if (!is_array($srv)) continue;

                $remoteId = (string)($srv['SERVICEID'] ?? $srv['serviceid'] ?? $srvKey ?? '');
                if ($remoteId === '') continue;

                $seen[] = $remoteId;

                RemoteFileService::updateOrCreate(
                    ['api_provider_id' => $provider->id, 'remote_id' => $remoteId],
                    [
                        'api_provider_id' => $provider->id,
                        'remote_id' => $remoteId,
                        'name' => (string)($srv['SERVICENAME'] ?? $srv['servicename'] ?? ''),
                        'group_name' => $groupName ?: null,
                        'price' => $this->toFloat($srv['CREDIT'] ?? $srv['credit'] ?? 0),
                        'time' => $this->clean((string)($srv['TIME'] ?? $srv['time'] ?? '')),
                        'info' => $this->extractInfo($provider, $srv),
                        'allowed_extensions' => $this->clean((string)($srv['ALLOW_EXTENSION'] ?? '')),
                        'additional_data' => $srv,
                        'additional_fields' => $this->extractAdditionalFields($srv),
                        'params' => ['CUSTOM' => $srv['CUSTOM'] ?? null],
                    ]
                );

                $count++;
            }
        }

        if (!empty($seen)) {
            RemoteFileService::where('api_provider_id', $provider->id)->whereNotIn('remote_id', $seen)->delete();
        }

        return $count;
    }

    private function extractAdditionalFields(array $srv): array
    {
        // 1) الأكثر شيوعًا في DHRU
        $req = $srv['Requires.Custom'] ?? null;
        $normalizedReq = $this->normalizeAdditionalFieldsPayload($req);
        if (!empty($normalizedReq)) return $normalizedReq;

        // 2) بعض المزودين يضعونها مباشرة باسم CustomFields
        $req2 = $srv['CustomFields'] ?? $srv['custom_fields'] ?? null;
        $normalizedReq2 = $this->normalizeAdditionalFieldsPayload($req2);
        if (!empty($normalizedReq2)) return $normalizedReq2;

        // 3) أحيانًا تكون داخل CUSTOM
        $custom = $srv['CUSTOM'] ?? $srv['custom'] ?? null;
        if (is_array($custom)) {
            // أحيانًا CUSTOM تكون { fields: [...] }
            $maybe = $custom['fields'] ?? $custom['FIELDS'] ?? null;
            $normalizedMaybe = $this->normalizeAdditionalFieldsPayload($maybe);
            if (!empty($normalizedMaybe)) return $normalizedMaybe;

            // IMPORTANT: لا نحول associative CUSTOM إلى array_values().
            // بعض المزودين يرسلون scalar arrays مثل ["SN", "", ...]؛ هذه قيم خام وليست field objects.
            // تسطيحها ينتج حقولًا وهمية بدون مفاتيح تعريف مثل name/fieldname/type.
            $normalizedCustom = $this->normalizeAdditionalFieldsPayload($custom);
            if (!empty($normalizedCustom)) return $normalizedCustom;
        } elseif (is_string($custom)) {
            $decodedCustom = json_decode($custom, true);
            $normalizedDecodedCustom = $this->normalizeAdditionalFieldsPayload($decodedCustom);
            if (!empty($normalizedDecodedCustom)) return $normalizedDecodedCustom;
        }

        // 4) أحيانًا تكون string JSON
        foreach (['Requires.Custom', 'CustomFields', 'custom_fields', 'CUSTOM'] as $k) {
            $v = $srv[$k] ?? null;
            if (!is_string($v)) continue;

            $decoded = json_decode($v, true);
            $normalizedDecoded = $this->normalizeAdditionalFieldsPayload($decoded);
            if (!empty($normalizedDecoded)) return $normalizedDecoded;
        }

        return [];
    }

    private function normalizeAdditionalFieldsPayload($payload): array
    {
        if (!is_array($payload) || empty($payload)) return [];

        $fieldKeys = ['fieldname', 'name', 'label', 'fieldtype', 'type'];

        $looksLikeFieldObject = static function (array $candidate) use ($fieldKeys): bool {
            $normalizedKeys = array_map(
                static fn ($key): string => strtolower((string)$key),
                array_keys($candidate)
            );

            foreach ($fieldKeys as $key) {
                if (in_array($key, $normalizedKeys, true)) return true;
            }

            return false;
        };

        // payload is already a list of field objects.
        if (array_is_list($payload)) {
            $allFieldObjects = true;
            foreach ($payload as $item) {
                if (!is_array($item) || !$looksLikeFieldObject($item)) {
                    $allFieldObjects = false;
                    break;
                }
            }

            return $allFieldObjects ? $payload : [];
        }

        // payload is associative; accept only if it resembles one field object.
        // Reject scalar/metadata associative maps because values like ["SN", "", ...]
        // are not field definitions and would create bogus additional fields.
        return $looksLikeFieldObject($payload) ? [$payload] : [];
    }


    private function requires($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return ((int)$value) !== 0;
        $v = strtolower(trim((string)$value));
        return !($v === '' || $v === 'none' || $v === '0' || $v === 'false' || $v === 'no');
    }

    private function toFloat($value): float
    {
        if ($value === null) return 0.0;
        if (is_int($value) || is_float($value)) return (float)$value;

        $s = trim((string)$value);
        $s = str_replace([',', '$', 'USD', 'usd', ' '], '', $s);
        $s = preg_replace('/[^0-9\.\-]/', '', $s) ?? '';

        return is_numeric($s) ? (float)$s : 0.0;
    }

    private function clean(string $s): ?string
    {
        $s = trim($s);
        return $s === '' ? null : $s;
    }
}
