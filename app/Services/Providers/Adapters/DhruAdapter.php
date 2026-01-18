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
        $client = new DhruClient((string)$provider->url, (string)($provider->username ?? ''), (string)($provider->api_key ?? ''));
        $info = $client->accountInfo();

        // DhruClient::accountInfo يرجع credits جاهزة
        return (float)($info['credits'] ?? 0.0);
    }

    public function syncCatalog(ApiProvider $provider, string $serviceType): int
    {
        $serviceType = strtolower($serviceType);

        $client = new DhruClient((string)$provider->url, (string)($provider->username ?? ''), (string)($provider->api_key ?? ''));

        $rows = [];

        if ($serviceType === 'imei') {
            $services = $client->imeiServices();
            foreach ($services as $srv) {
                $remoteId = (string)($srv['SERVICEID'] ?? '');
                if ($remoteId === '') continue;

                $rows[] = [
                    'api_id'            => $provider->id,
                    'remote_id'         => $remoteId,
                    'name'              => (string)($srv['SERVICENAME'] ?? ''),
                    'group_name'        => (string)($srv['group'] ?? ''),
                    'price'             => (float)($srv['CREDIT'] ?? 0),
                    'time'              => (string)($srv['TIME'] ?? ''),
                    'info'              => (string)($srv['INFO'] ?? ''),
                    'min_qty'           => isset($srv['MINQNT']) ? (string)$srv['MINQNT'] : null,
                    'max_qty'           => isset($srv['MAXQNT']) ? (string)$srv['MAXQNT'] : null,
                    'credit_groups'     => isset($srv['CREDITGROUPS']) ? json_encode($srv['CREDITGROUPS']) : null,
                    'additional_fields' => isset($srv['ADDFIELDS'])    ? json_encode($srv['ADDFIELDS'])    : null,
                    'additional_data'   => json_encode($srv, JSON_UNESCAPED_UNICODE),
                    'params'            => isset($srv['PARAMS'])       ? json_encode($srv['PARAMS'])       : null,

                    // Flags (إن كانت الأعمدة موجودة في جدولك)
                    'network'           => (int)!!($srv['Requires.Network']   ?? 0),
                    'mobile'            => (int)!!($srv['Requires.Mobile']    ?? 0),
                    'provider'          => (int)!!($srv['Requires.Provider']  ?? 0),
                    'pin'               => (int)!!($srv['Requires.PIN']       ?? 0),
                    'kbh'               => (int)!!($srv['Requires.KBH']       ?? 0),
                    'mep'               => (int)!!($srv['Requires.MEP']       ?? 0),
                    'prd'               => (int)!!($srv['Requires.PRD']       ?? 0),
                    'type'              => (int)!!($srv['Requires.Type']      ?? 0),
                    'locks'             => (int)!!($srv['Requires.Locks']     ?? 0),
                    'reference'         => (int)!!($srv['Requires.Reference'] ?? 0),
                    'udid'              => (int)!!($srv['Requires.UDID']      ?? 0),
                    'serial'            => (int)!!($srv['Requires.SN']        ?? 0),
                    'secro'             => (int)!!($srv['Requires.SecRO']     ?? 0),

                    'updated_at'        => now(),
                    'created_at'        => now(),
                ];
            }

            return $this->bulkUpsert((new RemoteImeiService)->getTable(), $rows);
        }

        if ($serviceType === 'server') {
            $services = $client->serverServices();
            foreach ($services as $srv) {
                $remoteId = (string)($srv['SERVICEID'] ?? '');
                if ($remoteId === '') continue;

                $rows[] = [
                    'api_id'            => $provider->id,
                    'remote_id'         => $remoteId,
                    'name'              => (string)($srv['SERVICENAME'] ?? ''),
                    'group_name'        => (string)($srv['group'] ?? ''),
                    'price'             => (float)($srv['CREDIT'] ?? 0),
                    'time'              => (string)($srv['TIME'] ?? ''),
                    'info'              => (string)($srv['INFO'] ?? ''),
                    'min_qty'           => isset($srv['MINQNT']) ? (string)$srv['MINQNT'] : null,
                    'max_qty'           => isset($srv['MAXQNT']) ? (string)$srv['MAXQNT'] : null,
                    'credit_groups'     => isset($srv['CREDITGROUPS']) ? json_encode($srv['CREDITGROUPS']) : null,
                    'additional_fields' => isset($srv['ADDFIELDS'])    ? json_encode($srv['ADDFIELDS'])    : null,
                    'additional_data'   => json_encode($srv, JSON_UNESCAPED_UNICODE),
                    'params'            => isset($srv['PARAMS'])       ? json_encode($srv['PARAMS'])       : null,
                    'updated_at'        => now(),
                    'created_at'        => now(),
                ];
            }

            return $this->bulkUpsert((new RemoteServerService)->getTable(), $rows);
        }

        // file
        $services = $client->fileServices();
        foreach ($services as $srv) {
            $remoteId = (string)($srv['SERVICEID'] ?? '');
            if ($remoteId === '') continue;

            $rows[] = [
                'api_id'             => $provider->id,
                'remote_id'          => $remoteId,
                'name'               => (string)($srv['SERVICENAME'] ?? ''),
                'group_name'         => (string)($srv['group'] ?? ''),
                'price'              => (float)($srv['CREDIT'] ?? 0),
                'time'               => (string)($srv['TIME'] ?? ''),
                'info'               => (string)($srv['INFO'] ?? ''),
                'allowed_extensions' => (string)($srv['ALLOW_EXTENSION'] ?? ''),
                'additional_fields'  => isset($srv['ADDFIELDS']) ? json_encode($srv['ADDFIELDS']) : null,
                'additional_data'    => json_encode($srv, JSON_UNESCAPED_UNICODE),
                'params'             => isset($srv['PARAMS']) ? json_encode($srv['PARAMS']) : null,
                'updated_at'         => now(),
                'created_at'         => now(),
            ];
        }

        return $this->bulkUpsert((new RemoteFileService)->getTable(), $rows);
    }

    protected function bulkUpsert(string $table, array $rows): int
    {
        $rows = array_values(array_filter($rows));
        if (!$rows) return 0;

        // نفس فكرة GSMHub upsert :contentReference[oaicite:8]{index=8}
        $uniqueBy = ['api_id', 'remote_id'];

        // نحدث كل الأعمدة ما عدا created_at (نتركها أول مرة فقط)
        $update = array_keys($rows[0]);
        $update = array_values(array_diff($update, ['created_at']));

        DB::table($table)->upsert($rows, $uniqueBy, $update);
        return count($rows);
    }
}
