<?php

namespace App\Providers;

use App\Transcript\Contracts\TranscriptProvider;
use App\Transcript\Providers\FakeTranscriptProvider;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TranscriptProvider::class, function (): TranscriptProvider {
            return match (config('transcripts.provider')) {
                'fake' => new FakeTranscriptProvider,
                default => throw new LogicException('A transcript provider is not configured for this environment.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
