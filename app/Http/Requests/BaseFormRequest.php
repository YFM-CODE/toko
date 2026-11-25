<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseFormRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $sanitized = $this->sanitizePayload($this->all());

        if ($sanitized !== $this->all()) {
            $this->merge($sanitized);
        }
    }

    /**
     * Sanitize null-bytes and dangerous script/PHP tags without altering user casing.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    protected function sanitizePayload(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->sanitizePayload($value);
            } elseif (is_string($value)) {
                $clean = str_replace("\0", '', $value);
                $clean = preg_replace('/<\s*(script|iframe|object|embed|php)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $clean) ?? $clean;
                $clean = preg_replace('/<\?(php)?/i', '', $clean) ?? $clean;
                $result[$key] = trim($clean);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
