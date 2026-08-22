<?php

namespace App\Transcript\YtDlp;

use App\Transcript\Exceptions\TranscriptProviderBlockedException;
use App\Transcript\Exceptions\TranscriptProviderException;
use App\Transcript\Exceptions\VideoUnavailableException;

final class YtDlpErrorClassifier
{
    public function classify(YtDlpProcessResult $result): TranscriptProviderException
    {
        $output = strtolower($result->stderr."\n".$result->stdout);

        if ($this->containsAny($output, [
            'sign in to confirm you’re not a bot',
            "sign in to confirm you're not a bot",
            'too many requests',
            'http error 429',
            'captcha',
            'temporarily blocked',
            'http error 403',
        ])) {
            return new TranscriptProviderBlockedException('The transcript provider blocked the extraction request.');
        }

        if ($this->containsAny($output, [
            'video unavailable',
            'private video',
            'has been removed',
            'this video is not available',
        ])) {
            return new VideoUnavailableException('The requested video is unavailable.');
        }

        return new TranscriptProviderException("yt-dlp failed with exit code {$result->exitCode}.");
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
