<?php

use App\Transcript\Exceptions\TranscriptNotAvailableException;
use App\Transcript\YtDlp\CaptionTrackKind;
use App\Transcript\YtDlp\CaptionTrackSelector;

test('manual captions in the video language have priority over automatic captions', function () {
    $track = (new CaptionTrackSelector)->select(ytDlpMetadataFixture('metadata-manual-chapters'));

    expect($track->kind)->toBe(CaptionTrackKind::Manual)
        ->and($track->languageCode)->toBe('pt-BR')
        ->and($track->languageName)->toBe('Português (Brasil)');
});

test('an original ASR track is the fallback when manual captions are absent', function () {
    $track = (new CaptionTrackSelector)->select(ytDlpMetadataFixture('metadata-asr'));

    expect($track->kind)->toBe(CaptionTrackKind::Automatic)
        ->and($track->languageCode)->toBe('en');
});

test('automatic translations are never selected as native captions', function () {
    $metadata = ytDlpMetadataFixture('metadata-asr');
    unset($metadata['automatic_captions']['en'], $metadata['automatic_captions']['en-orig']);

    expect(fn () => (new CaptionTrackSelector)->select($metadata))
        ->toThrow(TranscriptNotAvailableException::class);
});

test('a future preferred language can influence selection without changing the provider contract', function () {
    $track = (new CaptionTrackSelector)->select(
        ytDlpMetadataFixture('metadata-manual-chapters'),
        preferredLanguage: 'en',
    );

    expect($track->languageCode)->toBe('en');
});

test('videos without captions fail explicitly', function () {
    expect(fn () => (new CaptionTrackSelector)->select(ytDlpMetadataFixture('metadata-no-captions')))
        ->toThrow(TranscriptNotAvailableException::class);
});
