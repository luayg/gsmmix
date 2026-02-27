<?php

namespace App\Services\Api;

use App\Models\ApiProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WebxClient
{
    public function __construct(private ApiProvider $provider) {}

    public static function fromProvider(ApiProvider $provider): self
    {
        return new self($provider);
    }

    /**
     * Base URL:
     * - supports params.api_path (default "api")
     * - avoids double "api" if provider.url already ends with /api or /api/v1 ...
     */
    public function apiBase(): string
    {
        $apiPath = trim((string) data_get($this->provider, 'params.api_path', 'api'), '/');

        $base = rtrim((string) $this->provider->url, '/');

        // If already ends with "/api" or "/api/..."
        if (preg_match('~/(api)(/.*)?$~i', $base)) {
            return $base;
        }

        return $base . '/' . $apiPath;
    }

    /**
     * Auth-Key:
     * - supports params.auth_mode: bcrypt(default)/plain/md5/sha256
     *
     * NOTE:
     * bcrypt (password_hash) is non-deterministic by design.
     * If your WebX server expects deterministic signatures, use md5/sha256/plain.
     */
    public function authKey(): string
    {
        $mode = strtolower((string) data_get($this->provider, 'params.auth_mode', 'bcrypt'));
        $raw  = (string) $this->provider->username . (string) $this->provider->api_key;

        return match ($mode) {
            'plain'  => (string) $this->provider->api_key,
            'md5'    => md5($raw),
            'sha256' => hash('sha256', $raw),
            default  => password_hash($raw, PASSWORD_BCRYPT),
        };
    }

    public function client(): PendingRequest
    {
        return Http::withHeaders([
            'Accept'     => 'application/json',
            'Auth-Key'   => $this->authKey(),
            'User-Agent' => 'GsmMix/1.0 (+Laravel WebX Client)',
        ])->timeout(60)->retry(2, 500);
    }

    public function url(string $route): string
    {
        $base = rtrim($this->apiBase(), '/');
        $route = trim($route, '/');

        return $route === '' ? ($base . '/') : ($base . '/' . $route);
    }

    /**
     * Unified request returning decoded JSON array or throws RuntimeException.
     */
    public function request(string $method, string $route, array $params = [], bool $asForm = false): array
    {
        $method = strtoupper($method);
        $url = $this->url($route);

        // WebX expects username
        $params['username'] = (string) $this->provider->username;

        $req = $this->client();
        if ($asForm) {
            $req = $req->asForm();
        }

        $resp = match ($method) {
            'POST'   => $req->post($url, $params),
            'DELETE' => $req->delete($url, $params),
            default  => $req->get($url, $params),
        };

        if (!$resp->successful()) {
            $body = (string) $resp->body();
            $snippet = trim(substr($body, 0, 600));
            throw new \RuntimeException('WebX HTTP ' . $resp->status() . ': ' . ($snippet !== '' ? $snippet : 'empty_body'));
        }

        $data = $resp->json();
        if (!is_array($data)) {
            $body = (string) $resp->body();
            $snippet = trim(substr($body, 0, 600));
            throw new \RuntimeException('WebX: Invalid JSON. First bytes: ' . ($snippet !== '' ? $snippet : 'empty_body'));
        }

        if (!empty($data['errors'])) {
            $msg = $this->flattenErrors($data['errors']);
            throw new \RuntimeException($msg ?: 'WebX: API error');
        }

        return $data;
    }

    /**
     * Multipart POST helper (File Orders).
     */
    public function postMultipart(string $route, array $params, string $fieldName, string $rawBytes, string $filename)
    {
        $url = $this->url($route);

        $params['username'] = (string) $this->provider->username;

        return $this->client()
            ->asMultipart()
            ->attach($fieldName, $rawBytes, $filename)
            ->post($url, $params);
    }

    private function flattenErrors($errors): string
    {
        if (!is_array($errors) || empty($errors)) {
            return 'could_not_connect_to_api';
        }

        $messages = [];
        foreach ($errors as $error) {
            $messages[] = is_array($error) ? implode(', ', $error) : (string) $error;
        }

        return implode(', ', $messages);
    }
}