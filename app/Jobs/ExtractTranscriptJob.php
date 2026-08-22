<?php

namespace App\Jobs;

use App\Actions\PersistTranscriptData;
use App\Enums\ExtractionErrorCode;
use App\Enums\ExtractionStatus;
use App\Models\Extraction;
use App\Transcript\Contracts\TranscriptProvider;
use App\Transcript\Exceptions\TranscriptNotAvailableException;
use App\Transcript\Exceptions\TranscriptOutputLimitException;
use App\Transcript\Exceptions\TranscriptProviderBlockedException;
use App\Transcript\Exceptions\TranscriptProviderException;
use App\Transcript\Exceptions\TranscriptProviderTimeoutException;
use App\Transcript\Exceptions\VideoUnavailableException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ExtractTranscriptJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 150;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 1800;

    public function __construct(public readonly int $extractionId)
    {
        $this->onQueue('transcripts');
    }

    public function uniqueId(): string
    {
        return (string) $this->extractionId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(TranscriptProvider $provider, PersistTranscriptData $persister): void
    {
        $extraction = Extraction::query()->with('video')->find($this->extractionId);

        if ($extraction === null || in_array($extraction->status, [ExtractionStatus::Ready, ExtractionStatus::Failed], true)) {
            return;
        }

        if ($extraction->status === ExtractionStatus::Pending) {
            $extraction->markProcessing();
        }

        $startedAt = microtime(true);

        try {
            $data = $provider->fetch($extraction->video->provider_video_id);
            $persister->handle($extraction, $data);
            $extraction->refresh();

            Log::info('Transcript extraction completed.', $this->logContext($extraction, microtime(true) - $startedAt));
        } catch (TranscriptNotAvailableException $exception) {
            $this->markTerminalFailure($extraction, ExtractionErrorCode::TranscriptNotAvailable, 'A transcrição não está disponível para este vídeo.', $exception);
        } catch (VideoUnavailableException $exception) {
            $this->markTerminalFailure($extraction, ExtractionErrorCode::VideoUnavailable, 'O vídeo solicitado não está disponível.', $exception);
        } catch (TranscriptProviderBlockedException $exception) {
            $this->markTerminalFailure($extraction, ExtractionErrorCode::ProviderBlocked, 'O provedor bloqueou temporariamente a solicitação.', $exception);
        } catch (TranscriptOutputLimitException $exception) {
            $this->markTerminalFailure($extraction, ExtractionErrorCode::OutputLimit, 'A resposta do provedor excedeu o limite permitido.', $exception);
        } catch (TranscriptProviderTimeoutException $exception) {
            throw $exception;
        } catch (TranscriptProviderException $exception) {
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $extraction = Extraction::query()->find($this->extractionId);

        if ($extraction === null || in_array($extraction->status, [ExtractionStatus::Ready, ExtractionStatus::Failed], true)) {
            return;
        }

        if ($extraction->status === ExtractionStatus::Pending) {
            $extraction->markProcessing();
        }

        $code = $exception instanceof TranscriptProviderTimeoutException
            ? ExtractionErrorCode::ProviderTimeout
            : ExtractionErrorCode::ProviderError;
        $message = $code === ExtractionErrorCode::ProviderTimeout
            ? 'O provedor excedeu o tempo limite da extração.'
            : 'Não foi possível concluir a extração da transcrição.';

        $extraction->markFailed($code, $message);

        Log::warning('Transcript extraction exhausted its retries.', [
            ...$this->logContext($extraction),
            'error_code' => $code->value,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }

    private function markTerminalFailure(
        Extraction $extraction,
        ExtractionErrorCode $code,
        string $message,
        Throwable $exception,
    ): void {
        $extraction->refresh();

        if ($extraction->status === ExtractionStatus::Processing) {
            $extraction->markFailed($code, $message);
        }

        Log::notice('Transcript extraction failed terminally.', [
            ...$this->logContext($extraction),
            'error_code' => $code->value,
            'exception' => $exception::class,
        ]);
    }

    /** @return array<string, int|float|string|null> */
    private function logContext(Extraction $extraction, ?float $durationSeconds = null): array
    {
        return [
            'extraction_id' => $extraction->getKey(),
            'public_id' => $extraction->public_id,
            'provider' => $extraction->video->provider->value,
            'provider_video_id' => $extraction->video->provider_video_id,
            'status' => $extraction->status->value,
            'duration_seconds' => $durationSeconds === null ? null : round($durationSeconds, 3),
        ];
    }
}
