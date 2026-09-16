<?php

namespace App\Http\Requests;

use App\Models\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SavePaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool { return $this->user('web')?->can($this->isMethod('POST') ? 'settings.create' : 'settings.edit') === true; }

    public function rules(): array
    {
        $gateway = $this->route('gateway');
        $id = $gateway instanceof PaymentGateway ? $gateway->id : null;
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('payment_gateways', 'slug')->ignore($id)],
            'driver' => ['required', Rule::in(['manual', 'stripe', 'paypal', 'custom'])],
            'description' => ['nullable', 'string', 'max:2000'], 'instructions' => ['nullable', 'string', 'max:10000'],
            'payment_details' => ['nullable', 'string', 'max:5000'],
            'fixed_fee' => ['required', 'decimal:0,8', 'min:0', 'max:999999999999.99999999'],
            'percent_fee' => ['required', 'decimal:0,4', 'between:0,100'],
            'tax_percent' => ['nullable', 'decimal:0,4', 'between:0,100'],
            'minimum_amount' => ['nullable', 'decimal:0,8', 'gt:0'], 'maximum_amount' => ['nullable', 'decimal:0,8', 'gt:0'],
            'sandbox' => ['sometimes', 'boolean'], 'active' => ['sometimes', 'boolean'], 'ordering' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'currency_ids' => ['required', 'array', 'min:1'], 'currency_ids.*' => ['integer', 'distinct', 'exists:currencies,id'],
            'client_id' => ['nullable', 'string', 'max:1000'], 'api_key' => ['nullable', 'string', 'max:1000'],
            'api_secret' => ['nullable', 'string', 'max:2000'], 'webhook_secret' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('minimum_amount') && $this->filled('maximum_amount')
                && bccomp((string) $this->input('minimum_amount'), (string) $this->input('maximum_amount'), 8) > 0) {
                $validator->errors()->add('maximum_amount', 'Maximum amount must be greater than or equal to the minimum.');
            }
            if ($this->boolean('active') && $this->input('driver') !== 'manual') {
                $validator->errors()->add('active', 'This electronic driver cannot be activated until its payment processor is implemented.');
            }
            if ($this->boolean('active') && $this->input('driver') === 'manual' && trim((string) $this->input('payment_details')) === '') {
                $validator->errors()->add('payment_details', 'Active manual gateways must provide payment details.');
            }
        });
    }

    protected function prepareForValidation(): void { $this->merge(['slug' => strtolower(trim((string) $this->input('slug')))]); }
}
