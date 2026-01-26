<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;
use App\Services\Api\DhruClient;
use App\Services\Providers\ProviderAdapterInterface;
use Illuminate\Support\Facades\DB;

class DhruAdapter implements ProviderAdapterInterface
{
    public function type(): string { return 'dhru'; }

    public function supportsCatalog(string $serviceType): bool
    {
        return in_array(strtolower($serviceType), ['imei','server','file'], true);
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        $client = $this->client($provider);
        $info = $client->accountInfo();
        return (float)($info['creditraw'] ?? 0.0);
    }

    public function syncCatalog(ApiProvider $provider, string $serviceType): int
    {
        $serviceType = strtolower($serviceType);

        if ($serviceType === 'file') {
            return $this->syncFileServices($provider);
        }

        // imei + server + remote are all coming from imeiservicelist (per docs)
        return $this->syncImeiAndServerServices($provider, $serviceType);
    }

    protected function client(ApiProvider $p): DhruClient
    {
        return new DhruClient((string)$p->url, (string)($p->username ?? ''), (string)($p->api_key ?? ''), 'JSON');
    }

    protected function syncImeiAndServerServices(ApiProvider $provider, string $wanted): int
    {
        $client = $this->client($provider);
        $list = $client->allServicesAndGroups();
        if (!$list) return 0;

        $imeiRows = [];
        $serverRows = [];

        foreach ($list as $groupName => $group) {
            if (!is_array($group)) continue;

            $gName = (string)($group['GROUPNAME'] ?? $groupName ?? '');
            $gType = strtoupper((string)($group['GROUPTYPE'] ?? ''));

            $services = $group['SERVICES'] ?? [];
            if (!is_array($services)) continue;

            foreach ($services as $k => $srv) {
                if (!is_array($srv)) continue;

                $srvType = strtoupper((string)($srv['SERVICETYPE'] ?? $gType));
                $remoteId = (string)($srv['SERVICEID'] ?? $k ?? '');
                if ($remoteId === '') continue;

                // DHRU types: IMEI / SERVER / REMOTE
                $normalized = match ($srvType) {
                    'IMEI'   => 'imei',
                    'SERVER' => 'server',
                    'REMOTE' => 'server', // في مشروعك نخزّن REMOTE ضمن server table (تقدر تغيرها لاحقًا)
                    default  => null,
                };

                if ($normalized === null) continue;
                if ($wanted !== $normalized) continue;

                $base = [
                    'api_provider_id'          => (int)$provider->id,
                    'remote_id'       => $remoteId,
                    'name'            => (string)($srv['SERVICENAME'] ?? ''),
                    'group_name'      => $gName,
                    'price'           => (float)($srv['CREDIT'] ?? 0),
                    'time'            => (string)($srv['TIME'] ?? ''),
                    'info'            => (string)($srv['INFO'] ?? ''),
                    'additional_data' => json_encode($srv, JSON_UNESCAPED_UNICODE),
                    'updated_at'      => now(),
                    'created_at'      => now(),
                ];

                // حقول مشتركة موجودة في DB عند كثير من نسخ DHRU
                $extra = [
                    'min_qty'           => isset($srv['MINQNT']) ? (string)$srv['MINQNT'] : null,
                    'max_qty'           => isset($srv['MAXQNT']) ? (string)$srv['MAXQNT'] : null,
                    'additional_fields' => isset($srv['Requires.Custom']) ? json_encode($srv['Requires.Custom'], JSON_UNESCAPED_UNICODE) : null,
                    'params'            => isset($srv['CUSTOM']) ? json_encode($srv['CUSTOM'], JSON_UNESCAPED_UNICODE) : null,
                ];

                if ($normalized === 'imei') {
                    $imeiRows[] = array_merge($base, $extra, [
                        'network'   => (int)($srv['Requires.Network']   ?? 0) !== 0,
                        'mobile'    => (int)($srv['Requires.Mobile']    ?? 0) !== 0,
                        'provider'  => (int)($srv['Requires.Provider']  ?? 0) !== 0,
                        'pin'       => (int)($srv['Requires.PIN']       ?? 0) !== 0,
                        'kbh'       => (int)($srv['Requires.KBH']       ?? 0) !== 0,
                        'mep'       => (int)($srv['Requires.MEP']       ?? 0) !== 0,
                        'prd'       => (int)($srv['Requires.PRD']       ?? 0) !== 0,
                        'type'      => (int)($srv['Requires.Type']      ?? 0) !== 0,
                        'locks'     => (int)($srv['Requires.Locks']     ?? 0) !== 0,
                        'reference' => (int)($srv['Requires.Reference'] ?? 0) !== 0,
                        'serial'    => (int)($srv['Requires.SN']        ?? 0) !== 0,
                        'secro'     => (int)($srv['Requires.SecRO']     ?? 0) !== 0,
                    ]);
                } else {
                    $serverRows[] = array_merge($base, $extra);
                }
            }
        }

        if ($wanted === 'imei') {
            return $this->bulkUpsert((new RemoteImeiService)->getTable(), $imeiRows);
        }
        return $this->bulkUpsert((new RemoteServerService)->getTable(), $serverRows);
    }

    protected function syncFileServices(ApiProvider $provider): int
    {
        $client = $this->client($provider);
        $list = $client->fileServiceList();
        if (!$list) return 0;

        $rows = [];

        foreach ($list as $groupName => $group) {
            if (!is_array($group)) continue;
            $gName = (string)($group['GROUPNAME'] ?? $groupName ?? '');
            $services = $group['SERVICES'] ?? [];
            if (!is_array($services)) continue;

            foreach ($services as $k => $srv) {
                if (!is_array($srv)) continue;
                $remoteId = (string)($srv['SERVICEID'] ?? $k ?? '');
                if ($remoteId === '') continue;

                $rows[] = [
                    'api_provider_id'             => (int)$provider->id,
                    'remote_id'          => $remoteId,
                    'name'               => (string)($srv['SERVICENAME'] ?? ''),
                    'group_name'         => $gName,
                    'price'              => (float)($srv['CREDIT'] ?? 0),
                    'time'               => (string)($srv['TIME'] ?? ''),
                    'info'               => (string)($srv['INFO'] ?? ''),
                    'allowed_extensions' => (string)($srv['ALLOW_EXTENSION'] ?? ''),
                    'additional_data'    => json_encode($srv, JSON_UNESCAPED_UNICODE),
                    'updated_at'         => now(),
                    'created_at'         => now(),
                ];
            }
        }

        return $this->bulkUpsert((new RemoteFileService)->getTable(), $rows);
    }

    protected function bulkUpsert(string $table, array $rows): int
    {
        $rows = array_values(array_filter($rows));
        if (!$rows) return 0;

        $uniqueBy = ['api_provider_id', 'remote_id'];
        $update = array_keys($rows[0]);
        $update = array_values(array_diff($update, ['created_at']));

        DB::table($table)->upsert($rows, $uniqueBy, $update);
        return count($rows);
    }
}
