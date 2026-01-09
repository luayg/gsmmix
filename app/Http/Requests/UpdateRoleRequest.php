<?php
// app/Http/Requests/UpdateRoleRequest.php
// [انسخ]
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('roles.edit') ?? false;
    }

    public function rules(): array
    {
        $roleId = $this->route('role')?->id ?? null;

        return [
            'name'       => [
                'required','string','max:100',
                Rule::unique('roles','name')->ignore($roleId),
            ],
            'guard_name' => ['nullable','in:web'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الدور مطلوب.',
            'name.unique'   => 'اسم الدور مستخدم بالفعل.',
            'name.max'      => 'أقصى طول للاسم 100 حرف.',
        ];
    }
}
