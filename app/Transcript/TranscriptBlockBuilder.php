<?php

namespace App\Transcript;

final class TranscriptBlockBuilder
{
    public const SOFT_BREAK_MS = 24_000;

    public const TARGET_DURATION_MS = 30_000;

    public const HARD_BREAK_MS = 42_000;

    public const MAX_CHARACTERS = 1_200;

    /**
     * @param  iterable<array{position: int, startMs: int, endMs: int, text: string}>  $segments
     * @param  iterable<array{position: int, title: string, startMs: int, endMs: int}>  $chapters
     * @return list<array{position: int, startMs: int, endMs: int, text: string, chapterPosition: int|null}>
     */
    public function build(iterable $segments, iterable $chapters): array
    {
        $orderedSegments = [...$segments];
        $orderedChapters = [...$chapters];

        usort($orderedSegments, fn (array $left, array $right): int => $left['position'] <=> $right['position']);
        usort($orderedChapters, fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        $blocks = [];
        $buffer = [];
        $currentChapterPosition = null;
        $chapterIndex = 0;

        foreach ($orderedSegments as $segment) {
            $text = trim($segment['text']);

            if ($text === '') {
                continue;
            }

            $chapter = $this->chapterAt($segment['startMs'], $orderedChapters, $chapterIndex);
            $chapterPosition = $chapter['position'] ?? null;

            if ($buffer !== [] && $chapterPosition !== $currentChapterPosition) {
                $this->appendBlock($blocks, $buffer, $currentChapterPosition);
                $buffer = [];
            }

            $currentChapterPosition = $chapterPosition;
            $boundedEndMs = max($segment['startMs'], $segment['endMs']);

            if ($chapter !== null) {
                $boundedEndMs = min($boundedEndMs, $chapter['endMs']);
            }

            if ($buffer !== [] && $this->characterCount($buffer) + 1 + mb_strlen($text) > self::MAX_CHARACTERS) {
                $this->appendBlock($blocks, $buffer, $currentChapterPosition);
                $buffer = [];
            }

            $buffer[] = [
                'startMs' => $segment['startMs'],
                'endMs' => $boundedEndMs,
                'text' => $text,
            ];

            if ($this->duration($buffer) >= self::HARD_BREAK_MS || $this->characterCount($buffer) >= self::MAX_CHARACTERS) {
                $this->appendBlock($blocks, $buffer, $currentChapterPosition);
                $buffer = [];

                continue;
            }

            if ($this->duration($buffer) < self::TARGET_DURATION_MS) {
                continue;
            }

            $naturalBoundary = $this->latestNaturalBoundary($buffer);

            if ($naturalBoundary === null) {
                continue;
            }

            $this->appendBlock($blocks, array_slice($buffer, 0, $naturalBoundary + 1), $currentChapterPosition);
            $buffer = array_slice($buffer, $naturalBoundary + 1);
        }

        if ($buffer !== []) {
            $this->appendBlock($blocks, $buffer, $currentChapterPosition);
        }

        return $blocks;
    }

    /**
     * @param  list<array{position: int, title: string, startMs: int, endMs: int}>  $chapters
     * @return array{position: int, title: string, startMs: int, endMs: int}|null
     */
    private function chapterAt(int $startMs, array $chapters, int &$chapterIndex): ?array
    {
        while (isset($chapters[$chapterIndex]) && $startMs >= $chapters[$chapterIndex]['endMs']) {
            $chapterIndex++;
        }

        $chapter = $chapters[$chapterIndex] ?? null;

        return $chapter !== null && $startMs >= $chapter['startMs'] && $startMs < $chapter['endMs']
            ? $chapter
            : null;
    }

    /** @param list<array{startMs: int, endMs: int, text: string}> $buffer */
    private function latestNaturalBoundary(array $buffer): ?int
    {
        for ($index = count($buffer) - 1; $index >= 0; $index--) {
            $candidate = array_slice($buffer, 0, $index + 1);

            if ($this->duration($candidate) < self::SOFT_BREAK_MS) {
                return null;
            }

            if (preg_match('/[.!?…]["\'”’\)\]]*$/u', $buffer[$index]['text']) === 1) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  list<array{position: int, startMs: int, endMs: int, text: string, chapterPosition: int|null}>  $blocks
     * @param  list<array{startMs: int, endMs: int, text: string}>  $segments
     */
    private function appendBlock(array &$blocks, array $segments, ?int $chapterPosition): void
    {
        $blocks[] = [
            'position' => count($blocks),
            'startMs' => $segments[0]['startMs'],
            'endMs' => $segments[array_key_last($segments)]['endMs'],
            'text' => implode(' ', array_column($segments, 'text')),
            'chapterPosition' => $chapterPosition,
        ];
    }

    /** @param list<array{startMs: int, endMs: int, text: string}> $segments */
    private function duration(array $segments): int
    {
        return $segments[array_key_last($segments)]['endMs'] - $segments[0]['startMs'];
    }

    /** @param list<array{startMs: int, endMs: int, text: string}> $segments */
    private function characterCount(array $segments): int
    {
        return mb_strlen(implode(' ', array_column($segments, 'text')));
    }
}
