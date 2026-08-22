<?php

namespace App\Providers;

use App\Transcript\Contracts\TranscriptProvider;
use App\Transcript\Providers\FakeTranscriptProvider;
use App\Transcript\Providers\YouTubeTranscriptProvider;
use App\Transcript\YtDlp\Json3TranscriptParser;
use App\Transcript\YtDlp\YtDlpErrorClassifier;
use App\Transcript\YtDlp\YtDlpGateway;
use App\Transcript\YtDlp\YtDlpProcessRunner;
use App\Transcript\YtDlp\YtDlpProcessRunnerContract;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(YtDlpProcessRunnerContract::class, fn (): YtDlpProcessRunner => new YtDlpProcessRunner(
            binary: (string) config('transcripts.yt_dlp.binary'),
            timeoutSeconds: (float) config('transcripts.yt_dlp.timeout_seconds'),
            idleTimeoutSeconds: (float) config('transcripts.yt_dlp.idle_timeout_seconds'),
            maxStderrBytes: (int) config('transcripts.yt_dlp.max_stderr_bytes'),
        ));

        $this->app->bind(YtDlpGateway::class, fn (): YtDlpGateway => new YtDlpGateway(
            runner: $this->app->make(YtDlpProcessRunnerContract::class),
            errorClassifier: $this->app->make(YtDlpErrorClassifier::class),
            jsRuntime: (string) config('transcripts.yt_dlp.js_runtime'),
            temporaryPath: (string) config('transcripts.yt_dlp.temporary_path'),
            maxMetadataBytes: (int) config('transcripts.yt_dlp.max_metadata_bytes'),
            maxProcessStdoutBytes: (int) config('transcripts.yt_dlp.max_process_stdout_bytes'),
            maxCaptionBytes: (int) config('transcripts.yt_dlp.max_caption_bytes'),
        ));

        $this->app->bind(Json3TranscriptParser::class, fn (): Json3TranscriptParser => new Json3TranscriptParser(
            maxSegments: (int) config('transcripts.yt_dlp.max_segments'),
        ));

        $this->app->bind(TranscriptProvider::class, function (): TranscriptProvider {
            return match (config('transcripts.provider')) {
                'fake' => new FakeTranscriptProvider,
                'yt_dlp' => $this->app->make(YouTubeTranscriptProvider::class),
                default => throw new LogicException('A transcript provider is not configured for this environment.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('transcript-extractions', fn (Request $request): array => [
            Limit::perMinute(5)->by('minute:'.$request->ip()),
            Limit::perHour(20)->by('hour:'.$request->ip()),
        ]);

        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by(hash('sha256', $email.'|'.$request->ip()));
        });

        RateLimiter::for('register', fn (Request $request): Limit => Limit::perHour(5)->by((string) $request->ip()));
    }
}
