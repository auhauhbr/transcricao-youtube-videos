<?php

namespace App\Http\Controllers;

use App\Actions\FindUserTranscript;
use App\Http\Requests\DownloadUserDocumentRequest;
use App\UserDocument\Export\UserDocumentDocxRenderer;
use App\UserDocument\Export\UserDocumentExportRenderer;
use App\UserDocument\Export\UserDocumentPdfRenderer;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserDocumentDownloadController extends Controller
{
    public function __invoke(DownloadUserDocumentRequest $request, string $userTranscript, FindUserTranscript $find, UserDocumentExportRenderer $renderer, UserDocumentPdfRenderer $pdf, UserDocumentDocxRenderer $docx): StreamedResponse
    {
        $item = $find->handle($request->user(), $userTranscript);
        $document = $item->document()->firstOrFail();
        $format = (string) $request->validated('format');
        $extension = $format === 'markdown' ? 'md' : $format;
        $filename = trim(Str::slug((string) $document->title), '-');
        $filename = mb_substr($filename !== '' ? $filename : 'documento', 0, 100).'.'.$extension;
        $contentType = ['txt' => 'text/plain', 'markdown' => 'text/markdown', 'html' => 'text/html', 'pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'][$format];
        $content = match ($format) {
            'txt' => $renderer->text($document),
            'markdown' => $renderer->markdown($document),
            'html' => $renderer->html($document),
            'pdf' => $pdf->render($document, $renderer),
            'docx' => $docx->render($document),
            default => throw new \LogicException('Formato de exportação inválido.'),
        };

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, ['Content-Type' => $contentType.'; charset=UTF-8']);
    }
}
