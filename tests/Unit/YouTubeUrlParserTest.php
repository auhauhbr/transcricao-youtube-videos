<?php

use App\Support\YouTube\InvalidYouTubeUrlException;
use App\Support\YouTube\YouTubeUrlParser;

dataset('valid YouTube URLs', [
    'watch with www' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    'watch without www' => ['https://youtube.com/watch?v=dQw4w9WgXcQ'],
    'short URL' => ['https://youtu.be/dQw4w9WgXcQ'],
    'shorts with www' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ'],
    'shorts without www' => ['https://youtube.com/shorts/dQw4w9WgXcQ'],
    'embed with www' => ['https://www.youtube.com/embed/dQw4w9WgXcQ'],
    'embed without www' => ['https://youtube.com/embed/dQw4w9WgXcQ'],
    'watch with timestamp' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=120'],
    'short URL with timestamp' => ['https://youtu.be/dQw4w9WgXcQ?t=120'],
    'watch with reordered query parameters' => ['https://youtube.com/watch?list=PL123&t=120&v=dQw4w9WgXcQ'],
]);

dataset('invalid YouTube URLs', [
    'empty value' => [''],
    'plain text' => ['not a URL'],
    'URL without scheme' => ['youtube.com/watch?v=dQw4w9WgXcQ'],
    'insecure scheme' => ['http://youtube.com/watch?v=dQw4w9WgXcQ'],
    'lookalike YouTube subdomain' => ['https://youtube.com.evil.com/watch?v=dQw4w9WgXcQ'],
    'lookalike YouTube domain prefix' => ['https://evilyoutube.com/watch?v=dQw4w9WgXcQ'],
    'lookalike hyphenated domain' => ['https://youtube-example.com/watch?v=dQw4w9WgXcQ'],
    'lookalike short domain' => ['https://youtu.be.evil.com/dQw4w9WgXcQ'],
    'unrelated domain' => ['https://example.com/watch?v=dQw4w9WgXcQ'],
    'misleading user information' => ['https://evil.example@youtube.com/watch?v=dQw4w9WgXcQ'],
    'custom port' => ['https://youtube.com:8443/watch?v=dQw4w9WgXcQ'],
    'watch without video id' => ['https://youtube.com/watch'],
    'watch with array video id' => ['https://youtube.com/watch?v[]=dQw4w9WgXcQ'],
    'short URL without video id' => ['https://youtu.be/'],
    'shorts without video id' => ['https://youtube.com/shorts/'],
    'embed without video id' => ['https://youtube.com/embed/'],
    'video id too short' => ['https://youtube.com/watch?v=dQw4w9WgXc'],
    'video id too long' => ['https://youtube.com/watch?v=dQw4w9WgXcQ1'],
    'video id with invalid character' => ['https://youtu.be/dQw4w9WgXc!'],
    'unexpected path after video id' => ['https://youtu.be/dQw4w9WgXcQ/more'],
    'malformed URL' => ['https://[youtube.com/watch?v=dQw4w9WgXcQ'],
]);

test('it extracts a normalized video id from a supported URL', function (string $url) {
    expect((new YouTubeUrlParser)->parse($url))->toBe('dQw4w9WgXcQ');
})->with('valid YouTube URLs');

test('it accepts every character allowed in a YouTube video id', function () {
    expect((new YouTubeUrlParser)->parse('https://youtu.be/AbC_12-xyZ9'))->toBe('AbC_12-xyZ9');
});

test('it rejects invalid or unsupported URLs predictably', function (string $url) {
    expect(fn () => (new YouTubeUrlParser)->parse($url))
        ->toThrow(InvalidYouTubeUrlException::class, 'The value must be a valid YouTube video URL.');
})->with('invalid YouTube URLs');
