<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserTranscriptAnnotationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['text' => trim((string) $this->input('text'))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['text' => ['required', 'string', 'max:4000']];
    }
}
