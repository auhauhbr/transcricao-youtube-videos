<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tag = $this->user()->tags()->where('public_id', $this->route('tag'))->firstOrFail();

        return [
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('tags', 'name')->where('user_id', $this->user()->getKey())->ignore($tag->getKey()),
            ],
        ];
    }
}
