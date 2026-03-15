<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use Illuminate\Support\Facades\Http;

class SimpleLinkOrderGateway
{
    private function providerParams(ApiProvider $provider): array
    {
        $params = $provider->params ?? [];
        if (is_string($params)) {
            $decoded = json_decode($params, true);
            $params = is_array($decoded) ? $decoded : [];
        }

        return is_array($params) ? $params : [];
    }

    private function endpoint(ApiProvider $provider): string
    {
        return trim((string)($provider->url ?? ''));
    }

    private function method(ApiProvider $provider): string
    {
        $params = $this->providerParams($provider);
        $method = strtoupper(trim((string)($params['method'] ?? 'POST')));

        return in_array($method, ['GET', 'POST'], true) ? $method : 'POST';
    }

    private function mainFieldName(ApiProvider $provider): string
    {
        $params = $this->providerParams($provider);
        $main = trim((string)($params['main_field'] ?? 'imei'));

        return $main !== '' ? $main : 'imei';
    }

    private function resolvePrimaryValue(ImeiOrder $order, string $mainField): string
    {
        $device = trim((string)($order->device ?? ''));
        if ($device !== '') {
            return $device;
        }

        $params = $order->params ?? [];
        if (is_string($params)) {
            $decoded = json_decode($params, true);
            $params = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($params)) {
            $params = [];
        }

        $fields = $params['fields'] ?? $params['required'] ?? [];
        if (!is_array($fields)) {
            $fields = [];
        }

        if (isset($fields[$mainField]) && trim((string)$fields[$mainField]) !== '') {
            return trim((string)$fields[$mainField]);
        }

        foreach ($fields as $v) {
            $value = trim((string)$v);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function splitUrlAndQuery(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            return ['', []];
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return [$url, []];
        }

        $base = '';
        if (!empty($parts['scheme'])) {
            $base .= $parts['scheme'] . '://';
        }
        if (!empty($parts['user'])) {
            $base .= $parts['user'];
            if (!empty($parts['pass'])) {
                $base .= ':' . $parts['pass'];
            }
            $base .= '@';
        }
        if (!empty($parts['host'])) {
            $base .= $parts['host'];
        }
        if (!empty($parts['port'])) {
            $base .= ':' . $parts['port'];
        }
        $base .= $parts['path'] ?? '';

        $queryParams = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
            if (!is_array($queryParams)) {
                $queryParams = [];
            }
        }

        return [$base !== '' ? $base : $url, $queryParams];
    }

    private function buildPayload(ApiProvider $provider, ImeiOrder $order): array
    {
        $mainField = $this->mainFieldName($provider);
        $value = $this->resolvePrimaryValue($order, $mainField);

        [, $queryParams] = $this->splitUrlAndQuery($this->endpoint($provider));

        $payload = is_array($queryParams) ? $queryParams : [];
        $payload[$mainField] = $value;

        return $payload;
    }

    private function shortMessageFromBody(string $body): string
    {
        $text = trim(strip_tags($body));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return 'OK';
        }

        if (mb_strlen($text) > 180) {
            return mb_substr($text, 0, 180) . '...';
        }

