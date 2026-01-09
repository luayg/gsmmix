<?php
// app/Http/Requests/StoreRoleRequest.php
// [انسخ]
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('roles.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'       => ['required','string','max:100','unique:roles,name'],
            'guard_name' => ['nullable','in:web'], // نحصرها على web لتوافق Spatie
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
