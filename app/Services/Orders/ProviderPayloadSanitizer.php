<?php

namespace App\Services\Orders;

final class ProviderPayloadSanitizer
{
    private const REQUEST_SECRET_KEYS = [
        'apiaccesskey','apikey','api_key','accesskey','access_key','authorization',
        'accesstoken','access_token','refreshtoken','refresh_token','token','secret',
        'clientsecret','client_secret','password','pass','key',
    ];

    private const RESPONSE_SECRET_KEYS = [
        'apiaccesskey','apikey','api_key','accesskey','access_key','authorization',
        'accesstoken','access_token','refreshtoken','refresh_token','token','secret',
        'clientsecret','client_secret',
    ];

    public function sanitizeRequest(mixed $value): mixed
    {
        return $this->sanitize($value, self::REQUEST_SECRET_KEYS);
    }

    public function sanitizeResponse(mixed $value): mixed
    {
        // Provider results can legitimately contain an unlock "key" or an account
        // password. Response sanitization therefore targets provider/API credentials
        // only, not generic customer result fields.
        return $this->sanitize($value, self::RESPONSE_SECRET_KEYS);
    }

    private function sanitize(mixed $value, array $secretKeys): mixed
    {
        if (is_string($value)) {
            return $this->redactUrlQuerySecrets($value, $secretKeys);
        }

        if (!is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isSecretKey($key, $secretKeys)) {
                $out[$key] = '[REDACTED]';
                continue;
            }

            $out[$key] = $this->sanitize($item, $secretKeys);
        }

        return $out;
    }

    private function redactUrlQuerySecrets(string $value, array $secretKeys): string
    {
        if (!str_contains($value, '?') && !str_contains($value, '&')) {
            return $value;
        }

        foreach ($secretKeys as $secretKey) {
            $quoted = preg_quote($secretKey, '/');
            $value = preg_replace(
                '/([?&](?:' . $quoted . ')=)[^&#\s]*/i',
                '$1[REDACTED]',
                $value
            ) ?? $value;
        }

        return $value;
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
