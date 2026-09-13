<?php

namespace App\Http\Requests;

use App\Models\ApiProvider;
use App\Services\Security\ProviderKeyCipher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveApiProviderRequest extends FormRequest
{
    private const FLAGS = ['sync_imei', 'sync_server', 'sync_file', 'sync_smm', 'ignore_low_balance', 'auto_sync', 'active'];

    public function authorize(): bool
    {
        $user = $this->user('web');
        return $user !== null && $user->can('admin.access')
            && $user->can($this->isMethod('POST') ? 'apis.create' : 'apis.edit');
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['dhru', 'webx', 'gsmhub', 'unlockbase', 'simple_link', 'smm'])],
            'url' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail): void {
                if (is_string($value) && ProviderKeyCipher::resemblesCiphertext($value)) {
                    $fail('Enter the original API key, not an encrypted database value.');
                }
            }],
            'params' => ['nullable', function ($attribute, $value, $fail): void {
                $decoded = is_string($value) ? json_decode($value, true) : $value;
                if (!is_array($decoded)) {
                    $fail('Provider settings must be an array or a JSON object.');
                }
            }],
            'main_field_name' => ['nullable', 'string', 'max:100'],
            'method' => ['nullable', Rule::in(['GET', 'POST'])],
        ];
        foreach (self::FLAGS as $flag) {
            $rules[$flag] = ['sometimes', 'boolean'];
        }
        return $rules;
    }

    public function providerData(?ApiProvider $provider = null): array
    {
        $data = $this->validated();
        foreach (self::FLAGS as $flag) {
            if ($provider === null || array_key_exists($flag, $data)) {
                $data[$flag] = $this->boolean($flag);
            }
        }

        // Empty/missing replacement means KEEP, regardless of JavaScript or client.
        if (!isset($data['api_key']) || trim($data['api_key']) === '') {
            unset($data['api_key']);
        }

        $params = array_key_exists('params', $data) ? $data['params'] : $provider?->params;
        if (is_string($params)) {
            $params = json_decode($params, true);
        }
        $params = is_array($params) ? $params : [];
        if ($data['type'] === 'simple_link') {
            $data['url'] = trim($data['url']);
            $data['username'] = null;
            $data['api_key'] = null; // Existing behavior: simple links do not use this field.
            $params['main_field'] = trim((string) ($data['main_field_name'] ?? $params['main_field'] ?? 'imei')) ?: 'imei';
            $params['method'] = $data['method'] ?? $params['method'] ?? 'POST';
            unset($params['services_url']);
        } elseif ($data['type'] === 'smm') {
            $data['url'] = trim($data['url']);
            $data['username'] = null;
        } else {
            $data['url'] = rtrim($data['url'], '/') . '/';
        }
        $data['params'] = $params ?: null;
        unset($data['main_field_name'], $data['method']);
        return $data;
    }
}
