<?php

namespace App\Transcript\Contracts;

use App\Transcript\Data\TranscriptData;

interface TranscriptProvider
{
    public function fetch(string $providerVideoId): TranscriptData;
}
