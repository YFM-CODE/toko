<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Validation\Rule;

class UserRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user') ?? $this->route('id');

        return [
            'nama_lengkap' => ['required', 'string', 'max:100'],
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tbl_users', 'username')->ignore($userId, 'id_user'),
            ],
            'rfid_uid' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('tbl_users', 'rfid_uid')->ignore($userId, 'id_user'),
            ],
            'email' => [
                'nullable',
                'email',
                'max:100',
                Rule::unique('tbl_users', 'email')->ignore($userId, 'id_user'),
            ],
            'role' => ['required', Rule::in(UserRole::values())],
            'is_active' => ['sometimes', 'boolean'],
            'password' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'min:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rfid_uid.unique' => 'Kartu RFID ini sudah terdaftar pada akun staf lain.',
        ];
    }
}
