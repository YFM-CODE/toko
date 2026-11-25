<?php

namespace App\Http\Requests;

class CheckoutRequest extends BaseFormRequest
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
            'id_pelanggan' => ['nullable', 'integer', 'exists:tbl_pelanggan,id_pelanggan'],
            'tanggal' => ['nullable', 'date'],
            'persen_diskon' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'poin_ditukar' => ['nullable', 'integer', 'min:0'],
            'metode_pembayaran' => ['nullable', 'string', 'in:TUNAI,SCAN_CPM,QRIS_MPM,SALDO_MEMBER'],
            'payment_channel' => ['nullable', 'string', 'max:50'],
            'payment_token' => ['nullable', 'string', 'max:120'],
            'nominal_bayar' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id_produk' => ['required', 'integer', 'exists:tbl_produk,id_produk'],
            'items.*.jumlah' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_pelanggan.exists' => 'Data pelanggan tidak ditemukan.',
            'persen_diskon.min' => 'Persentase diskon minimal 0%.',
            'persen_diskon.max' => 'Persentase diskon maksimal 100%.',
            'items.required' => 'Keranjang belanja tidak boleh kosong.',
            'items.min' => 'Minimal harus ada 1 produk untuk checkout.',
            'items.*.id_produk.exists' => 'Salah satu produk tidak ditemukan di database.',
            'items.*.jumlah.min' => 'Jumlah pembelian tiap produk minimal 1.',
        ];
    }
}
