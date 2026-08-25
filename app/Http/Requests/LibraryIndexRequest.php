<?php

namespace App\Http\Requests;

use App\Enums\TranscriptSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LibraryIndexRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('q')) {
            $this->merge(['q' => trim((string) $this->input('q'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'folder' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== 'none' && (! is_string($value) || ! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $value))) {
                    $fail('A pasta selecionada é inválida.');
                }
            }],
            'tag' => ['nullable', 'ulid'],
            'language' => ['nullable', 'string', 'max:35'],
            'source' => ['nullable', Rule::enum(TranscriptSource::class)],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'title_asc', 'title_desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{q: string, folder: string|null, tag: string|null, language: string|null, source: string|null, sort: string} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'q' => (string) ($validated['q'] ?? ''),
            'folder' => $validated['folder'] ?? null,
            'tag' => $validated['tag'] ?? null,
            'language' => $validated['language'] ?? null,
            'source' => $validated['source'] ?? null,
            'sort' => $validated['sort'] ?? 'newest',
        ];
    }
}
