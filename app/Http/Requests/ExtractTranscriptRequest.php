<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExtractTranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'video_url' => ['bail', 'required', 'string', 'max:2048', 'url:https'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'video_url.required' => 'Informe a URL de um vídeo do YouTube.',
            'video_url.string' => 'Informe uma URL válida de vídeo do YouTube.',
            'video_url.max' => 'A URL do vídeo não pode ter mais de 2048 caracteres.',
            'video_url.url' => 'Informe uma URL válida de vídeo do YouTube.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $videoUrl = $this->input('video_url');

        if (is_string($videoUrl)) {
            $this->merge(['video_url' => trim($videoUrl)]);
        }
    }
}
