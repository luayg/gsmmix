<?php

namespace App\Services\Api;

use App\Models\ApiProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class WebxClient
{
    public function __construct(private ApiProvider $provider) {}

    public static function fromProvider(ApiProvider $provider): self
    {
        return new self($provider);
    }

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
            'Accept'          => 'application/json,text/plain,*/*',
            'Auth-Key'        => $this->authKey(),
            'User-Agent'      => 'GsmMix/1.0 (+Laravel WebX Client)',
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->timeout(60)->retry(2, 500);
    }

    public function url(string $route): string
    {
        $base = rtrim($this->apiBase(), '/');
        $route = trim($route, '/');
        return $route === '' ? ($base . '/') : ($base . '/' . $route);
    }

    /**
     * Returns decoded JSON array or throws RuntimeException with SHORT message.
     */
    public function request(string $method, string $route, array $params = [], bool $asForm = false): array
    {
        $method = strtoupper($method);
        $url = $this->url($route);

        // WebX expects username
        $params['username'] = (string) $this->provider->username;

        try {
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
                $ct = strtolower((string) ($resp->header('content-type') ?? ''));
                $body = (string) $resp->body();
                throw new \RuntimeException($this->shortHttpMessage($resp->status(), $ct, $body));
            }

            $data = $resp->json();
            if (!is_array($data)) {
                $ct = strtolower((string) ($resp->header('content-type') ?? ''));
                $body = (string) $resp->body();
                throw new \RuntimeException($this->shortHttpMessage($resp->status(), $ct, $body));
            }

            if (!empty($data['errors'])) {
                // Keep it short as well
                throw new \RuntimeException('PROVIDER ERROR');
            }

            return $data;
        } catch (RequestException $e) {
            $status = 0;
            $ct = '';
            $body = '';

            try {
                if ($e->response) {
                    $status = (int) $e->response->status();
                    $ct = strtolower((string) ($e->response->header('content-type') ?? ''));
                    $body = (string) $e->response->body();
                }
            } catch (\Throwable $ignored) {}

            throw new \RuntimeException($this->shortHttpMessage($status, $ct, $body));
        } catch (ConnectionException $e) {
            throw new \RuntimeException('IP BLOCKED - Reset Provider IP');
        } catch (\Throwable $e) {
            $msg = trim((string) $e->getMessage());
            if ($msg === '') {
                throw new \RuntimeException('PROVIDER ERROR');
            }
            if (stripos($msg, 'status code 503') !== false) {
                throw new \RuntimeException('IP BLOCKED - Reset Provider IP');
            }
            throw new \RuntimeException('PROVIDER ERROR');
        }
    }

    public function postMultipart(string $route, array $params, string $fieldName, string $rawBytes, string $filename)
    {
        $url = $this->url($route);
        $params['username'] = (string) $this->provider->username;

        return $this->client()
            ->asMultipart()
            ->attach($fieldName, $rawBytes, $filename)
            ->post($url, $params);
    }

    private function shortHttpMessage(int $status, string $contentType, string $body): string
    {
        $bodyL = strtolower($body);
        $isHtml = str_contains($contentType, 'text/html') || str_contains($bodyL, '<!doctype html') || str_contains($bodyL, '<html');

        // Your exact IP whitelist case: 503 HTML page
        if ($status === 503 && $isHtml) {
            return 'IP BLOCKED - Reset Provider IP';
        }

        // Generic: treat access denied as IP blocked (short)
        if (in_array($status, [401, 403], true)) {
            return 'IP BLOCKED - Reset Provider IP';
        }

        // Rate limit sometimes behaves like a block
        if ($status === 429) {
            return 'IP BLOCKED - Reset Provider IP';
        }

        // Any 5xx with HTML: block/unavailable -> same short msg
        if ($status >= 500 && $isHtml) {
            return 'IP BLOCKED - Reset Provider IP';
        }

        return 'PROVIDER ERROR';
    }
}