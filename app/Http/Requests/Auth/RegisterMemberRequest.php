<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class RegisterMemberRequest extends BaseFormRequest
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
        return [
            'nama_lengkap' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('tbl_users', 'username')],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:100', Rule::unique('tbl_users', 'email')],
            'rfid_uid' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('tbl_users', 'rfid_uid'),
            ],
            'kota' => ['required', 'string', 'max:50'],
            'no_telepon' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama_lengkap.required' => 'Nama lengkap member wajib diisi.',
            'username.required' => 'Username login wajib diisi.',
            'username.unique' => 'Username ini sudah digunakan, pilih username lain.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'rfid_uid.unique' => 'UID Kartu RFID ini sudah digunakan pada akun lain.',
            'kota.required' => 'Kota asal wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}
