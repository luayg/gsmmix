<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\SafeRasterImage;

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
            'contact_address' => ['nullable', 'string', 'max:500'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:500'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:500'],
            'x_url' => ['nullable', 'url:http,https', 'max:500'],
            'linkedin_url' => ['nullable', 'url:http,https', 'max:500'],
            'telegram_url' => ['nullable', 'url:http,https', 'max:500'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'timezone' => ['required', 'timezone:all'],
            'date_format' => ['required', Rule::in(['Y-m-d', 'd/m/Y', 'm/d/Y', 'd M Y'])],
            'registration_enabled' => ['sometimes', 'boolean'],
            'registration_activation' => ['sometimes', Rule::in(['automatic', 'admin'])],
            'email_verification_enabled' => ['sometimes', 'boolean'],
            'default_group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'default_overdraft' => ['sometimes', 'decimal:0,8', 'min:0', 'max:999999999999.99999999'],
            'show_prices_to_guests' => ['sometimes', 'boolean'],
            'show_original_prices' => ['sometimes', 'boolean'],
            'allow_credit_transfers' => ['sometimes', 'boolean'],
            'use_24_hour_time' => ['sometimes', 'boolean'],
            'session_lifetime' => ['sometimes', 'integer', 'between:5,43200'],
            'session_expire_on_close' => ['sometimes', 'boolean'],
            'api_low_balance_threshold' => ['sometimes', 'decimal:0,8', 'min:0', 'max:999999999999.99999999'],
            'service_imei_enabled' => ['sometimes', 'boolean'],
            'service_server_enabled' => ['sometimes', 'boolean'],
            'service_file_enabled' => ['sometimes', 'boolean'],
            'service_smm_enabled' => ['sometimes', 'boolean'],
            'store_enabled' => ['sometimes', 'boolean'],
            'two_factor_enabled' => ['sometimes', 'boolean'],
            'google_login_enabled' => ['sometimes', 'boolean'],
            'google_client_id' => ['nullable', 'string', 'max:500'],
            'google_client_secret' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'file', 'max:2048', new SafeRasterImage],
            'favicon' => ['nullable', 'file', 'max:512', new SafeRasterImage],
        ];
    }
}
