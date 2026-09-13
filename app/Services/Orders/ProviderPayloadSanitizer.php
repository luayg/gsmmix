<?php

namespace App\Services\Orders;

final class ProviderPayloadSanitizer
{
    private const REQUEST_SECRET_KEYS = [
        'apiaccesskey',
        'apikey',
        'api_key',
        'accesskey',
        'access_key',
        'authorization',
        'accesstoken',
        'access_token',
        'refreshtoken',
        'refresh_token',
        'token',
        'secret',
        'clientsecret',
        'client_secret',
        'password',
        'pass',
        'key',
    ];

    private const RESPONSE_SECRET_KEYS = [
        'apiaccesskey',
        'apikey',
        'api_key',
        'accesskey',
        'access_key',
        'authorization',
        'accesstoken',
        'access_token',
        'refreshtoken',
        'refresh_token',
        'token',
        'secret',
        'clientsecret',
        'client_secret',
    ];

    public function sanitizeRequest(mixed $value): mixed
    {
        return $this->sanitize($value, self::REQUEST_SECRET_KEYS);
    }

    public function sanitizeResponse(mixed $value): mixed
    {
        // Response payloads may legitimately contain an unlock "key" or a returned
        // account password. Do not destroy customer results; redact only fields whose
        // names clearly represent provider/API credentials.
        return $this->sanitize($value, self::RESPONSE_SECRET_KEYS);
    }

    private function sanitize(mixed $value, array $secretKeys): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isSecretKey($key, $secretKeys)) {
                $out[$key] = '[REDACTED]';
                continue;
            }

            $out[$key] = is_array($item)
                ? $this->sanitize($item, $secretKeys)
                : $item;
        }

        return $out;
    }

    private function isSecretKey(string $key, array $secretKeys): bool
    {
        $normalized = strtolower(trim($key));
        $compact = preg_replace('/[^a-z0-9]+/', '', $normalized) ?? $normalized;

        foreach ($secretKeys as $secretKey) {
            $secretCompact = preg_replace('/[^a-z0-9]+/', '', strtolower($secretKey)) ?? strtolower($secretKey);
            if ($compact === $secretCompact) {
                return true;
            }
        }

        return false;
    }
}
