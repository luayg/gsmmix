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
        // ممكن تساعد ضد بعض WAF، لكنها لن تتجاوز Cloudflare challenge الحقيقي
        return Http::withHeaders([
            'Accept'          => 'application/json,text/plain,*/*',
            'Auth-Key'        => $this->authKey(),
            'User-Agent'      => 'GsmMix/1.0 (+Laravel WebX Client)',
            'Accept-Language' => 'en-US,en;q=0.9,ar;q=0.8',
        ])
            ->timeout(60)
            ->retry(2, 500);
    }

    public function url(string $route): string
    {
        $base = rtrim($this->apiBase(), '/');
        $route = trim($route, '/');

        return $route === '' ? ($base . '/') : ($base . '/' . $route);
    }

    /**
     * Unified request returning decoded JSON array or throws RuntimeException.
     * - never uses ->throw() (حتى لا ترجع رسالة Laravel الافتراضية بدون سياق)
     * - includes rich debugging info when 5xx/HTML occurs
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
                $body = (string) $resp->body();
                throw new \RuntimeException($this->formatHttpFailure($url, $method, $params, $resp->status(), $body, $resp->headers()));
            }

            $data = $resp->json();
            if (!is_array($data)) {
                $body = (string) $resp->body();
                throw new \RuntimeException($this->formatInvalidJson($url, $method, $params, $resp->status(), $body, $resp->headers()));
            }

            if (!empty($data['errors'])) {
                $msg = $this->flattenErrors($data['errors']);
                throw new \RuntimeException($this->formatApiErrors($url, $method, $params, $msg, $data));
            }

            return $data;
        } catch (RequestException $e) {
            // لو في مكان ما رمى RequestException، نرجع برسالة غنية
            $status = 0;
            $body = '';
            $headers = [];

            try {
                if ($e->response) {
                    $status = (int) $e->response->status();
                    $body = (string) $e->response->body();
                    $headers = (array) $e->response->headers();
                }
            } catch (\Throwable $ignored) {}

            throw new \RuntimeException(
                "WebX RequestException [" . get_class($e) . "]: " . $this->formatHttpFailure($url, $method, $params, $status, $body, $headers)
            );
        } catch (ConnectionException $e) {
            throw new \RuntimeException(
                "WebX ConnectionException [" . get_class($e) . "]: {$e->getMessage()} | url={$url} | ctx=" . $this->ctx()
            );
        } catch (\Throwable $e) {
            // أي شيء آخر
            throw new \RuntimeException(
                "WebX Throwable [" . get_class($e) . "]: {$e->getMessage()} | url={$url} | ctx=" . $this->ctx()
            );
        }
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

    private function ctx(): string
    {
        $apiPath = (string) data_get($this->provider, 'params.api_path', 'api');
        $authMode = (string) data_get($this->provider, 'params.auth_mode', 'bcrypt');

        return json_encode([
            'provider_id' => (int) ($this->provider->id ?? 0),
            'type' => (string) ($this->provider->type ?? ''),
            'url' => (string) ($this->provider->url ?? ''),
            'api_path' => $apiPath,
            'auth_mode' => $authMode,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function shortenHeaders(array $headers): array
    {
        // نختصر الهيدرز للعرض: بعض الهيدرز كبيرة
        $keep = ['server', 'date', 'content-type', 'cf-ray', 'cf-cache-status', 'set-cookie', 'location'];
        $out = [];
        foreach ($headers as $k => $v) {
            $kl = strtolower((string)$k);
            if (in_array($kl, $keep, true)) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private function sniffHtmlBlock(string $body): ?string
    {
        $b = strtolower($body);

        // مؤشرات Cloudflare/WAF شائعة
        if (str_contains($b, 'cloudflare') || str_contains($b, 'cf-ray') || str_contains($b, 'attention required') || str_contains($b, 'just a moment')) {
            return 'Detected Cloudflare/WAF HTML challenge. Use provider API domain that does NOT sit behind Cloudflare challenge, or whitelist your server IP, or disable challenge for /api.';
        }

        // 503 من origin لكن HTML
        if (str_contains($b, '<!doctype html') || str_contains($b, '<html')) {
            return 'HTML response returned (not JSON). Likely provider is down, blocked, or URL/api_path points to a website page not API.';
        }

        return null;
    }

    private function formatHttpFailure(string $url, string $method, array $params, int $status, string $body, array $headers): string
    {
        $snippet = trim(substr($body, 0, 1200));
        $hint = $this->sniffHtmlBlock($body);

        $payload = [
            'url' => $url,
            'method' => $method,
            'status' => $status,
            'ctx' => json_decode($this->ctx(), true),
            'headers' => $this->shortenHeaders($headers),
            'hint' => $hint,
            'body_snippet' => $snippet,
            // لا نطبع authKey ولا api_key لأسباب أمان
            'params_keys' => array_keys($params),
        ];

        return 'WebX HTTP failure: ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function formatInvalidJson(string $url, string $method, array $params, int $status, string $body, array $headers): string
    {
        $snippet = trim(substr($body, 0, 1200));
        $hint = $this->sniffHtmlBlock($body);

        $payload = [
            'url' => $url,
            'method' => $method,
            'status' => $status,
            'ctx' => json_decode($this->ctx(), true),
            'headers' => $this->shortenHeaders($headers),
            'hint' => $hint,
            'body_snippet' => $snippet,
            'params_keys' => array_keys($params),
        ];

        return 'WebX invalid JSON: ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function formatApiErrors(string $url, string $method, array $params, string $msg, array $data): string
    {
        $payload = [
            'url' => $url,
            'method' => $method,
            'ctx' => json_decode($this->ctx(), true),
            'error' => $msg,
            'api_errors' => $data['errors'] ?? null,
            'params_keys' => array_keys($params),
        ];

        return 'WebX API errors: ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}