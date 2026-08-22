<?php

use App\Transcript\TranscriptBlockBuilder;

function timedSegments(int $count, int $durationMs = 2_000, ?callable $text = null): array
{
    return array_map(
        fn (int $position): array => [
            'position' => $position,
            'startMs' => $position * $durationMs,
            'endMs' => ($position + 1) * $durationMs,
            'text' => $text ? $text($position) : "trecho {$position}",
        ],
        range(0, $count - 1),
    );
}

test('many short cues become substantially fewer blocks near the target duration', function () {
    $segments = timedSegments(60, 2_000, fn (int $position): string => ($position + 1) % 15 === 0
        ? "fim da frase {$position}."
        : "trecho {$position}");

    $blocks = (new TranscriptBlockBuilder)->build($segments, []);

    expect($blocks)->toHaveCount(4)
        ->and(array_column($blocks, 'startMs'))->toBe([0, 30_000, 60_000, 90_000])
        ->and(array_column($blocks, 'endMs'))->toBe([30_000, 60_000, 90_000, 120_000]);
});

test('a sentence ending shortly before the target is preferred as a soft break', function () {
    $segments = timedSegments(12, 3_000, fn (int $position): string => $position === 8 ? 'fim natural.' : "trecho {$position}");

    $blocks = (new TranscriptBlockBuilder)->build($segments, []);

    expect($blocks[0]['endMs'])->toBe(27_000)
        ->and($blocks[1]['startMs'])->toBe(27_000);
});

test('speech without punctuation is constrained by the hard time limit', function () {
    $blocks = (new TranscriptBlockBuilder)->build(timedSegments(30, 3_000), []);

    expect($blocks[0]['endMs'] - $blocks[0]['startMs'])->toBe(TranscriptBlockBuilder::HARD_BREAK_MS)
        ->and(count($blocks))->toBeGreaterThan(1);
});

test('chapter boundaries always finish the current block', function () {
    $chapters = [
        ['position' => 0, 'title' => 'Primeiro', 'startMs' => 0, 'endMs' => 23_000],
        ['position' => 1, 'title' => 'Segundo', 'startMs' => 23_000, 'endMs' => 60_000],
    ];

    $blocks = (new TranscriptBlockBuilder)->build(timedSegments(20, 3_000), $chapters);

    expect($blocks[0]['chapterPosition'])->toBe(0)
        ->and($blocks[0]['endMs'])->toBe(23_000)
        ->and($blocks[1]['chapterPosition'])->toBe(1)
        ->and($blocks[1]['startMs'])->toBe(24_000);
});

test('grouping preserves every cue once and protects against excessive text', function () {
    $segments = timedSegments(20, 1_000, fn (int $position): string => str_repeat((string) ($position % 10), 150));
    $blocks = (new TranscriptBlockBuilder)->build($segments, []);

    expect(implode(' ', array_column($blocks, 'text')))->toBe(implode(' ', array_column($segments, 'text')))
        ->and(count($blocks))->toBeGreaterThan(1)
        ->and(max(array_map('mb_strlen', array_column($blocks, 'text'))))->toBeLessThanOrEqual(TranscriptBlockBuilder::MAX_CHARACTERS);
});
