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

    public function fetchBalance(ApiProvider $provider): float
    {
        $client = DhruClient::fromProvider($provider);
        $data = $client->accountInfo();

        $raw = data_get($data, 'SUCCESS.0.AccoutInfo.creditraw')
            ?? data_get($data, 'SUCCESS.0.AccoutInfo.creditRaw')
            ?? data_get($data, 'SUCCESS.0.AccoutInfo.credit')
            ?? data_get($data, 'SUCCESS.0.AccoutInfo.CREDITRAW')
            ?? data_get($data, 'SUCCESS.0.AccoutInfo.CREDIT');

        return $this->toFloat($raw);
    }

    public function syncCatalog(ApiProvider $provider, string $kind): int
    {
        if (!$this->supportsCatalog($kind)) {
            return 0;
        }

        $client = DhruClient::fromProvider($provider);

        return DB::transaction(function () use ($provider, $kind, $client) {
            if ($kind === 'file') {
                $data = $client->getFileServices();
                return $this->syncFileServices($provider, $data);
            }

            // DHRU returns IMEI + SERVER + REMOTE in the same list
            $data = $client->getAllServicesAndGroups();
            return $this->syncImeiOrServerServices($provider, $kind, $data);
        });
    }

    private function syncImeiOrServerServices(ApiProvider $provider, string $kind, array $data): int
    {
        $targetType = strtoupper($kind); // IMEI or SERVER

        $list = data_get($data, 'SUCCESS.0.LIST', []);
        if (!is_array($list)) {
            $list = [];
        }

        $seenRemoteIds = [];
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
                if ($serviceType !== $targetType) {
                    continue;
                }

                $remoteId = (string)($srv['SERVICEID'] ?? $srvKey ?? '');
                if ($remoteId === '') continue;

                $seenRemoteIds[] = $remoteId;

                $common = [
                    'name' => (string)($srv['SERVICENAME'] ?? ''),
                    'group_name' => $groupName,
                    'price' => $this->toFloat($srv['CREDIT'] ?? 0),
                    'time' => (string)($srv['TIME'] ?? null),
                    'info' => $this->normalizeInfo($srv['INFO'] ?? null),
                    'min_qty' => (string)($srv['MINQNT'] ?? null),
                    'max_qty' => (string)($srv['MAXQNT'] ?? null),
                    'credit_groups' => null,
                    'additional_fields' => $this->extractAdditionalFields($srv),
                    'additional_data' => $srv,
                    'params' => [
                        'GROUPTYPE' => $groupType,
                        'SERVICETYPE' => $serviceType,
                        'CUSTOM' => $srv['CUSTOM'] ?? null,
                    ],
                ];

                if ($kind === 'imei') {
                    $imeiData = array_merge($common, [
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
                    ]);

                    RemoteImeiService::updateOrCreate(
                        ['api_provider_id' => $provider->id, 'remote_id' => $remoteId],
                        array_merge(['api_provider_id' => $provider->id, 'remote_id' => $remoteId], $imeiData)
                    );
                } else {
                    RemoteServerService::updateOrCreate(
                        ['api_provider_id' => $provider->id, 'remote_id' => $remoteId],
                        array_merge(['api_provider_id' => $provider->id, 'remote_id' => $remoteId], $common)
                    );
                }

                $count++;
            }
        }

        // Optional: remove old services not present anymore
        if (!empty($seenRemoteIds)) {
            if ($kind === 'imei') {
                RemoteImeiService::where('api_provider_id', $provider->id)
                    ->whereNotIn('remote_id', $seenRemoteIds)
                    ->delete();
            } else {
                RemoteServerService::where('api_provider_id', $provider->id)
                    ->whereNotIn('remote_id', $seenRemoteIds)
                    ->delete();
            }
        }

        return $count;
    }

    private function syncFileServices(ApiProvider $provider, array $data): int
    {
        $list = data_get($data, 'SUCCESS.0.LIST', []);
        if (!is_array($list)) {
            $list = [];
        }

        $seenRemoteIds = [];
        $count = 0;

        foreach ($list as $groupKey => $group) {
            if (!is_array($group)) continue;

            $groupName = (string)($group['GROUPNAME'] ?? $groupKey ?? '');
            $services = $group['SERVICES'] ?? [];

            if (!is_array($services)) continue;

            foreach ($services as $srvKey => $srv) {
                if (!is_array($srv)) continue;

                $remoteId = (string)($srv['SERVICEID'] ?? $srvKey ?? '');
                if ($remoteId === '') continue;

                $seenRemoteIds[] = $remoteId;

                RemoteFileService::updateOrCreate(
                    ['api_provider_id' => $provider->id, 'remote_id' => $remoteId],
                    [
                        'api_provider_id' => $provider->id,
                        'remote_id' => $remoteId,
                        'name' => (string)($srv['SERVICENAME'] ?? ''),
                        'group_name' => $groupName,
                        'price' => $this->toFloat($srv['CREDIT'] ?? 0),
                        'time' => (string)($srv['TIME'] ?? null),
                        'allowed_extensions' => (string)($srv['ALLOW_EXTENSION'] ?? null),
                        'info' => $this->normalizeInfo($srv['INFO'] ?? null),
                        'min_qty' => (string)($srv['MINQNT'] ?? null),
                        'max_qty' => (string)($srv['MAXQNT'] ?? null),
                        'credit_groups' => null,
                        'additional_fields' => $this->extractAdditionalFields($srv),
                        'additional_data' => $srv,
                        'params' => [
                            'CUSTOM' => $srv['CUSTOM'] ?? null,
                        ],
                    ]
                );

                $count++;
            }
        }

        if (!empty($seenRemoteIds)) {
            RemoteFileService::where('api_provider_id', $provider->id)
                ->whereNotIn('remote_id', $seenRemoteIds)
                ->delete();
        }

        return $count;
    }

    private function requires($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return ((int)$value) !== 0;

        $v = strtolower(trim((string)$value));
        return !($v === '' || $v === 'none' || $v === '0' || $v === 'false' || $v === 'no');
    }

    private function extractAdditionalFields(array $srv): array
    {
        $fields = [];

        $req = $srv['Requires.Custom'] ?? null;
        if (is_array($req)) {
            $fields = array_values($req);
        }

        if (isset($srv['CUSTOM']) && is_array($srv['CUSTOM'])) {
            $fields[] = ['CUSTOM' => $srv['CUSTOM']];
        }

        return $fields;
    }

    private function toFloat($value): float
    {
        if ($value === null) return 0.0;
        if (is_int($value) || is_float($value)) return (float)$value;

        $str = trim((string)$value);
        $str = str_replace([',', '$', 'USD', 'usd'], '', $str);
        $str = preg_replace('/[^0-9\.\-]/', '', $str) ?? '';

        return is_numeric($str) ? (float)$str : 0.0;
    }

    private function normalizeInfo($info): ?string
    {
        if ($info === null) return null;
        $s = trim((string)$info);
        return $s === '' ? null : $s;
    }
}
