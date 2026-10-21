<?php

declare(strict_types=1);

namespace Lubie\Reports;

/**
 * PDF via Dompdf (pinned 3.1.4, remote resources disabled). If Dompdf is not installed,
 * the caller falls back to a printable HTML page (print stylesheet).
 */
final class PdfRenderer
{
    public static function available(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    public function render(string $html): string
    {
        $opts = new \Dompdf\Options();
        $opts->set('isRemoteEnabled', false);
        $opts->set('isPhpEnabled', false);
        $opts->set('isJavascriptEnabled', false);
        $opts->set('defaultFont', 'DejaVu Sans');
        $pdf = new \Dompdf\Dompdf($opts);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $canvas->page_text(500, 815, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8, [0.3, 0.3, 0.3]);
        return (string) $pdf->output();
    }
}
