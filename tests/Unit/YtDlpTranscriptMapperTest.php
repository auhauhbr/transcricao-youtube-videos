<?php

use App\Transcript\Data\TranscriptSegmentData;
use App\Transcript\YtDlp\CaptionTrack;
use App\Transcript\YtDlp\CaptionTrackKind;
use App\Transcript\YtDlp\YtDlpTranscriptMapper;

test('yt-dlp metadata and chapters are mapped to internal transcript data', function () {
    $metadata = ytDlpMetadataFixture('metadata-manual-chapters');
    $track = new CaptionTrack('pt-BR', 'Português (Brasil)', CaptionTrackKind::Manual);
    $segments = [new TranscriptSegmentData(0, 1000, 'Texto demonstrativo.')];

    $transcript = (new YtDlpTranscriptMapper)->map($metadata, $track, $segments);

    expect($transcript->video->providerVideoId)->toBe('dQw4w9WgXcQ')
        ->and($transcript->video->durationSeconds)->toBe(185)
        ->and($transcript->video->thumbnailUrl)->toBe('https://i.example.test/thumb.jpg')
        ->and($transcript->chapters)->toHaveCount(3)
        ->and(array_map(fn ($chapter): string => $chapter->title, $transcript->chapters))
        ->toBe(['Introdução', 'Desenvolvimento', 'Conclusão'])
        ->and($transcript->chapters[0]->startMs)->toBe(0)
        ->and($transcript->chapters[0]->endMs)->toBe(60_125)
        ->and($transcript->chapters[2]->endMs)->toBe(185_000);
});

test('chapters are optional and unsafe thumbnails do not leak into the DTO', function () {
    $metadata = ytDlpMetadataFixture('metadata-asr');
    $track = new CaptionTrack('en-orig', 'English (Original)', CaptionTrackKind::Automatic);

    $transcript = (new YtDlpTranscriptMapper)->map(
        $metadata,
        $track,
        [new TranscriptSegmentData(0, 1000, 'Example.')],
    );

    expect($transcript->chapters)->toBe([])
        ->and($transcript->video->thumbnailUrl)->toBeNull()
        ->and($transcript->video->channelName)->toBe('Canal automático');
});
