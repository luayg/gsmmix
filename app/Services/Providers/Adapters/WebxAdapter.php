<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteFileService;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Services\Api\WebxClient;
use App\Services\Providers\ProviderAdapterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

        $groupMap = $this->fetchPublicGroupMap($provider, $kind);

        return DB::transaction(function () use ($provider, $kind, $services, $groupMap) {
            $seen = [];
            $count = 0;

            foreach ($services as $srv) {
                if (!is_array($srv)) continue;

                $remoteId = (string)($srv['id'] ?? '');
                if ($remoteId === '') continue;

                $seen[] = $remoteId;
                $groupName = $this->resolveGroupName($srv, $groupMap);

                $basePayload = [
                    'api_provider_id'   => $provider->id,
                    'remote_id'         => $remoteId,
                    'name'              => (string)($srv['name'] ?? ''),
                    'group_name'        => $groupName,
                    'price'             => $this->toFloat($srv['credits'] ?? 0),
                    'time'              => $this->cleanStr($srv['time'] ?? null),
                    'info'              => $this->cleanStr($srv['info'] ?? null),
                    'additional_data'   => $srv,
                    'additional_fields' => is_array($srv['fields'] ?? null) ? ($srv['fields'] ?? []) : [],
                    'params'            => [
                        'main_field'        => $srv['main_field'] ?? null,
                        'calculation_type'  => $srv['type'] ?? null,
                        'allow_duplicates'  => $srv['allow_duplicates'] ?? null,
                        'group_name'        => $groupName,
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

    private function fetchPublicGroupMap(ApiProvider $provider, string $kind): array
    {
        $route = match ($kind) {
            'imei' => 'imei-services',
            'server' => 'server-services',
            'file' => 'file-services',
            default => null,
        };

        if ($route === null) {
            return [];
        }

        try {
            $url = rtrim((string) $provider->url, '/') . '/' . $route;
            $response = Http::withHeaders([
                'User-Agent' => 'GsmMix/1.0 (+Laravel WebX Group Sync)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])->timeout(30)->get($url);

            if (!$response->successful()) {
                return [];
            }

            return $this->parsePublicGroups((string) $response->body());
        } catch (\Throwable) {
            return [];
        }
    }

    private function parsePublicGroups(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $groups = [];

        foreach ($xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' service-group ')]") as $groupNode) {
            $title = null;
            $titleNode = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' title ')]", $groupNode)->item(0);

            if ($titleNode) {
                $title = $this->cleanStr($titleNode->textContent);
            }

            if ($title === null) {
                continue;
            }

            foreach ($xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' searchable ')]", $groupNode) as $serviceNode) {
                $serviceName = $this->cleanStr($serviceNode->textContent);
                if ($serviceName === null) {
                    continue;
                }

                $key = $this->normalizeServiceName($serviceName);
                if ($key !== '') {
                    $groups[$key] = $title;
                }
            }
        }

        return $groups;
    }

    private function resolveGroupName(array $service, array $groupMap): ?string
    {
        $candidates = [
            $service['group_name'] ?? null,
            $service['groupName'] ?? null,
            $service['group'] ?? null,
            $service['category'] ?? null,
            $service['service_group'] ?? null,
            $service['serviceGroup'] ?? null,
            data_get($service, 'group.name'),
            data_get($service, 'category.name'),
            data_get($service, 'main_field.group'),
            data_get($service, 'main_field.category'),
        ];

        foreach ($candidates as $candidate) {
            $value = $this->cleanStr($candidate);
            if ($value !== null) {
                return $value;
            }
        }

        $serviceName = $this->cleanStr($service['name'] ?? null);
        if ($serviceName !== null) {
            $key = $this->normalizeServiceName($serviceName);
            if ($key !== '' && isset($groupMap[$key])) {
                return $groupMap[$key];
            }
        }

        return null;
    }

    private function normalizeServiceName(string $name): string
    {
        $name = html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        return mb_strtolower(trim($name), 'UTF-8');
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