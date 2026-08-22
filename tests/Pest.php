<?php

use App\Transcript\YtDlp\YtDlpErrorClassifier;
use App\Transcript\YtDlp\YtDlpGateway;
use Tests\Support\FakeYtDlpProcessRunner;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'External');

function ytDlpMetadataFixture(string $name): array
{
    return json_decode(
        file_get_contents(__DIR__."/Fixtures/YtDlp/{$name}.json"),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
}

function ytDlpGateway(FakeYtDlpProcessRunner $runner, string $temporaryPath): YtDlpGateway
{
    return new YtDlpGateway(
        runner: $runner,
        errorClassifier: new YtDlpErrorClassifier,
        jsRuntime: 'node',
        temporaryPath: $temporaryPath,
        maxMetadataBytes: 1024 * 1024,
        maxProcessStdoutBytes: 64 * 1024,
        maxCaptionBytes: 1024 * 1024,
    );
}
