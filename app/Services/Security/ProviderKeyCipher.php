<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use InvalidArgumentException;
use RuntimeException;

final class ProviderKeyCipher
{
    public function __construct(private readonly Encrypter $encrypter) {}

    /** Conservative recognition, including recognizable truncated Laravel payloads. */
    public static function resemblesCiphertext(#[\SensitiveParameter] string $value): bool
    {
        foreach ([$value, base64_decode($value, false)] as $candidate) {
            $payload = json_decode($candidate, true);
            if (is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac'])) {
                return true;
            }
            if (preg_match('/^\s*\{\s*"iv"\s*:/', $candidate)) {
                return true;
            }
            if (preg_match_all('/"(?:iv|value|mac|tag)"\s*:/', $candidate) >= 2) {
                return true;
            }
        }
        return false;
    }

    public function state(#[\SensitiveParameter] ?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return 'empty';
        }
        if (!self::resemblesCiphertext($value)) {
            return 'legacy_plaintext';
        }
        try {
            $plain = $this->encrypter->decrypt($value, false);
        } catch (DecryptException) {
            return 'unreadable';
        }
        if (self::resemblesCiphertext($plain)) {
            return 'nested';
        }
        return trim($plain) === '' ? 'empty' : 'encrypted';
    }

    public function encrypt(#[\SensitiveParameter] ?string $plain): ?string
    {
        if ($plain === null || trim($plain) === '') {
            return null;
        }
        if (self::resemblesCiphertext($plain)) {
            throw new InvalidArgumentException('Supply an original API key, not encrypted storage.');
        }
        return $this->encrypter->encrypt($plain, false);
    }

    /** Strict model reads: never silently send legacy plaintext or nested ciphertext. */
    public function decrypt(#[\SensitiveParameter] ?string $stored): ?string
    {
        if ($stored === null || trim($stored) === '') {
            return null;
        }
        $plain = $this->encrypter->decrypt($stored, false);
        if (self::resemblesCiphertext($plain)) {
            throw new DecryptException('Nested provider key encryption requires manual recovery.');
        }
        return $plain;
    }

    /** Migrations only. Valid ciphertext is preserved byte-for-byte. */
    public function upgradedValue(#[\SensitiveParameter] ?string $stored): ?string
    {
        return match ($this->state($stored)) {
            'empty' => null,
            'encrypted' => $stored,
            'legacy_plaintext' => $this->encrypt($stored),
            default => throw new RuntimeException(
                'Provider key upgrade stopped: unreadable or nested encryption. Restore the correct application key or recover the credential; no secret values are shown.'
            ),
        };
    }
}
