<?php

namespace Tests\Support;

use App\Transcript\YtDlp\YtDlpProcessResult;
use App\Transcript\YtDlp\YtDlpProcessRunnerContract;
use Closure;

final class FakeYtDlpProcessRunner implements YtDlpProcessRunnerContract
{
    /** @var list<array{arguments: list<string>, maxStdoutBytes: int}> */
    public array $calls = [];

    private Closure $handler;

    public function __construct(callable $handler)
    {
        $this->handler = Closure::fromCallable($handler);
    }

    public function run(array $arguments, int $maxStdoutBytes): YtDlpProcessResult
    {
        $this->calls[] = compact('arguments', 'maxStdoutBytes');

        return ($this->handler)($arguments, $maxStdoutBytes);
    }
}
