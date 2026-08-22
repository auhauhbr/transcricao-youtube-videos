<?php

namespace App\Transcript\YtDlp;

interface YtDlpProcessRunnerContract
{
    /**
     * @param  list<string>  $arguments
     */
    public function run(array $arguments, int $maxStdoutBytes): YtDlpProcessResult;
}
