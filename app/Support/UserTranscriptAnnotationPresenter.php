<?php

namespace App\Support;

use App\Models\UserTranscript;
use App\Models\UserTranscriptAnnotation;

final class UserTranscriptAnnotationPresenter
{
    /**
     * @return array{items: list<array{publicId: string, startMs: int, type: string, text: string|null, urls: array{update: string, destroy: string}}>, urls: array{store: string}}
     */
    public function forUserTranscript(UserTranscript $userTranscript): array
    {
        return [
            'items' => $userTranscript->annotations->map(fn (UserTranscriptAnnotation $annotation): array => $this->annotation($annotation, $userTranscript))->values()->all(),
            'urls' => [
                'store' => route('library.annotations.store', $userTranscript->public_id, absolute: false),
            ],
        ];
    }

    /** @return array{publicId: string, startMs: int, type: string, text: string|null, urls: array{update: string, destroy: string}} */
    public function annotation(UserTranscriptAnnotation $annotation, UserTranscript $userTranscript): array
    {
        return [
            'publicId' => $annotation->public_id,
            'startMs' => $annotation->start_ms,
            'type' => $annotation->type->value,
            'text' => $annotation->text,
            'urls' => [
                'update' => route('library.annotations.update', [$userTranscript->public_id, $annotation->public_id], absolute: false),
                'destroy' => route('library.annotations.destroy', [$userTranscript->public_id, $annotation->public_id], absolute: false),
            ],
        ];
    }
}
