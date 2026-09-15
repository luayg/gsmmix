<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web')?->can('settings.edit') === true;
    }

    public function rules(): array
    {
        return [
            'mailer' => ['required', Rule::in(['smtp', 'sendmail', 'log'])],
            'host' => ['required_if:mailer,smtp', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:mailer,smtp', 'nullable', 'integer', 'between:1,65535'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'],
            'from_address' => ['required', 'email:rfc', 'max:255'],
            'from_name' => ['required', 'string', 'max:120'],
            'timeout' => ['required', 'integer', 'between:1,60'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('encryption') === '') {
            $this->merge(['encryption' => null]);
        }
    }
}
