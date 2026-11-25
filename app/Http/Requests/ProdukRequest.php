<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ProdukRequest extends BaseFormRequest
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
        $produkId = $this->route('produk') ?? $this->route('id');

        return [
            'id_kategori' => ['required', 'integer', 'exists:tbl_kategori,id_kategori'],
            'kode_produk' => [
                'required',
                'string',
                'max:30',
                Rule::unique('tbl_produk', 'kode_produk')->ignore($produkId, 'id_produk'),
            ],
            'nama_produk' => ['required', 'string', 'max:150'],
            'harga' => ['required', 'numeric', 'min:0'],
            'stok' => ['required', 'integer', 'min:0'],
            'gambar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'hapus_gambar' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_kategori.required' => 'Kategori produk wajib dipilih.',
            'id_kategori.exists' => 'Kategori yang dipilih tidak valid.',
            'kode_produk.required' => 'Kode produk wajib diisi.',
            'kode_produk.unique' => 'Kode produk sudah digunakan.',
            'nama_produk.required' => 'Nama produk wajib diisi.',
            'harga.required' => 'Harga produk wajib diisi.',
            'harga.min' => 'Harga produk tidak boleh negatif.',
            'stok.required' => 'Stok wajib diisi.',
            'stok.min' => 'Stok tidak boleh kurang dari 0.',
            'gambar.image' => 'File yang diunggah harus berupa gambar.',
            'gambar.mimes' => 'Format gambar harus JPG, PNG, atau WEBP.',
            'gambar.max' => 'Ukuran gambar maksimal 2 MB.',
        ];
    }
}
