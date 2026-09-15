<?php

namespace App\Http\Requests;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveLanguageRequest extends FormRequest
{
    public function authorize(): bool { return $this->user('web')?->can($this->isMethod('POST') ? 'settings.create' : 'settings.edit') === true; }
    public function rules(): array
    {
        $language = $this->route('language');
        $id = $language instanceof Language ? $language->id : null;
        return [
            'name' => ['required', 'string', 'max:100'], 'native_name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2,3}$/', Rule::unique('languages', 'code')->ignore($id)],
            'locale' => ['required', 'string', 'max:20', 'regex:/^[a-z]{2,3}(?:[_-][A-Z]{2})?$/', Rule::unique('languages', 'locale')->ignore($id)],
            'direction' => ['required', Rule::in(['ltr', 'rtl'])], 'flag' => ['nullable', 'string', 'max:10'],
            'is_default' => ['sometimes', 'boolean'], 'active' => ['sometimes', 'boolean'], 'ordering' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtolower(trim((string) $this->input('code'))), 'locale' => trim((string) $this->input('locale'))]);
    }
}
