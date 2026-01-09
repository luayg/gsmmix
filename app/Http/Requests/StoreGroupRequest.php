<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:255','unique:groups,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المجموعة مطلوب',
            'name.unique'   => 'هذا الاسم مستخدم مسبقاً',
        ];
    }
}
