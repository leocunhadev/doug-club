<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

class PdfWatermarker
{
    public function stamp(string $pdfContents, string $stampText): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tmpFile, $pdfContents);

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($tmpFile);

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

            return $pdf->Output('S');
        } finally {
            @unlink($tmpFile);
        }
    }

    private function toLatin1(string $text): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text) ?: $text;
    }
}
