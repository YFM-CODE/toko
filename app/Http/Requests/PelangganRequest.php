<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PelangganRequest extends BaseFormRequest
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
        $pelangganId = $this->route('pelanggan') ?? $this->route('id');

        return [
            'rfid_uid' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('tbl_pelanggan', 'rfid_uid')->ignore($pelangganId, 'id_pelanggan'),
            ],
            'nama_pelanggan' => ['required', 'string', 'max:100'],
            'kota' => ['required', 'string', 'max:50'],
            'no_telepon' => ['nullable', 'string', 'max:20'],
            'tier' => ['nullable', 'string', 'in:Reguler,Silver,Gold,VIP'],
            'poin' => ['nullable', 'integer', 'min:0'],
            'saldo' => ['nullable', 'numeric', 'min:0'],
            'username' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'password' => ['nullable', 'string', 'min:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rfid_uid.unique' => 'Kartu RFID ini sudah terdaftar pada pelanggan lain.',
            'nama_pelanggan.required' => 'Nama pelanggan wajib diisi.',
            'kota.required' => 'Kota asal pelanggan wajib diisi.',
            'no_telepon.max' => 'Nomor telepon maksimal 20 karakter.',
        ];
    }
}
