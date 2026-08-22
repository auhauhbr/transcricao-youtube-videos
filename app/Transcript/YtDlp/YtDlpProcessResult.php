<?php

namespace App\Transcript\YtDlp;

final readonly class YtDlpProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }
}
