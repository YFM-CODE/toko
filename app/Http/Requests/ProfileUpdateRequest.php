<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (! $this->filled('nama_lengkap') && $this->filled('name')) {
            $this->merge([
                'nama_lengkap' => $this->input('name'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id_user;

        return [
            'nama_lengkap' => ['required', 'string', 'max:100'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('tbl_users', 'username')->ignore($userId, 'id_user'),
            ],
            'email' => [
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:100',
                Rule::unique('tbl_users', 'email')->ignore($userId, 'id_user'),
            ],
        ];
    }
}
