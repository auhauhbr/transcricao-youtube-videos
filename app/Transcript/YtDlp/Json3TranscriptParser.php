<?php

namespace App\Transcript\YtDlp;

use App\Transcript\Data\TranscriptSegmentData;
use App\Transcript\Exceptions\TranscriptProviderException;
use JsonException;

final class Json3TranscriptParser
{
    public function __construct(private readonly int $maxSegments) {}

    /**
     * @return list<TranscriptSegmentData>
     */
    public function parse(string $json): array
    {
        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TranscriptProviderException('The caption document is not valid JSON3.', previous: $exception);
        }

        if (! is_array($document) || ! isset($document['events']) || ! is_array($document['events'])) {
            throw new TranscriptProviderException('The caption document does not contain JSON3 events.');
        }

        if (count($document['events']) > $this->maxSegments * 4) {
            throw new TranscriptProviderException('The caption document contains too many events.');
        }

        /** @var list<array{start: int, end: int, text: string, window: string|null}> $normalized */
        $normalized = [];

        foreach ($document['events'] as $event) {
            if (! is_array($event)) {
                continue;
            }

            $segment = $this->normalizeEvent($event);

            if ($segment === null) {
                continue;
            }

            $lastIndex = array_key_last($normalized);

            if ($lastIndex !== null && $this->mergeRollingCue($normalized[$lastIndex], $segment)) {
                continue;
            }

            $normalized[] = $segment;

            if (count($normalized) > $this->maxSegments) {
                throw new TranscriptProviderException('The transcript contains too many segments.');
            }
        }

        return array_map(
            fn (array $segment): TranscriptSegmentData => new TranscriptSegmentData(
                $segment['start'],
                $segment['end'],
                $segment['text'],
            ),
            $normalized,
        );
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array{start: int, end: int, text: string, window: string|null}|null
     */
    private function normalizeEvent(array $event): ?array
    {
        $start = $this->milliseconds($event['tStartMs'] ?? null);
        $duration = $this->milliseconds($event['dDurationMs'] ?? null);

        if ($start === null || $duration === null || $duration <= 0 || ! isset($event['segs']) || ! is_array($event['segs'])) {
            return null;
        }

        $parts = [];

        foreach ($event['segs'] as $part) {
            if (is_array($part) && is_string($part['utf8'] ?? null)) {
                $parts[] = $part['utf8'];
            }
        }

        $text = $this->normalizeText(implode('', $parts));

        if ($text === '') {
            return null;
        }

        $window = $event['wWinId'] ?? null;

        return [
            'start' => $start,
            'end' => $start + $duration,
            'text' => $text,
            'window' => is_int($window) || is_string($window) ? (string) $window : null,
        ];
    }

    /**
     * @param  array{start: int, end: int, text: string, window: string|null}  $previous
     * @param  array{start: int, end: int, text: string, window: string|null}  $current
     */
    private function mergeRollingCue(array &$previous, array $current): bool
    {
        if ($previous['window'] === null || $current['window'] !== $previous['window'] || $current['start'] >= $previous['end']) {
            return false;
        }

        if ($current['text'] === $previous['text']) {
            $previous['end'] = max($previous['end'], $current['end']);

            return true;
        }

        if (str_starts_with($current['text'], $previous['text'])) {
            $previous['text'] = $current['text'];
            $previous['end'] = max($previous['end'], $current['end']);

            return true;
        }

        if (str_starts_with($previous['text'], $current['text'])) {
            $previous['end'] = max($previous['end'], $current['end']);

            return true;
        }

        return false;
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\t\f\v ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n+ */u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function milliseconds(mixed $value): ?int
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }

        $milliseconds = (int) round((float) $value);

        return $milliseconds >= 0 ? $milliseconds : null;
    }
}
