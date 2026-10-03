<?php

namespace App\Actions;

use App\Models\UserTranscript;
use App\Models\UserTranscriptAnnotation;

final class FindUserTranscriptAnnotation
{
    public function handle(UserTranscript $userTranscript, string $publicId): UserTranscriptAnnotation
    {
        return $userTranscript->annotations()
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
