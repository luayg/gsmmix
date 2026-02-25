<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteFileService;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
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
        $info = $this->call($provider, ''); // GET {url}/api/
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

        $services = $this->call($provider, $route); // array of services

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
                    'api_provider_id' => $provider->id,
                    'remote_id' => $remoteId,
                    'name' => (string)($srv['name'] ?? ''),
                    'group_name' => null,
                    'price' => $this->toFloat($srv['credits'] ?? 0),
                    'time' => $this->cleanStr($srv['time'] ?? null),
                    'info' => $this->cleanStr($srv['info'] ?? null),
                    'additional_data' => $srv,
                    'additional_fields' => is_array($srv['fields'] ?? null) ? ($srv['fields'] ?? []) : [],
                    'params' => [
                        'main_field' => $srv['main_field'] ?? null,
                        'calculation_type' => $srv['type'] ?? null,
                        'allow_duplicates' => $srv['allow_duplicates'] ?? null,
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
                            'allowed_extensions' => is_array($allowed) ? implode(',', $allowed) : $this->cleanStr($allowed),
                        ])
                    );
                }

                $count++;
            }

            // Cleanup removed services (نفس فكرة DhruStyle) :contentReference[oaicite:6]{index=6}
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

    /* ============================
     * WebX client logic
     * ============================ */

    private function apiBase(ApiProvider $provider): string
    {
        // WebX library uses: $url.'/api/'.$route
        return rtrim((string)$provider->url, '/') . '/api';
    }

    private function call(ApiProvider $provider, string $route, array $params = [], string $method = 'GET'): mixed
    {
        $method = strtoupper($method);
        $url = rtrim($this->apiBase($provider), '/') . '/' . ltrim($route, '/');

        // The library sets username param always
        $params['username'] = (string)$provider->username;

        // Auth-Key is bcrypt hash of username+key
        $auth = password_hash((string)$provider->username . (string)$provider->api_key, PASSWORD_BCRYPT);

        $req = Http::withHeaders([
            'Accept' => 'application/json',
            'Auth-Key' => $auth,
        ])->timeout(60)->retry(2, 500);

        $resp = match ($method) {
            'POST' => $req->asForm()->post($url, $params),
            'DELETE' => $req->delete($url, $params),
            default => $req->get($url, $params),
        };

        $data = $resp->json();

        if (!is_array($data)) {
            throw new \RuntimeException('WebX: Invalid JSON response');
        }

        if (!empty($data['errors'])) {
            // same behavior as the WebX php library (throw on errors)
            $msg = $this->flattenErrors($data['errors']);
            throw new \RuntimeException($msg ?: 'WebX: API error');
        }

        return $data;
    }

    private function flattenErrors($errors): string
    {
        if (!is_array($errors) || empty($errors)) return 'could_not_connect_to_api';
        $messages = [];
        foreach ($errors as $error) {
            if (is_array($error)) $messages[] = implode(', ', $error);
            else $messages[] = (string)$error;
        }
        return implode(', ', $messages);
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