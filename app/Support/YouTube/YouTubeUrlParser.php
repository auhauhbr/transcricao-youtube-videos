<?php

namespace App\Support\YouTube;

final class YouTubeUrlParser
{
    private const array YOUTUBE_HOSTS = ['youtube.com', 'www.youtube.com'];

    private const string SHORT_HOST = 'youtu.be';

    private const string VIDEO_ID_PATTERN = '/\A[A-Za-z0-9_-]{11}\z/';

    public function parse(string $url): string
    {
        $url = trim($url);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidYouTubeUrlException;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            throw new InvalidYouTubeUrlException;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (! is_string($scheme) || strtolower($scheme) !== 'https' || ! is_string($host)) {
            throw new InvalidYouTubeUrlException;
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new InvalidYouTubeUrlException;
        }

        $host = strtolower($host);

        if ($host === self::SHORT_HOST) {
            return $this->parseShortUrl($parts);
        }

        if (in_array($host, self::YOUTUBE_HOSTS, true)) {
            return $this->parseYouTubeUrl($parts);
        }

        throw new InvalidYouTubeUrlException;
    }

    /**
     * @param  array<string, int|string>  $parts
     */
    private function parseYouTubeUrl(array $parts): string
    {
        $path = $parts['path'] ?? '';

        if (! is_string($path)) {
            throw new InvalidYouTubeUrlException;
        }

        if (preg_match('#\A/watch/?\z#', $path) === 1) {
            $queryString = $parts['query'] ?? '';

            if (! is_string($queryString)) {
                throw new InvalidYouTubeUrlException;
            }

            parse_str($queryString, $query);

            return $this->validateVideoId($query['v'] ?? null);
        }

        if (preg_match('#\A/(?:shorts|embed)/([^/]+)/?\z#', $path, $matches) === 1) {
            return $this->validateVideoId($matches[1]);
        }

        throw new InvalidYouTubeUrlException;
    }

    /**
     * @param  array<string, int|string>  $parts
     */
    private function parseShortUrl(array $parts): string
    {
        $path = $parts['path'] ?? '';

        if (! is_string($path) || preg_match('#\A/([^/]+)/?\z#', $path, $matches) !== 1) {
            throw new InvalidYouTubeUrlException;
        }

        return $this->validateVideoId($matches[1]);
    }

    private function validateVideoId(mixed $videoId): string
    {
        if (! is_string($videoId) || preg_match(self::VIDEO_ID_PATTERN, $videoId) !== 1) {
            throw new InvalidYouTubeUrlException;
        }

        return $videoId;
    }
}
