<?php

use App\Transcript\Export\TranscriptTimestampFormatter;

test('timestamps use minutes below one hour and hours at or above one hour', function (int $milliseconds, string $expected) {
    expect((new TranscriptTimestampFormatter)->format($milliseconds))->toBe($expected);
})->with([
    'three seconds without rounding up' => [3_999, '00:03'],
    'twelve minutes' => [748_000, '12:28'],
    'last second below one hour' => [3_599_999, '59:59'],
    'one hour' => [3_603_000, '01:00:03'],
    'more than two hours' => [8_142_000, '02:15:42'],
    'negative values are clamped' => [-1, '00:00'],
]);
