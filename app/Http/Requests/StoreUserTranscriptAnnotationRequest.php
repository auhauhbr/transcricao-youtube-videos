<?php

namespace App\Http\Requests;

use App\Enums\UserTranscriptAnnotationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserTranscriptAnnotationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $text = $this->input('text');

        $this->merge([
            'text' => is_string($text) ? trim($text) : $text,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start_ms' => ['required', 'integer', 'min:0', 'max:86400000'],
            'type' => ['required', Rule::enum(UserTranscriptAnnotationType::class)],
            'text' => [
                Rule::requiredIf($this->input('type') === UserTranscriptAnnotationType::Note->value),
                'nullable',
                'string',
                'max:4000',
            ],
        ];
    }
}
