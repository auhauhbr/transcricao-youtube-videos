<?php

namespace App\Transcript\YtDlp;

use App\Transcript\Exceptions\TranscriptNotAvailableException;

final class CaptionTrackSelector
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function select(array $metadata, ?string $preferredLanguage = null): CaptionTrack
    {
        $videoLanguage = $preferredLanguage ?? $this->stringValue($metadata['language'] ?? null);
        $manual = $this->manualTracks($metadata['subtitles'] ?? null);

        if ($manual !== []) {
            return $this->preferred($manual, $videoLanguage);
        }

        $automatic = $this->originalAutomaticTracks($metadata['automatic_captions'] ?? null);

        if ($automatic !== []) {
            return $this->preferred($automatic, $videoLanguage);
        }

        throw new TranscriptNotAvailableException('No native caption track is available for this video.');
    }

    /**
     * @return list<CaptionTrack>
     */
    private function manualTracks(mixed $tracks): array
    {
        if (! is_array($tracks)) {
            return [];
        }

        $result = [];

        foreach ($tracks as $languageCode => $formats) {
            if (! is_string($languageCode) || ! $this->validLanguageCode($languageCode) || ! is_array($formats) || $formats === []) {
                continue;
            }

            $result[] = new CaptionTrack(
                $languageCode,
                $this->trackName($formats, $languageCode),
                CaptionTrackKind::Manual,
            );
        }

        return $result;
    }

    /**
     * @return list<CaptionTrack>
     */
    private function originalAutomaticTracks(mixed $tracks): array
    {
        if (! is_array($tracks)) {
            return [];
        }

        $result = [];

        foreach ($tracks as $languageCode => $formats) {
            if (! is_string($languageCode) || ! $this->validLanguageCode($languageCode) || ! is_array($formats)) {
                continue;
            }

            $nativeFormats = array_values(array_filter(
                $formats,
                fn (mixed $format): bool => is_array($format) && $this->isNativeAutomaticFormat($format),
            ));

            if ($nativeFormats === []) {
                continue;
            }

            $result[] = new CaptionTrack(
                $languageCode,
                $this->trackName($nativeFormats, $languageCode),
                CaptionTrackKind::Automatic,
            );
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $format
     */
    private function isNativeAutomaticFormat(array $format): bool
    {
        $url = $this->stringValue($format['url'] ?? null);

        if ($url === null) {
            return false;
        }

        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query)) {
            return true;
        }

        parse_str($query, $parameters);

        foreach (array_keys($parameters) as $key) {
            if (strtolower((string) $key) === 'tlang') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<CaptionTrack>  $tracks
     */
    private function preferred(array $tracks, ?string $language): CaptionTrack
    {
        usort($tracks, function (CaptionTrack $left, CaptionTrack $right) use ($language): int {
            $score = fn (CaptionTrack $track): array => [
                $this->languageScore($track->languageCode, $language),
                strtolower($track->languageCode),
            ];

            return $score($left) <=> $score($right);
        });

        return $tracks[0];
    }

    private function languageScore(string $candidate, ?string $preferred): int
    {
        if ($preferred === null || $preferred === '') {
            return str_ends_with(strtolower($candidate), '-orig') ? 0 : 1;
        }

        $candidate = strtolower($candidate);
        $preferred = strtolower($preferred);
        $withoutOriginalSuffix = preg_replace('/-orig$/', '', $candidate) ?? $candidate;

        return match (true) {
            $candidate === $preferred => 0,
            $withoutOriginalSuffix === $preferred => 1,
            str_starts_with($candidate, $preferred.'-') => 2,
            str_starts_with($preferred, $candidate.'-') => 3,
            str_ends_with($candidate, '-orig') => 4,
            default => 5,
        };
    }

    /**
     * @param  array<int|string, mixed>  $formats
     */
    private function trackName(array $formats, string $fallback): string
    {
        foreach ($formats as $format) {
            if (is_array($format) && ($name = $this->stringValue($format['name'] ?? null)) !== null) {
                return $name;
            }
        }

        return $fallback;
    }

    private function validLanguageCode(string $languageCode): bool
    {
        return preg_match('/\A[A-Za-z0-9._-]{1,64}\z/', $languageCode) === 1;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
