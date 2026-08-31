<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

class PdfWatermarker
{
    public function stamp(string $pdfContents, string $stampText): string
    {
        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $pdfContents);
        rewind($stream);

        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($stream);

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $templateId = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(140, 140, 140);
            $pdf->SetXY(10, $size['height'] - 12);
            $pdf->Cell($size['width'] - 20, 5, $this->toLatin1($stampText), 0, 0, 'C');
        }

        $output = $pdf->Output('S');
        fclose($stream);

        return $output;
    }

    private function toLatin1(string $text): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text) ?: $text;
    }
}
