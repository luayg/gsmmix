<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use Illuminate\Support\Facades\Http;

class DhruAdapter
{
    protected ApiProvider $provider;

    public function __construct(ApiProvider $provider)
    {
        $this->provider = $provider;
    }

    protected function post(array $payload): array
    {
        $url = rtrim($this->provider->url, '/') . '/api/index.php';

        $payload = array_merge([
            'username' => $this->provider->username,
            'apiaccesskey' => $this->provider->api_key,
        ], $payload);

        $res = Http::asForm()->timeout(60)->post($url, $payload);
        return $res->json() ?: [];
    }

    public function accountInfo(): array
    {
        return $this->post(['request' => 'accountinfo']);
    }

    public function imeiServiceList(): array
    {
        return $this->post(['request' => 'imeiservicelist']);
    }

    public function serverServiceList(): array
    {
        return $this->post(['request' => 'serverservicelist']);
    }

    public function fileServiceList(): array
    {
        return $this->post(['request' => 'fileservicelist']);
    }
}
