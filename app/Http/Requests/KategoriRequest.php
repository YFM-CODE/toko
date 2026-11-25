<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class KategoriRequest extends BaseFormRequest
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
        $kategoriId = $this->route('kategori') ?? $this->route('id');

        return [
            'nama_kategori' => [
                'required',
                'string',
                'max:100',
                Rule::unique('tbl_kategori', 'nama_kategori')->ignore($kategoriId, 'id_kategori'),
            ],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama_kategori.required' => 'Nama kategori wajib diisi.',
            'nama_kategori.unique' => 'Nama kategori sudah terdaftar.',
            'nama_kategori.max' => 'Nama kategori maksimal 100 karakter.',
        ];
    }
}
