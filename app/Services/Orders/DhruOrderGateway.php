<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DhruOrderGateway
{
    private function endpoint(ApiProvider $p): string
    {
        $base = rtrim((string)$p->url, '/');
        if (str_ends_with($base, 'api/index.php')) return $base;
        return $base . '/api/index.php';
    }

    private function basePayload(ApiProvider $p, string $action): array
    {
        return [
            'username'      => (string)($p->username ?? ''),
            'apiaccesskey'  => (string)($p->api_key ?? ''),
            'requestformat' => 'JSON',
            'action'        => $action,
        ];
    }

    private function xmlEscape(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function normalizeUi(array $json): array
    {
        if (isset($json['SUCCESS'][0])) {
            $s0 = $json['SUCCESS'][0];
            return [
                'type' => 'success',
                'message' => $s0['MESSAGE'] ?? 'OK',
                'reference_id' => $s0['REFERENCEID'] ?? null,
            ];
        }

        if (isset($json['ERROR'][0])) {
            $e0 = $json['ERROR'][0];
            return [
                'type' => 'error',
                'message' => $e0['MESSAGE'] ?? 'Error',
            ];
        }

        return ['type' => 'unknown', 'message' => 'Unknown response'];
    }

    private function isRetryableErrorMessage(?string $msg): bool
    {
        if ($msg === null) return false;
        $m = mb_strtolower(trim($msg));

        $needles = [
            'ip', 'not allowed', 'whitelist', 'blocked', 'forbidden',
            'timeout', 'timed out',
            'connection', 'connect', 'refused', 'reset',
            'temporarily', 'temporary', 'try again',
            'maintenance', 'unavailable', 'service unavailable',
            'server busy', 'busy',
            'too many requests', 'rate limit', 'limit exceeded',
            'bad gateway', 'gateway', 'cloudflare',
            'dns', 'resolve', 'host',

            // Provider wallet/credits errors (wait + retry later)
            'insufficient', 'insufficent', 'not enough',
            'low balance', 'no balance', 'insufficient balance', 'balance low',
            'credit', 'credits', 'insufficient credits',
            'fund', 'funds', 'wallet',

            // Common API action mismatch responses
            'command not found', 'invalid action', 'unknown action',
        ];

        foreach ($needles as $n) {
            if (str_contains($m, $n)) return true;
        }

        return false;
    }

    private function isCommandNotFoundResult(array $result): bool
    {
        $raw = $result['response_raw'] ?? null;
        $msg = null;

        if (is_array($raw) && isset($raw['ERROR'][0]['MESSAGE'])) {
            $msg = (string)$raw['ERROR'][0]['MESSAGE'];
        }

        if (!$msg && isset($result['response_ui']['message'])) {
            $msg = (string)$result['response_ui']['message'];
        }

        $m = mb_strtolower(trim((string)$msg));
        if ($m === '') return false;

        return str_contains($m, 'command not found')
            || str_contains($m, 'invalid action')
            || str_contains($m, 'unknown action');
    }

    /**
     * Normalize REQUIRED fields:
     * - ensure array
     * - values => strings only (array/object => json)
     * - drop empty keys
     */
    private function normalizeRequiredFields($fields): array
    {
        if (!is_array($fields)) return [];

        $out = [];
        foreach ($fields as $k => $v) {
            $key = trim((string)$k);
            if ($key === '') continue;

            if (is_array($v) || is_object($v)) {
                $out[$key] = json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            } elseif ($v === null) {
                $out[$key] = '';
            } else {
                $out[$key] = (string)$v;
            }
        }
        return $out;
    }

    /**
     * Add compatibility aliases for REQUIRED keys from service custom_fields definitions.
     *
     * Example: if local key is service_fields_1 but field name/validation says email,
     * we also send REQUIRED[email] with same value.
     */
    private function enrichRequiredFieldAliases(array $fields, $service): array
    {
        if (empty($fields) || !$service) return $fields;

        $params = $service->params ?? [];
        if (is_string($params)) {
            $decoded = json_decode($params, true);
            $params = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($params)) return $fields;

        $customFields = $params['custom_fields'] ?? [];
        if (!is_array($customFields) || empty($customFields)) return $fields;

        foreach ($customFields as $def) {
            if (!is_array($def)) continue;

            $input = trim((string)($def['input'] ?? ''));
            if ($input === '' || !array_key_exists($input, $fields)) continue;

            $value = (string)$fields[$input];
            $name = trim((string)($def['name'] ?? ''));
            $validation = Str::lower(trim((string)($def['validation'] ?? '')));

            // Alias from field label/name
            if ($name !== '') {
                $slug = Str::snake(Str::of($name)->replaceMatches('/[^\pL\pN]+/u', ' ')->trim()->value());
                if ($slug !== '' && !array_key_exists($slug, $fields)) {
                    $fields[$slug] = $value;
                }
            }

            // Explicit email alias for providers expecting REQUIRED[email]
            $nameLc = Str::lower($name);
            if (
                $validation === 'email'
                || str_contains($nameLc, 'email')
                || str_contains($input, 'email')
            ) {
                if (!array_key_exists('email', $fields)) {
                    $fields['email'] = $value;
                }
            }
        }

        return $fields;
    }


    private function appendWellKnownFieldTags(string $xml, array $fields): string
    {
        $map = [
            'email' => 'EMAIL',
            'username' => 'USERNAME',
            'user_name' => 'USERNAME',
            'account' => 'ACCOUNT',
            'login' => 'LOGIN',
            'password' => 'PASSWORD',
        ];

        foreach ($map as $key => $tag) {
            if (!array_key_exists($key, $fields)) continue;

            $val = trim((string)$fields[$key]);
            if ($val === '') continue;

            // avoid duplicate tag append
            if (str_contains($xml, '<' . $tag . '>')) continue;

            $xml .= '<' . $tag . '>' . $this->xmlEscape($val) . '</' . $tag . '>';
        }

        return $xml;
    }


    private function shouldTryLegacyParameters(array $result): bool
    {
        $raw = $result['response_raw'] ?? null;
        $msg = '';

        if (is_array($raw) && isset($raw['ERROR'][0]['MESSAGE'])) {
            $msg = (string)$raw['ERROR'][0]['MESSAGE'];
        } elseif (isset($result['response_ui']['message'])) {
            $msg = (string)$result['response_ui']['message'];
        }

        $m = mb_strtolower(trim($msg));
        if ($m === '') return false;

        return str_contains($m, 'validation')
            || str_contains($m, 'parameter')
            || str_contains($m, 'required')
            || str_contains($m, 'customfield')
            || str_contains($m, 'custom field')
            || str_contains($m, "parameter 'id'");
    }

    private function sendLegacy(ApiProvider $p, string $action, array $parameters): array
    {
        $payload = $this->basePayload($p, $action);
        $payload['parameters'] = base64_encode(json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $url = $this->endpoint($p);

        try {
            $resp = Http::asForm()->timeout(60)->post($url, $payload);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => [
                    'url' => $url,
                    'action' => $action,
                    'payload' => $payload,
                    'legacy_parameters' => $parameters,
                    'exception' => $e->getMessage(),
                ],
                'response_raw' => ['error' => 'connection_failed', 'message' => $e->getMessage()],
                'response_ui' => ['type' => 'queued', 'message' => 'Provider unreachable, queued.'],
            ];
        }

        $raw = (string)$resp->body();
        $json = json_decode($raw, true);

        if (!$resp->successful() || !is_array($json)) {
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => [
                    'url' => $url,
                    'action' => $action,
                    'payload' => $payload,
                    'legacy_parameters' => $parameters,
                    'http_status' => $resp->status(),
                ],
                'response_raw' => is_array($json) ? $json : ['raw' => $raw, 'http_status' => $resp->status()],
                'response_ui' => ['type' => 'queued', 'message' => 'Temporary provider error, queued.'],
            ];
        }

        if (isset($json['SUCCESS'][0])) {
            $s0 = $json['SUCCESS'][0];
            $ref = $s0['REFERENCEID'] ?? null;

            return [
                'ok' => true,
                'retryable' => false,
                'status' => 'inprogress',
                'remote_id' => $ref,
                'request' => [
                    'url' => $url,
                    'action' => $action,
                    'payload' => $payload,
                    'legacy_parameters' => $parameters,
                    'http_status' => $resp->status(),
                ],
                'response_raw' => $json,
                'response_ui' => $this->normalizeUi($json),
            ];
        }

        $errMsg = null;
        if (isset($json['ERROR'][0]['MESSAGE'])) {
            $errMsg = (string)$json['ERROR'][0]['MESSAGE'];
        }

        if ($this->isRetryableErrorMessage($errMsg)) {
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => [
                    'url' => $url,
                    'action' => $action,
                    'payload' => $payload,
                    'legacy_parameters' => $parameters,
                    'http_status' => $resp->status(),
                ],
                'response_raw' => $json,
                'response_ui' => [
                    'type' => 'queued',
                    'message' => $errMsg ? ('Temporary provider issue: ' . $errMsg) : 'Temporary provider issue, queued.',
                ],
            ];
        }

        return [
            'ok' => false,
            'retryable' => false,
            'status' => 'rejected',
            'remote_id' => null,
            'request' => [
                'url' => $url,
                'action' => $action,
                'payload' => $payload,
                'legacy_parameters' => $parameters,
                'http_status' => $resp->status(),
            ],
            'response_raw' => $json,
            'response_ui' => $this->normalizeUi($json),
        ];
    }

    private function send(ApiProvider $p, string $action, string $parametersXml): array
    {
        $payload = $this->basePayload($p, $action);
        $payload['parameters'] = $parametersXml;

        $url = $this->endpoint($p);

        try {
            $resp = Http::asForm()->timeout(60)->post($url, $payload);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => [
                    'url' => $url,
                    'action' => $action,
                    'payload' => $payload,
                    'exception' => $e->getMessage()
                ],
                'response_raw' => ['error' => 'connection_failed', 'message' => $e->getMessage()],
                'response_ui' => ['type' => 'queued', 'message' => 'Provider unreachable, queued.'],
            ];
        }

        $raw = (string)$resp->body();
        $json = json_decode($raw, true);

        if (!$resp->successful() || !is_array($json)) {
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => [
                    'url' => $url,
                    'action' => $action,
                    'payload' => $payload,
                    'http_status' => $resp->status()
                ],
                'response_raw' => is_array($json) ? $json : ['raw' => $raw, 'http_status' => $resp->status()],
                'response_ui' => ['type' => 'queued', 'message' => 'Temporary provider error, queued.'],
            ];
        }

        if (isset($json['SUCCESS'][0])) {
            $s0 = $json['SUCCESS'][0];
            $ref = $s0['REFERENCEID'] ?? null;

            return [
                'ok' => true,
                'retryable' => false,
                'status' => 'inprogress',
                'remote_id' => $ref,
                'request' => [
                    'url' => $url,
                    'action' => $action,
                    'payload' => $payload,
                    'http_status' => $resp->status()
                ],
                'response_raw' => $json,
                'response_ui' => $this->normalizeUi($json),
            ];
        }

        $errMsg = null;
        if (isset($json['ERROR'][0]['MESSAGE'])) {
            $errMsg = (string)$json['ERROR'][0]['MESSAGE'];
        }

        if ($this->isRetryableErrorMessage($errMsg)) {
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => [
                    'url' => $url,
                    'action' => $action,
                    'payload' => $payload,
                    'http_status' => $resp->status()
                ],
                'response_raw' => $json,
                'response_ui' => [
                    'type' => 'queued',
                    'message' => $errMsg ? ('Temporary provider issue: ' . $errMsg) : 'Temporary provider issue, queued.',
                ],
            ];
        }

        return [
            'ok' => false,
            'retryable' => false,
            'status' => 'rejected',
            'remote_id' => null,
            'request' => [
                'url' => $url,
                'action' => $action,
                'payload' => $payload,
                'http_status' => $resp->status()
            ],
            'response_raw' => $json,
            'response_ui' => $this->normalizeUi($json),
        ];
    }

    // =========================
    // PLACE ORDERS
    // =========================

    /**
     * placeImeiOrder supports REQUIRED fields:
     * fields source: order->params['fields'] OR legacy order->params['required']
     */
    public function placeImeiOrder(ApiProvider $p, ImeiOrder $order): array
    {
        $serviceId = (string)($order->service?->remote_id ?? '');
        $imei = (string)($order->device ?? '');

        $fields = [];
        if (is_array($order->params)) {
            $fields = $order->params['fields'] ?? $order->params['required'] ?? [];
        }
        $fields = $this->normalizeRequiredFields($fields);
        $fields = $this->enrichRequiredFieldAliases($fields, $order->service);

        $xml = '<PARAMETERS>'
            . '<IMEI>' . $this->xmlEscape($imei) . '</IMEI>'
            . '<ID>' . $this->xmlEscape($serviceId) . '</ID>';

        if (!empty($fields)) {
            $requiredJson = json_encode($fields, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $xml .= '<REQUIRED>' . $this->xmlEscape((string)$requiredJson) . '</REQUIRED>';
        }

        $xml = $this->appendWellKnownFieldTags($xml, $fields);
        $xml .= '</PARAMETERS>';

        return $this->send($p, 'placeimeiorder', $xml);
    }


    private function legacyCustomfieldBlob(array $fields): string
    {
        $legacyFields = $fields;
        foreach ($fields as $k => $v) {
            $uk = strtoupper((string)$k);
            if ($uk !== '' && !array_key_exists($uk, $legacyFields)) {
                $legacyFields[$uk] = $v;
            }
        }

        return base64_encode(json_encode($legacyFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function buildLegacyServerPayload(string $serviceId, int $qty, array $fields, string $comments): array
    {
        $sid = trim((string)$serviceId);
        $customfieldBlob = $this->legacyCustomfieldBlob($fields);

        $payload = [
            // Multiple aliases for providers with inconsistent key parsing.
            'ID' => $sid,
            'id' => $sid,
            'SERVICEID' => $sid,
            'serviceid' => $sid,

            'QNT' => (string)$qty,
            'qnt' => (string)$qty,
            'QUANTITY' => (string)$qty,

            // DHRU v6.1 style custom fields.
            'customfield' => $customfieldBlob,
            'CUSTOMFIELD' => $customfieldBlob,
        ];

        if ($comments !== '') {
            $payload['COMMENTS'] = $comments;
            $payload['comments'] = $comments;
        }

        return $payload;
    }

    /**
     * ✅ Updated: REQUIRED is now optional (only if not empty), like IMEI/File
     */
    public function placeServerOrder(ApiProvider $p, ServerOrder $order): array
    {
        $serviceId = (string)($order->service?->remote_id ?? '');
        $qty = (int)($order->quantity ?? 1);

        $fields = [];
        if (is_array($order->params)) {
            $fields = $order->params['fields'] ?? $order->params['required'] ?? [];
        }
        $fields = $this->normalizeRequiredFields($fields);
        $fields = $this->enrichRequiredFieldAliases($fields, $order->service);

        $comments = trim((string)($order->comments ?? ''));

        if (trim($serviceId) === '') {
            return [
                'ok' => false,
                'retryable' => false,
                'status' => 'rejected',
                'remote_id' => null,
                'request' => [
                    'action' => 'placeServerOrder',
                    'service_id' => $serviceId,
                ],
                'response_raw' => ['ERROR' => [['MESSAGE' => 'Service remote ID missing']]],
                'response_ui' => ['type' => 'error', 'message' => 'Service remote ID missing'],
            ];
        }

        $xml = '<PARAMETERS>'
            . '<ID>' . $this->xmlEscape($serviceId) . '</ID>'
            . '<QUANTITY>' . $this->xmlEscape((string)$qty) . '</QUANTITY>';

        if (!empty($fields)) {
            $requiredJson = json_encode($fields, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $xml .= '<REQUIRED>' . $this->xmlEscape((string)$requiredJson) . '</REQUIRED>';
        }

        $xml = $this->appendWellKnownFieldTags($xml, $fields);

        if ($comments !== '') {
            $xml .= '<COMMENTS>' . $this->xmlEscape($comments) . '</COMMENTS>';
        }

        $xml .= '</PARAMETERS>';

        // Try server-specific action first.
        $res = $this->send($p, 'placeserverorder', $xml);

        if (($res['ok'] ?? false) === true) {
            return $res;
        }

        // Some DHRU providers use placeimeiorder for server services.
        if ($this->isCommandNotFoundResult($res)) {
            $fallback = $this->send($p, 'placeimeiorder', $xml);
            if (($fallback['ok'] ?? false) === true) {
                return $fallback;
            }

            // Legacy DHRU v6.1 style: parameters=base64(json) and customfield=base64(json)
            $legacyPayload = $this->buildLegacyServerPayload($serviceId, $qty, $fields, $comments);

            $legacy = $this->sendLegacy($p, 'placeimeiorder', $legacyPayload);
            if (($legacy['ok'] ?? false) === true) {
                return $legacy;
            }

            $fallback['retryable'] = true;
            $fallback['status'] = 'waiting';

            if ($this->shouldTryLegacyParameters($fallback)) {
                return $legacy;
            }

            return $fallback;
        }

        // Even if action exists, some providers require legacy encoded parameters on validation failures.
        if ($this->shouldTryLegacyParameters($res)) {
            $legacyPayload = $this->buildLegacyServerPayload($serviceId, $qty, $fields, $comments);

            $legacy = $this->sendLegacy($p, 'placeimeiorder', $legacyPayload);
            if (($legacy['ok'] ?? false) === true) {
                return $legacy;
            }

            return $legacy;
        }

        return $res;
    }

    /**
     * placeFileOrder supports REQUIRED if exists
     */
    public function placeFileOrder(ApiProvider $p, FileOrder $order): array
    {
        $serviceId = (string)($order->service?->remote_id ?? '');
        $path = (string)($order->storage_path ?? '');

        if ($path === '' || !Storage::exists($path)) {
            return [
                'ok' => false,
                'retryable' => false,
                'status' => 'rejected',
                'remote_id' => null,
                'request' => null,
                'response_raw' => ['ERROR' => [['MESSAGE' => 'storage_path missing or file not found']]],
                'response_ui' => ['type' => 'error', 'message' => 'File not found on server'],
            ];
        }

        $raw = Storage::get($path);
        $filename = (string)($order->device ?? basename($path));
        $b64 = base64_encode($raw);

        $fields = [];
        if (is_array($order->params)) {
            $fields = $order->params['fields'] ?? $order->params['required'] ?? [];
        }
        $fields = $this->normalizeRequiredFields($fields);
        $fields = $this->enrichRequiredFieldAliases($fields, $order->service);

        $xml = '<PARAMETERS>'
            . '<ID>' . $this->xmlEscape($serviceId) . '</ID>'
            . '<FILENAME>' . $this->xmlEscape($filename) . '</FILENAME>'
            . '<FILEDATA>' . $this->xmlEscape($b64) . '</FILEDATA>';

        if (!empty($fields)) {
            $requiredJson = json_encode($fields, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $xml .= '<REQUIRED>' . $this->xmlEscape((string)$requiredJson) . '</REQUIRED>';
        }

        $xml = $this->appendWellKnownFieldTags($xml, $fields);
        $xml .= '</PARAMETERS>';

        return $this->send($p, 'placefileorder', $xml);
    }

    // =========================
    // SYNC RESULT / STATUS
    // =========================

    public function getImeiOrder(ApiProvider $p, string $referenceId): array
    {
        $xml = '<PARAMETERS>'
            . '<ID>' . $this->xmlEscape($referenceId) . '</ID>'
            . '</PARAMETERS>';

        return $this->send($p, 'getimeiorder', $xml);
    }

    public function getServerOrder(ApiProvider $p, string $referenceId): array
    {
        $xml = '<PARAMETERS>'
            . '<ID>' . $this->xmlEscape($referenceId) . '</ID>'
            . '</PARAMETERS>';

        $res = $this->send($p, 'getserverorder', $xml);

        if (($res['ok'] ?? false) === true) {
            return $res;
        }

        if ($this->isCommandNotFoundResult($res)) {
            $fallback = $this->send($p, 'getimeiorder', $xml);
            if (($fallback['ok'] ?? false) === true) {
                return $fallback;
            }

            $fallback['retryable'] = true;
            $fallback['status'] = 'waiting';
            return $fallback;
        }

        return $res;
    }

    public function getFileOrder(ApiProvider $p, string $referenceId): array
    {
        $xml = '<PARAMETERS>'
            . '<ID>' . $this->xmlEscape($referenceId) . '</ID>'
            . '</PARAMETERS>';

        return $this->send($p, 'getfileorder', $xml);
    }
}
