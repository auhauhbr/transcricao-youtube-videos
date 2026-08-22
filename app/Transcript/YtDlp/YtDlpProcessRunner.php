<?php

namespace App\Transcript\YtDlp;

use App\Transcript\Exceptions\TranscriptOutputLimitException;
use App\Transcript\Exceptions\TranscriptProviderException;
use App\Transcript\Exceptions\TranscriptProviderTimeoutException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

final class YtDlpProcessRunner implements YtDlpProcessRunnerContract
{
    public function __construct(
        private readonly string $binary,
        private readonly float $timeoutSeconds,
        private readonly float $idleTimeoutSeconds,
        private readonly int $maxStderrBytes,
    ) {}

    public function run(array $arguments, int $maxStdoutBytes): YtDlpProcessResult
    {
        $process = new Process([$this->binary, ...$arguments]);
        $process->setTimeout($this->timeoutSeconds);
        $process->setIdleTimeout($this->idleTimeoutSeconds);

        $stdout = '';
        $stderr = '';

        try {
            $exitCode = $process->run(function (string $type, string $buffer) use (&$stdout, &$stderr, $maxStdoutBytes, $process): void {
                if ($type === Process::OUT) {
                    $stdout .= $buffer;

                    if (strlen($stdout) > $maxStdoutBytes) {
                        $process->stop(0.1);
                        throw new TranscriptOutputLimitException('yt-dlp exceeded the stdout limit.');
                    }

                    return;
                }

                $stderr .= $buffer;

                if (strlen($stderr) > $this->maxStderrBytes) {
                    $process->stop(0.1);
                    throw new TranscriptOutputLimitException('yt-dlp exceeded the stderr limit.');
                }
            });
        } catch (ProcessTimedOutException $exception) {
            throw new TranscriptProviderTimeoutException('yt-dlp exceeded its execution timeout.', previous: $exception);
        } catch (Throwable $exception) {
            if ($exception instanceof TranscriptProviderException) {
                throw $exception;
            }

            throw new TranscriptProviderException('yt-dlp could not be executed.', previous: $exception);
        }

        return new YtDlpProcessResult($exitCode, $stdout, $stderr);
    }
}
