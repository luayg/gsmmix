<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $groupId = $this->route('group')?->id ?? $this->route('group');
        return [
            'name' => [
                'required','string','max:255',
                Rule::unique('groups','name')->ignore($groupId),
            ],
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
