<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteFileService;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Services\Api\GsmhubClient;
use App\Services\Providers\ProviderAdapterInterface;
use Illuminate\Support\Facades\DB;

class GsmhubAdapter implements ProviderAdapterInterface
{
    public function type(): string
    {
        return 'gsmhub';
    }

    public function supportsCatalog(string $kind): bool
    {
        return in_array($kind, ['imei', 'server', 'file'], true);
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        $client = GsmhubClient::fromProvider($provider);
        $data = $client->accountInfo();

        // imei.us عادة يرجع SUCCESS -> AccountInfo / AccoutInfo وبداخلها credit/creditraw
        $raw =
            data_get($data, 'SUCCESS.0.AccoutInfo.creditraw') ??
            data_get($data, 'SUCCESS.0.AccoutInfo.credit') ??
            data_get($data, 'SUCCESS.0.AccountInfo.creditraw') ??
            data_get($data, 'SUCCESS.0.AccountInfo.credit') ??
            $this->deepFind($data, ['creditraw','creditRaw','CREDITRAW','credit','CREDIT']);

        return $this->toFloat($raw);
    }

    public function syncCatalog(ApiProvider $provider, string $kind): int
    {
        $client = GsmhubClient::fromProvider($provider);

        return DB::transaction(function () use ($provider, $kind, $client) {
            $data = match ($kind) {
                'imei' => $client->imeiServiceList(),
                'server' => $client->serverServiceList(),
                'file' => $client->fileServiceList(),
                default => [],
            };

            // أغلب هذه الـ APIs ترجع LIST داخل SUCCESS.0.LIST
            $list = data_get($data, 'SUCCESS.0.LIST', []);
            if (!is_array($list)) $list = [];

            // بعضهم يرجع list مباشرة
            if (empty($list)) {
                $list = $this->deepFind($data, ['LIST','list']) ?? [];
                if (!is_array($list)) $list = [];
            }

            $seen = [];
            $count = 0;

            foreach ($list as $groupKey => $group) {
                if (!is_array($group)) continue;

                $groupName = (string)($group['GROUPNAME'] ?? $groupKey ?? '');
                $services = $group['SERVICES'] ?? $group['services'] ?? [];
                if (!is_array($services)) continue;

                foreach ($services as $srvKey => $srv) {
                    if (!is_array($srv)) continue;

                    $remoteId = (string)($srv['SERVICEID'] ?? $srv['serviceid'] ?? $srv['ID'] ?? $srvKey ?? '');
                    if ($remoteId === '') continue;

                    $seen[] = $remoteId;

                    $payload = [
                        'api_provider_id' => $provider->id,
                        'remote_id'       => $remoteId,
                        'name'            => (string)($srv['SERVICENAME'] ?? $srv['servicename'] ?? $srv['NAME'] ?? ''),
                        'group_name'      => $groupName ?: null,
                        'price'           => $this->toFloat($srv['CREDIT'] ?? $srv['credit'] ?? 0),
                        'time'            => $this->clean((string)($srv['TIME'] ?? $srv['time'] ?? '')),
                        'info'            => $this->clean((string)($srv['INFO'] ?? $srv['info'] ?? $srv['DESCRIPTION'] ?? $srv['description'] ?? '')),
                        'additional_data' => $srv,
                        'additional_fields' => $this->extractAdditionalFields($srv),
                        'params'          => ['CUSTOM' => $srv['CUSTOM'] ?? null],
                    ];

                    if ($kind === 'imei') {
                        $payload = array_merge($payload, [
                            'network'   => $this->requires($srv['Requires.Network'] ?? null),
                            'mobile'    => $this->requires($srv['Requires.Mobile'] ?? null),
                            'provider'  => $this->requires($srv['Requires.Provider'] ?? null),
                            'pin'       => $this->requires($srv['Requires.PIN'] ?? null),
                            'kbh'       => $this->requires($srv['Requires.KBH'] ?? null),
                            'mep'       => $this->requires($srv['Requires.MEP'] ?? null),
                            'prd'       => $this->requires($srv['Requires.PRD'] ?? null),
                            'type'      => $this->requires($srv['Requires.Type'] ?? null),
                            'locks'     => $this->requires($srv['Requires.Locks'] ?? null),
                            'reference' => $this->requires($srv['Requires.Reference'] ?? null),
                            'udid'      => $this->requires($srv['Requires.UDID'] ?? null),
                            'serial'    => $this->requires($srv['Requires.SN'] ?? null),
                            'secro'     => $this->requires($srv['Requires.SecRO'] ?? null),
                        ]);

                        RemoteImeiService::updateOrCreate(
                            ['api_provider_id' => $provider->id, 'remote_id' => $remoteId],
                            $payload
                        );
                    } elseif ($kind === 'server') {
                        RemoteServerService::updateOrCreate(
                            ['api_provider_id' => $provider->id, 'remote_id' => $remoteId],
                            $payload
                        );
                    } else { // file
                        $payload['allowed_extensions'] = $this->clean((string)($srv['ALLOW_EXTENSION'] ?? $srv['allow_extension'] ?? ''));
                        RemoteFileService::updateOrCreate(
                            ['api_provider_id' => $provider->id, 'remote_id' => $remoteId],
                            $payload
                        );
                    }

                    $count++;
                }
            }

            // cleanup
            if (!empty($seen)) {
                if ($kind === 'imei') {
                    RemoteImeiService::where('api_provider_id', $provider->id)->whereNotIn('remote_id', $seen)->delete();
                } elseif ($kind === 'server') {
                    RemoteServerService::where('api_provider_id', $provider->id)->whereNotIn('remote_id', $seen)->delete();
                } else {
                    RemoteFileService::where('api_provider_id', $provider->id)->whereNotIn('remote_id', $seen)->delete();
                }
            }

            return $count;
        });
    }

    private function extractAdditionalFields(array $srv): array
    {
        // نفس فلسفة النظام عندك: حاول Requires.Custom / CustomFields / CUSTOM
        $candidates = [
            $srv['Requires.Custom'] ?? null,
            $srv['CustomFields'] ?? null,
            $srv['custom_fields'] ?? null,
            $srv['CUSTOM']['fields'] ?? null,
            $srv['CUSTOM']['FIELDS'] ?? null,
            $srv['CUSTOM'] ?? null,
        ];

        foreach ($candidates as $raw) {
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $raw = $decoded;
            }
            if (is_array($raw) && !empty($raw)) {
                return array_is_list($raw) ? $raw : [];
            }
        }

        return [];
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
}