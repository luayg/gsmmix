<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteFileService;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Services\Api\WebxClient;
use App\Services\Providers\ProviderAdapterInterface;
use Illuminate\Support\Facades\DB;

class WebxAdapter implements ProviderAdapterInterface
{
    public function type(): string
    {
        return 'webx';
    }

    public function supportsCatalog(string $kind): bool
    {
        return in_array($kind, ['imei', 'server', 'file'], true);
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        $info = WebxClient::fromProvider($provider)->request('GET', '', [], false);
        $balance = $info['balance'] ?? 0;
        return $this->toFloat($balance);
    }

    public function syncCatalog(ApiProvider $provider, string $kind): int
    {
        if (!$this->supportsCatalog($kind)) return 0;

        $route = match ($kind) {
            'imei' => 'imei-services',
            'server' => 'server-services',
            'file' => 'file-services',
            default => '',
        };

        // ✅ unified WebX client (supports params.api_path + params.auth_mode)
        $services = WebxClient::fromProvider($provider)->request('GET', $route, [], false);

        if (!is_array($services)) return 0;

        return DB::transaction(function () use ($provider, $kind, $services) {
            $seen = [];
            $count = 0;

            foreach ($services as $srv) {
                if (!is_array($srv)) continue;

                $remoteId = (string)($srv['id'] ?? '');
                if ($remoteId === '') continue;

                $seen[] = $remoteId;

                $basePayload = [
                    'api_provider_id'   => $provider->id,
                    'remote_id'         => $remoteId,
                    'name'              => (string)($srv['name'] ?? ''),
                    'group_name'        => null,
                    'price'             => $this->toFloat($srv['credits'] ?? 0),
                    'time'              => $this->cleanStr($srv['time'] ?? null),
                    'info'              => $this->cleanStr($srv['info'] ?? null),
                    'additional_data'   => $srv,
                    'additional_fields' => is_array($srv['fields'] ?? null) ? ($srv['fields'] ?? []) : [],
                    'params'            => [
                        'main_field'        => $srv['main_field'] ?? null,
                        'calculation_type'  => $srv['type'] ?? null,
                        'allow_duplicates'  => $srv['allow_duplicates'] ?? null,
                    ],
                ];

                if ($kind === 'imei') {
                    RemoteImeiService::updateOrCreate(
                        ['api_provider_id' => $provider->id, 'remote_id' => $remoteId],
                        array_merge($basePayload, [
                            // WebX payload لا يذكر Requires.* مثل DHRU، فبنتركها false
                            'network' => false,
                            'mobile' => false,
                            'provider' => false,
                            'pin' => false,
                            'kbh' => false,
                            'mep' => false,
                            'prd' => false,
                            'type' => false,
                            'locks' => false,
                            'reference' => false,
                            'udid' => false,
                            'serial' => false,
                            'secro' => false,
                        ])
                    );
                } elseif ($kind === 'server') {
                    RemoteServerService::updateOrCreate(
                        ['api_provider_id' => $provider->id, 'remote_id' => $remoteId],
                        $basePayload
                    );
                } else { // file
                    $allowed = data_get($srv, 'main_field.rules.allowed');
                    RemoteFileService::updateOrCreate(
                        ['api_provider_id' => $provider->id, 'remote_id' => $remoteId],
                        array_merge($basePayload, [
                            // ✅ canonical name
                            'allowed_extensions' => is_array($allowed) ? implode(',', $allowed) : $this->cleanStr($allowed),
                        ])
                    );
                }

                $count++;
            }

            // Cleanup removed services
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

    private function toFloat($value): float
    {
        if ($value === null) return 0.0;
        if (is_int($value) || is_float($value)) return (float)$value;

        $s = trim((string)$value);
        $s = str_replace([',', '$', 'USD', 'usd', ' '], '', $s);
        $s = preg_replace('/[^0-9\.\-]/', '', $s) ?? '';
        return is_numeric($s) ? (float)$s : 0.0;
    }

    private function cleanStr($value): ?string
    {
        $s = trim((string)($value ?? ''));
        return $s === '' ? null : $s;
    }
}