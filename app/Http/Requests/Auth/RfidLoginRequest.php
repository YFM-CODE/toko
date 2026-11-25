<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class RfidLoginRequest extends BaseFormRequest
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
            'rfid_uid' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rfid_uid.required' => 'Tempelkan kartu RFID pada reader.',
        ];
    }
}
