<?php

namespace App\Http\Requests;

use App\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveCurrencyRequest extends FormRequest
{
    public function authorize(): bool { return $this->user('web')?->can($this->isMethod('POST') ? 'settings.create' : 'settings.edit') === true; }
    public function rules(): array
    {
        $currency = $this->route('currency');
        $id = $currency instanceof Currency ? $currency->id : null;
        return [
            'code' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', Rule::unique('currencies', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100'], 'symbol' => ['required', 'string', 'max:10'],
            'exchange_rate' => ['required', 'decimal:0,8', 'gt:0', 'max:999999999999.99999999'],
            'decimal_places' => ['required', 'integer', 'between:0,8'], 'symbol_position' => ['required', Rule::in(['before', 'after'])],
            'decimal_separator' => ['required', Rule::in(['.', ','])], 'thousands_separator' => ['nullable', Rule::in([',', '.', ' ', "'"])],
            'is_default' => ['sometimes', 'boolean'], 'active' => ['sometimes', 'boolean'], 'ordering' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }
    protected function prepareForValidation(): void { $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]); }
}
