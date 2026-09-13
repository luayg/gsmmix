<?php

namespace App\Casts;

use App\Services\Security\ProviderKeyCipher;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

final class EncryptedProviderKey implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return app(ProviderKeyCipher::class)->decrypt($value);
    }

    public function set(Model $model, string $key, #[\SensitiveParameter] mixed $value, array $attributes): ?string
    {
        return app(ProviderKeyCipher::class)->encrypt($value);
    }
}
