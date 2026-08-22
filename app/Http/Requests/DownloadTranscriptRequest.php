<?php

namespace App\Http\Requests;

use App\Enums\TranscriptExportFormat;
use App\Enums\TranscriptExportMode;
use App\Transcript\Export\TranscriptExportOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DownloadTranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'format' => ['required', Rule::enum(TranscriptExportFormat::class)],
            'mode' => ['required', Rule::enum(TranscriptExportMode::class)],
            'timestamps' => ['required', 'boolean'],
        ];
    }

    public function options(): TranscriptExportOptions
    {
        $validated = $this->validated();

        return new TranscriptExportOptions(
            format: TranscriptExportFormat::from($validated['format']),
            mode: TranscriptExportMode::from($validated['mode']),
            timestamps: $this->boolean('timestamps'),
        );
    }
}
