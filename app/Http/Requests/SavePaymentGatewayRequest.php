<?php

namespace App\Http\Requests;

use App\Models\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SavePaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web')?->can($this->isMethod('POST') ? 'settings.create' : 'settings.edit') === true;
    }

    public function rules(): array
    {
        $gateway = $this->route('gateway');
        $system = $gateway instanceof PaymentGateway && $gateway->is_system;
        return [
            'name' => [$system ? 'sometimes' : 'required', 'string', 'max:100'],
            'slug' => [$system ? 'sometimes' : 'required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('payment_gateways', 'slug')->ignore($gateway?->id)],
            'driver' => [$system ? 'sometimes' : 'required', Rule::in(['manual'])],
            'description' => ['nullable', 'string', 'max:2000'], 'instructions' => ['nullable', 'string', 'max:10000'],
            'payment_details' => ['nullable', 'string', 'max:5000'],
            'fixed_fee' => ['required', 'decimal:0,8', 'min:0', 'max:999999999999.99999999'],
            'percent_fee' => ['required', 'decimal:0,4', 'between:0,100'],
            'tax_percent' => ['nullable', 'decimal:0,4', 'between:0,100'],
            'minimum_amount' => ['nullable', 'decimal:0,8', 'gt:0'], 'maximum_amount' => ['nullable', 'decimal:0,8', 'gt:0'],
            'sandbox' => ['sometimes', 'boolean'], 'active' => ['sometimes', 'boolean'],
            'ordering' => [$system ? 'sometimes' : 'nullable', 'integer', 'min:0', 'max:999999'],
            'currency_ids' => ['required', 'array', 'min:1'], 'currency_ids.*' => ['integer', 'distinct', 'exists:currencies,id'],
            'client_id' => ['nullable', 'string', 'max:1000'], 'client_secret' => ['nullable', 'string', 'max:2000'],
            'paypal_webhook_id' => ['nullable', 'string', 'max:1000'],
            'binance_api_key' => ['nullable', 'string', 'max:1000'], 'binance_secret_key' => ['nullable', 'string', 'max:2000'],
            'binance_webhook_public_key' => ['nullable', 'string', 'max:10000'],
            'usdt_network' => ['nullable', Rule::in(['TRC20', 'BEP20'])],
            'wallet_address' => ['nullable', 'string', 'max:255'], 'provider_url' => ['nullable', 'url', 'max:2000'],
            'provider_api_key' => ['nullable', 'string', 'max:2000'], 'contract_address' => ['nullable', 'string', 'max:255'],
            'confirmations' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'logo' => [$system ? 'prohibited' : 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $gateway = $this->route('gateway');
            $driver = $gateway instanceof PaymentGateway && $gateway->is_system ? $gateway->driver : 'manual';
            if ($this->filled('minimum_amount') && $this->filled('maximum_amount')
                && bccomp((string) $this->input('minimum_amount'), (string) $this->input('maximum_amount'), 8) > 0) {
                $validator->errors()->add('maximum_amount', 'Maximum amount must be greater than or equal to the minimum.');
            }
            if ($this->boolean('active') && $driver === 'manual' && trim((string) $this->input('payment_details')) === '') {
                $validator->errors()->add('payment_details', 'Active manual gateways must provide payment details.');
            }
            if (!$this->boolean('active') || !($gateway instanceof PaymentGateway && $gateway->is_system)) return;
            $existing = $gateway->credentials ?? [];
            $required = match ($driver) {
                'paypal' => ['client_id', 'client_secret', 'paypal_webhook_id'],
                'binance_pay' => ['binance_api_key', 'binance_secret_key', 'binance_webhook_public_key'],
                'usdt' => ['wallet_address', 'provider_url', 'contract_address'],
                default => [],
            };
            foreach ($required as $field) {
                if (!$this->filled($field) && blank($existing[$field] ?? null)) $validator->errors()->add($field, 'This value is required before activating the gateway.');
            }
            if ($driver === 'usdt' && !$this->filled('usdt_network') && blank(data_get($gateway->config, 'network'))) {
                $validator->errors()->add('usdt_network', 'Select TRC20 or BEP20 before activating USDT.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('slug')) $this->merge(['slug' => strtolower(trim((string) $this->input('slug')))]);
    }
}
