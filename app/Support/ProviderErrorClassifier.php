<?php

namespace App\Support;

class ProviderErrorClassifier
{
    public static function classify(string $message, int $httpStatus = 0, ?string $contentType = null): array
    {
        $m = strtolower(trim($message));
        $ct = strtolower((string)$contentType);

        // invalid URL / bad endpoint
        if (
            str_contains($m, 'could not resolve host') ||
            str_contains($m, 'name or service not known') ||
            str_contains($m, 'no such host') ||
            str_contains($m, 'invalid url') ||
            str_contains($m, 'malformed') ||
            str_contains($m, 'cURL error 3') ||
            str_contains($m, 'cURL error 6')
        ) {
            return ['status' => 'rejected', 'msg' => 'INVALID URL - Check provider URL/api_path'];
        }

        // auth failures
        if (
            $httpStatus === 401 ||
            $httpStatus === 403 ||
            str_contains($m, 'unauthorized') ||
            str_contains($m, 'forbidden') ||
            str_contains($m, 'auth') && str_contains($m, 'fail') ||
            str_contains($m, 'invalid key') ||
            str_contains($m, 'api key') && str_contains($m, 'invalid')
        ) {
            return ['status' => 'rejected', 'msg' => 'AUTH FAILED - Check username/api_key/auth_mode'];
        }

        // IP blocked / WAF / HTML 503 pages
        if (
            str_contains($m, 'ip blocked') ||
            str_contains($m, 'whitelist') ||
            str_contains($m, 'access denied') ||
            str_contains($m, 'cloudflare') ||
            ($httpStatus === 503 && (str_contains($m, '<html') || str_contains($ct, 'text/html')))
        ) {
            return ['status' => 'rejected', 'msg' => 'IP BLOCKED - Reset Provider IP'];
        }

        // timeouts / connection issues -> retryable
        if (
            str_contains($m, 'timed out') ||
            str_contains($m, 'timeout') ||
            str_contains($m, 'connection refused') ||
            str_contains($m, 'failed to connect') ||
            str_contains($m, 'cURL error 7') ||
            str_contains($m, 'cURL error 28')
        ) {
            return ['status' => 'waiting', 'msg' => 'TIMEOUT - Provider not responding'];
        }

        // provider down
        if ($httpStatus >= 500) {
            return ['status' => 'waiting', 'msg' => 'PROVIDER DOWN - Try again later'];
        }

        // generic client errors -> reject
        if ($httpStatus >= 400 && $httpStatus < 500) {
            return ['status' => 'rejected', 'msg' => 'REQUEST REJECTED - Check request fields'];
        }

        return ['status' => 'waiting', 'msg' => 'PROVIDER ERROR'];
    }
}