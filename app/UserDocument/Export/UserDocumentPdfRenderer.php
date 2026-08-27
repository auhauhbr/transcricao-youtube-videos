<?php

namespace App\UserDocument\Export;

use Dompdf\Dompdf;
use Dompdf\Options;

final class UserDocumentPdfRenderer
{
    public function render(UserDocumentExportData $document, UserDocumentExportRenderer $htmlRenderer): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($htmlRenderer->html($document), 'UTF-8');
        $dompdf->setPaper('a4');
        $dompdf->render();
        $dompdf->addInfo('Title', $document->title);

        return $dompdf->output();
    }
}
