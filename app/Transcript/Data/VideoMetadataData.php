<?php

namespace App\Transcript\Data;

use App\Enums\VideoProvider;

final readonly class VideoMetadataData
{
    public function __construct(
        public VideoProvider $provider,
        public string $providerVideoId,
        public string $title,
        public string $channelName,
        public int $durationSeconds,
        public ?string $thumbnailUrl,
    ) {}

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider->value,
            'providerVideoId' => $this->providerVideoId,
            'title' => $this->title,
            'channelName' => $this->channelName,
            'durationSeconds' => $this->durationSeconds,
            'thumbnailUrl' => $this->thumbnailUrl,
        ];
    }
}
