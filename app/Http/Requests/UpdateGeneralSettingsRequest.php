<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web')?->can('settings.edit') === true;
    }

    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:120'],
            'site_tagline' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'timezone' => ['required', 'timezone:all'],
            'date_format' => ['required', Rule::in(['Y-m-d', 'd/m/Y', 'm/d/Y', 'd M Y'])],
            'registration_enabled' => ['sometimes', 'boolean'],
            'service_imei_enabled' => ['sometimes', 'boolean'],
            'service_server_enabled' => ['sometimes', 'boolean'],
            'service_file_enabled' => ['sometimes', 'boolean'],
            'service_smm_enabled' => ['sometimes', 'boolean'],
            'store_enabled' => ['sometimes', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }
}
