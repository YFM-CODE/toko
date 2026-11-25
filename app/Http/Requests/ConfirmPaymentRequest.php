<?php

namespace App\Http\Requests;

class ConfirmPaymentRequest extends BaseFormRequest
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
            'payment_ref' => ['nullable', 'string', 'max:80'],
            'nominal_bayar' => ['nullable', 'numeric', 'min:0'],
            'bukti_pembayaran' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