        return $text;
    }

    private function replyHtmlFromBody(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '<div style="white-space:pre-wrap;">OK</div>';
        }

        $looksHtml = stripos($trimmed, '<div') !== false
            || stripos($trimmed, '<p') !== false
            || stripos($trimmed, '<br') !== false
            || stripos($trimmed, '<table') !== false
            || stripos($trimmed, '<img') !== false
            || stripos($trimmed, '<span') !== false
            || stripos($trimmed, '<b') !== false
            || stripos($trimmed, '<strong') !== false;

        if ($looksHtml) {
            return $trimmed;
        }

        return '<div style="white-space:pre-wrap;">' . e($trimmed) . '</div>';
    }

    private function successResult(string $url, string $method, array $payload, int $httpStatus, string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            $body = 'OK';
        }

        return [
            'ok' => true,
            'retryable' => false,
            'status' => 'success',
            'remote_id' => null,
            'request' => [
                'url' => $url,
                'method' => $method,
                'params' => $payload,
                'http_status' => $httpStatus,
            ],
            'response_raw' => [
                'raw' => $body,
                'http_status' => $httpStatus,
            ],
            'response_ui' => [
                'type' => 'success',
                'message' => $this->shortMessageFromBody($body),
                'result_text' => $body,
                'provider_reply_html' => $this->replyHtmlFromBody($body),
            ],
        ];
    }

    private function rejectResult(string $url, string $method, array $payload, int $httpStatus, string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            $body = 'REQUEST REJECTED';
        }

        return [
            'ok' => false,
            'retryable' => false,
            'status' => 'rejected',
            'remote_id' => null,
            'request' => [
                'url' => $url,
                'method' => $method,
                'params' => $payload,
                'http_status' => $httpStatus,
            ],
            'response_raw' => [
                'raw' => $body,
                'http_status' => $httpStatus,
            ],
            'response_ui' => [
                'type' => 'error',
                'message' => $this->shortMessageFromBody($body),
                'result_text' => $body,
                'provider_reply_html' => $this->replyHtmlFromBody($body),
            ],
        ];
    }

    private function waitingResult(string $url, string $method, array $payload, int $httpStatus, string $body, string $fallback = 'Temporary provider error, queued.'): array
    {
        $body = trim($body);
        $message = $body !== '' ? $this->shortMessageFromBody($body) : $fallback;

        return [
            'ok' => false,
            'retryable' => true,
            'status' => 'waiting',
            'remote_id' => null,
            'request' => [
                'url' => $url,
                'method' => $method,
                'params' => $payload,
                'http_status' => $httpStatus,
            ],
            'response_raw' => [
                'raw' => $body !== '' ? $body : $fallback,
                'http_status' => $httpStatus,
            ],
            'response_ui' => [
                'type' => 'queued',
                'message' => $message,
                'result_text' => $body !== '' ? $body : $fallback,
                'provider_reply_html' => $this->replyHtmlFromBody($body !== '' ? $body : $fallback),
            ],
        ];
    }

    public function placeImeiOrder(ApiProvider $provider, ImeiOrder $order): array
    {
        $rawUrl = $this->endpoint($provider);
        $method = $this->method($provider);
        $mainField = $this->mainFieldName($provider);
        [$baseUrl] = $this->splitUrlAndQuery($rawUrl);
        $payload = $this->buildPayload($provider, $order);

        if ($baseUrl === '') {
            return $this->rejectResult($baseUrl, $method, $payload, 0, 'SIMPLE LINK URL IS EMPTY');
        }

        if (trim((string)($payload[$mainField] ?? '')) === '') {
            return $this->rejectResult($baseUrl, $method, $payload, 400, 'MISSING MAIN FIELD VALUE');
        }

        try {
            $client = Http::timeout(60)->retry(1, 500);

            $response = $method === 'GET'
                ? $client->get($baseUrl, $payload)
                : $client->asForm()->post($baseUrl, $payload);

            $status = (int)$response->status();
            $body = (string)$response->body();

            if ($response->successful()) {
                return $this->successResult($baseUrl, $method, $payload, $status, $body);
            }

            if ($status >= 500 || $status === 0) {
                return $this->waitingResult($baseUrl, $method, $payload, $status, $body, 'SIMPLE LINK PROVIDER DOWN');
            }

            return $this->rejectResult($baseUrl, $method, $payload, $status, $body);
        } catch (\Throwable $e) {
            return $this->waitingResult($baseUrl, $method, $payload, 0, $e->getMessage(), 'SIMPLE LINK CONNECTION ERROR');
        }
    }

    public function placeServerOrder(ApiProvider $provider, ServerOrder $order): array
    {
        return [
            'ok' => false,
            'retryable' => false,
            'status' => 'rejected',
            'remote_id' => null,
            'request' => [
                'url' => $this->endpoint($provider),
                'method' => $this->method($provider),
                'params' => [],
                'http_status' => 0,
            ],
            'response_raw' => [
                'raw' => 'Simple Link currently supports IMEI orders only',
                'http_status' => 0,
            ],
            'response_ui' => [
                'type' => 'error',
                'message' => 'Simple Link currently supports IMEI orders only',
            ],
        ];
    }

    public function placeFileOrder(ApiProvider $provider, FileOrder $order): array
    {
        return [
            'ok' => false,
            'retryable' => false,
            'status' => 'rejected',
            'remote_id' => null,
            'request' => [
                'url' => $this->endpoint($provider),
                'method' => $this->method($provider),
                'params' => [],
                'http_status' => 0,
            ],
            'response_raw' => [
                'raw' => 'Simple Link currently supports IMEI orders only',
                'http_status' => 0,
            ],
            'response_ui' => [
                'type' => 'error',
                'message' => 'Simple Link currently supports IMEI orders only',
            ],
        ];
    }
}