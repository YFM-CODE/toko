<?php

namespace App\Http\Requests;

class TopUpSaldoRequest extends BaseFormRequest
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
            'nominal' => ['required', 'numeric', 'min:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nominal.required' => 'Nominal top-up wajib diisi.',
            'nominal.min' => 'Nominal top-up minimal Rp 1.000.',
        ];
    }
}
