<?php

use App\Transcript\Exceptions\TranscriptProviderException;
use App\Transcript\YtDlp\Json3TranscriptParser;

function json3Fixture(string $name): string
{
    return file_get_contents(__DIR__."/../Fixtures/YtDlp/{$name}.json3");
}

test('JSON3 cues are mapped with timestamps multiple parts and Unicode intact', function () {
    $segments = (new Json3TranscriptParser(100))->parse(json3Fixture('caption-manual'));

    expect($segments)->toHaveCount(3)
        ->and($segments[0]->startMs)->toBe(0)
        ->and($segments[0]->endMs)->toBe(2500)
        ->and($segments[0]->text)->toBe('Texto demonstrativo.')
        ->and($segments[1]->text)->toBe('Unicode: ação, café e 🎬.')
        ->and($segments[2]->text)->toBe('Texto <b>literal</b>, não HTML confiável.');
});

test('empty events are ignored and line breaks are normalized', function () {
    $json = json_encode([
        'events' => [
            ['tStartMs' => 0, 'dDurationMs' => 1000, 'segs' => [['utf8' => "Primeira\n linha"]]],
            ['tStartMs' => 1000, 'dDurationMs' => 1000, 'segs' => [['utf8' => '  ']]],
            ['tStartMs' => 2000, 'dDurationMs' => 0, 'segs' => [['utf8' => 'ignorar']]],
        ],
    ], JSON_THROW_ON_ERROR);

    $segments = (new Json3TranscriptParser(100))->parse($json);

    expect($segments)->toHaveCount(1)
        ->and($segments[0]->text)->toBe('Primeira linha');
});

test('rolling ASR prefixes are consolidated while unrelated overlapping text is preserved', function () {
    $segments = (new Json3TranscriptParser(100))->parse(json3Fixture('caption-asr-rolling'));

    expect($segments)->toHaveCount(3)
        ->and($segments[0]->text)->toBe('Construa com segurança')
        ->and($segments[0]->startMs)->toBe(0)
        ->and($segments[0]->endMs)->toBe(4700)
        ->and($segments[1]->text)->toBe('sem apagar repetições válidas')
        ->and($segments[2]->text)->toBe('olá olá, mundo');
});

test('malformed and excessive JSON3 documents are rejected', function () {
    expect(fn () => (new Json3TranscriptParser(10))->parse('{invalid'))
        ->toThrow(TranscriptProviderException::class)
        ->and(fn () => (new Json3TranscriptParser(1))->parse(json3Fixture('caption-manual')))
        ->toThrow(TranscriptProviderException::class);
});
