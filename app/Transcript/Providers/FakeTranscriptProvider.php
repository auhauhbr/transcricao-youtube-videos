<?php

namespace App\Transcript\Providers;

use App\Enums\VideoProvider;
use App\Transcript\Contracts\TranscriptProvider;
use App\Transcript\Data\ChapterData;
use App\Transcript\Data\TranscriptData;
use App\Transcript\Data\TranscriptSegmentData;
use App\Transcript\Data\VideoMetadataData;

final class FakeTranscriptProvider implements TranscriptProvider
{
    public function fetch(string $providerVideoId): TranscriptData
    {
        return new TranscriptData(
            video: new VideoMetadataData(
                provider: VideoProvider::YouTube,
                providerVideoId: $providerVideoId,
                title: 'Como transformar conteúdo em conhecimento',
                channelName: 'Canal demonstrativo',
                durationSeconds: 270,
                thumbnailUrl: null,
            ),
            languageCode: 'pt-BR',
            languageName: 'Português',
            segments: [
                new TranscriptSegmentData(0, 45_000, 'Neste exemplo, começamos identificando as ideias centrais apresentadas no vídeo.'),
                new TranscriptSegmentData(45_000, 75_000, 'A divisão em trechos mantém o texto conectado ao momento correspondente.'),
                new TranscriptSegmentData(75_000, 140_000, 'Durante o desenvolvimento, cada segmento permanece ordenado e pronto para leitura.'),
                new TranscriptSegmentData(140_000, 210_000, 'Os capítulos ajudam a navegar pelo conteúdo sem transformar a transcrição em um bloco único.'),
                new TranscriptSegmentData(210_000, 240_000, 'Na etapa final, as informações principais podem ser revisadas com mais clareza.'),
                new TranscriptSegmentData(240_000, 270_000, 'Assim, vídeo, capítulos e texto permanecem relacionados em uma estrutura consistente.'),
            ],
            chapters: [
                new ChapterData('Introdução', 0, 75_000),
                new ChapterData('Desenvolvimento', 75_000, 210_000),
                new ChapterData('Encerramento', 210_000, 270_000),
            ],
        );
    }
}
