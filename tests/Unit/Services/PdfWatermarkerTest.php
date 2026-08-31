<?php

namespace Tests\Unit\Services;

use App\Services\PdfWatermarker;
use Tests\TestCase;

class PdfWatermarkerTest extends TestCase
{
    private function samplePdfBytes(): string
    {
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'Documento de teste');

        return $pdf->Output('S');
    }

    public function test_stamp_returns_a_different_valid_pdf(): void
    {
        $original = $this->samplePdfBytes();

        $stamped = (new PdfWatermarker)->stamp(
            $original,
            'Ricardo Mendes · ricardo@empresa.com · baixado em 31/08/2026'
        );

        $this->assertStringStartsWith('%PDF', $stamped);
        $this->assertNotSame($original, $stamped);
    }

    public function test_stamp_handles_accented_characters_without_throwing(): void
    {
        $original = $this->samplePdfBytes();

        $stamped = (new PdfWatermarker)->stamp(
            $original,
            'José da Conceição · jose@exemplo.com.br · baixado em 31/08/2026'
        );

        $this->assertStringStartsWith('%PDF', $stamped);
    }

    public function test_stamp_throws_for_unparseable_input(): void
    {
        $this->expectException(\Throwable::class);

        (new PdfWatermarker)->stamp('not a real pdf', 'stamp text');
    }
}
