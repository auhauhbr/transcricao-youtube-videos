<?php

namespace App\Transcript\Providers;

use App\Transcript\Contracts\TranscriptProvider;
use App\Transcript\Data\TranscriptData;
use App\Transcript\Exceptions\TranscriptNotAvailableException;
use App\Transcript\YtDlp\CaptionTrackSelector;
use App\Transcript\YtDlp\Json3TranscriptParser;
use App\Transcript\YtDlp\YtDlpGateway;
use App\Transcript\YtDlp\YtDlpTranscriptMapper;

final class YouTubeTranscriptProvider implements TranscriptProvider
{
    public function __construct(
        private readonly YtDlpGateway $gateway,
        private readonly CaptionTrackSelector $trackSelector,
        private readonly Json3TranscriptParser $parser,
        private readonly YtDlpTranscriptMapper $mapper,
    ) {}

    public function fetch(string $providerVideoId): TranscriptData
    {
        $metadata = $this->gateway->fetchMetadata($providerVideoId);
        $track = $this->trackSelector->select($metadata);
        $segments = $this->parser->parse($this->gateway->fetchCaption($providerVideoId, $track));

        if ($segments === []) {
            throw new TranscriptNotAvailableException('The selected caption track contains no transcript segments.');
        }

        return $this->mapper->map($metadata, $track, $segments);
    }
}
