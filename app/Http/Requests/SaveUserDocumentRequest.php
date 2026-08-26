<?php

namespace App\Http\Requests;

use App\Rules\ValidUserDocumentContent;
use Illuminate\Foundation\Http\FormRequest;

class SaveUserDocumentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'array', new ValidUserDocumentContent],
            // null means the client loaded the lazy seed before a document existed.
            'lock_version' => ['present', 'nullable', 'integer', 'min:1'],
        ];
    }
}
