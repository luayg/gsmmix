<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteFileService;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Services\Api\DhruClient;
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
                    'info' => $this->clean((string)($srv['INFO'] ?? $srv['info'] ?? '')),
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
                        'info' => $this->clean((string)($srv['INFO'] ?? $srv['info'] ?? '')),
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
        $fields = [];
        $req = $srv['Requires.Custom'] ?? null;
        if (is_array($req)) $fields = array_values($req);
        return $fields;
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
