# Cofre PDF Watermark Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stamp every downloaded Cofre PDF with the member's name, email, and download date, and stop promising that watermark for document types that can't get one.

**Architecture:** A new stateless `App\Services\PdfWatermarker` uses FPDI to import each page of the source PDF as a template and FPDF to draw a footer stamp on top, producing new PDF bytes in memory (no disk caching). `VaultDocumentOpenController` calls it only when the uploaded file's extension is `pdf`, streaming the result the same way `Storage::download()` already streams files today. Any other document type (non-PDF upload, or external `file_url`) is untouched.

**Tech Stack:** Laravel 13, PHP 8.3, `setasign/fpdi` 2.6.8 + `setasign/fpdf` 1.9.0 (both MIT).

**Spec:** `docs/superpowers/specs/2026-08-31-cofre-watermark-design.md`

## Global Constraints

- Dependencies must be MIT-licensed (or otherwise clearly non-AGPL/non-GPL) — this project never uses AGPL packages. `setasign/fpdi` and `setasign/fpdf` are both MIT.
- Watermarking applies only to PDFs served from `VaultDocument::$file_path` (uploaded files). Non-PDF uploads and any `file_url`-backed document are served exactly as before.
- The watermarked PDF is generated on every request, never persisted to disk or cached.
- Stamp text format: `"{name} · {email} · baixado em {d/m/Y}"`, drawn as a small gray line in the footer of every page — not a diagonal full-page watermark.
- If watermarking throws for any reason, the controller must still serve the original file rather than fail the request.

---

### Task 1: `PdfWatermarker` service

**Files:**
- Modify: `composer.json`, `composer.lock` (via `composer require`)
- Create: `app/Services/PdfWatermarker.php`
- Test: `tests/Unit/Services/PdfWatermarkerTest.php`

**Interfaces:**
- Produces: `App\Services\PdfWatermarker::stamp(string $pdfContents, string $stampText): string` — takes raw PDF bytes and the footer text, returns the stamped PDF's raw bytes. Throws (does not catch) if the source PDF can't be parsed.

- [ ] **Step 1: Install the PDF libraries**

Run: `composer require setasign/fpdi setasign/fpdf`

Expected: `composer.json` gains `"setasign/fpdi": "^2.6"` and `"setasign/fpdf": "^1.9"` under `require`; `composer.lock` updates; command exits 0.

- [ ] **Step 2: Write the failing unit test**

Create `tests/Unit/Services/PdfWatermarkerTest.php`:

```php
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
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test tests/Unit/Services/PdfWatermarkerTest.php`
Expected: FAIL — `Class "App\Services\PdfWatermarker" not found`.

- [ ] **Step 4: Implement `PdfWatermarker`**

Create `app/Services/PdfWatermarker.php`:

```php
<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

class PdfWatermarker
{
    public function stamp(string $pdfContents, string $stampText): string
    {
        $stream = fopen('php://temp', 'rb+');
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

        fclose($stream);

        return $pdf->Output('S');
    }

    private function toLatin1(string $text): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text) ?: $text;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Unit/Services/PdfWatermarkerTest.php`
Expected: PASS — 3 tests, 0 failures.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock app/Services/PdfWatermarker.php tests/Unit/Services/PdfWatermarkerTest.php
git commit -m "feat: add PdfWatermarker service for stamping vault PDFs"
```

---

### Task 2: Wire watermarking into `VaultDocumentOpenController`

**Files:**
- Modify: `app/Http/Controllers/Membros/VaultDocumentOpenController.php`
- Test: `tests/Feature/Membros/VaultDocumentOpenTest.php`

**Interfaces:**
- Consumes: `App\Services\PdfWatermarker::stamp(string, string): string` from Task 1 (resolved via Laravel's automatic dependency injection on the controller's `__invoke` method, the same pattern already used for `BookMentorSession` in `app/Livewire/Membros/Agenda.php`).

- [ ] **Step 1: Write the failing feature tests**

Add three new test methods to `tests/Feature/Membros/VaultDocumentOpenTest.php` (no new `use` statements needed — `\FPDF` is referenced fully-qualified, everything else the tests use is already imported). Leave the existing `test_owner_can_open_an_uploaded_file` test untouched; its fake, non-PDF-structured bytes will now exercise the watermarking failure fallback path once Step 3 lands:

```php
    public function test_owner_downloading_a_real_pdf_gets_a_watermarked_copy(): void
    {
        Storage::fake('public');

        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'Documento de teste');
        $originalBytes = $pdf->Output('S');

        Storage::disk('public')->put('vault-documents/contrato.pdf', $originalBytes);

        $member = User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes']);
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $document = VaultDocument::create([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'Contrato modelo', 'file_path' => 'vault-documents/contrato.pdf',
        ]);

        $this->actingAs($member);

        $response = $this->get(route('membros.cofre.open', $document))
            ->assertOk()
            ->assertDownload('Contrato modelo.pdf');

        $this->assertNotSame($originalBytes, $response->streamedContent());
        $this->assertNotNull($document->fresh()->opened_at);
    }

    public function test_owner_opening_a_non_pdf_uploaded_file_is_not_watermarked(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->create('planilha.xlsx', 10)->store('vault-documents', 'public');

        $member = User::factory()->create(['tier' => 'club']);
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $document = VaultDocument::create([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'Planilha de custos', 'file_path' => $path,
        ]);

        $this->actingAs($member);

        $this->get(route('membros.cofre.open', $document))
            ->assertOk()
            ->assertDownload('Planilha de custos.xlsx');

        $this->assertNotNull($document->fresh()->opened_at);
    }

    public function test_a_corrupted_pdf_falls_back_to_serving_the_original_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vault-documents/broken.pdf', 'this is not a real pdf');

        $member = User::factory()->create(['tier' => 'club']);
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $document = VaultDocument::create([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'Documento quebrado', 'file_path' => 'vault-documents/broken.pdf',
        ]);

        $this->actingAs($member);

        $response = $this->get(route('membros.cofre.open', $document))
            ->assertOk()
            ->assertDownload('Documento quebrado.pdf');

        $this->assertSame('this is not a real pdf', $response->streamedContent());
        $this->assertNotNull($document->fresh()->opened_at);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Membros/VaultDocumentOpenTest.php`
Expected: FAIL — `test_owner_downloading_a_real_pdf_gets_a_watermarked_copy` fails because `assertNotSame` sees identical bytes (no watermarking happens yet); the other two new tests should already pass since the controller doesn't distinguish PDFs from other files yet (confirms they're not accidentally relying on new behavior).

- [ ] **Step 3: Update the controller**

Replace the full contents of `app/Http/Controllers/Membros/VaultDocumentOpenController.php`:

```php
<?php

namespace App\Http\Controllers\Membros;

use App\Http\Controllers\Controller;
use App\Models\VaultDocument;
use App\Services\PdfWatermarker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class VaultDocumentOpenController extends Controller
{
    public function __invoke(VaultDocument $document, PdfWatermarker $watermarker): StreamedResponse|RedirectResponse
    {
        abort_unless($document->member_id === request()->user()->id, 404);

        if ($document->hasUploadedFile()) {
            abort_unless(Storage::disk('public')->exists($document->file_path), 404);

            $this->markOpened($document);

            $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
            $filename = str_replace(['/', '\\'], '-', $document->title).".{$extension}";

            if (strtolower($extension) === 'pdf') {
                return $this->downloadWatermarkedPdf($document, $watermarker, $filename);
            }

            return Storage::disk('public')->download($document->file_path, $filename);
        }

        abort_unless(filled($document->file_url), 404);

        $this->markOpened($document);

        return redirect($document->file_url);
    }

    private function downloadWatermarkedPdf(VaultDocument $document, PdfWatermarker $watermarker, string $filename): StreamedResponse
    {
        $original = Storage::disk('public')->get($document->file_path);
        $user = request()->user();
        $stampText = "{$user->name} · {$user->email} · baixado em ".now()->format('d/m/Y');

        try {
            $contents = $watermarker->stamp($original, $stampText);
        } catch (Throwable $e) {
            Log::warning("Falha ao aplicar marca d'água no PDF do cofre.", [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            $contents = $original;
        }

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    private function markOpened(VaultDocument $document): void
    {
        if ($document->isNew()) {
            $document->update(['opened_at' => now()]);
        }
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Membros/VaultDocumentOpenTest.php`
Expected: PASS — all 10 tests (7 existing + 3 new).

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: All tests pass (aside from any failures already present on `main` before this plan started, unrelated to this change).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Membros/VaultDocumentOpenController.php tests/Feature/Membros/VaultDocumentOpenTest.php
git commit -m "feat: watermark Cofre PDFs with the member's name and email on download"
```

---

### Task 3: Scope the Cofre banner copy to what's actually watermarked

**Files:**
- Modify: `resources/views/livewire/membros/cofre.blade.php`
- Test: `tests/Feature/Livewire/Membros/CofreTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Livewire/Membros/CofreTest.php`:

```php
    public function test_banner_scopes_the_watermark_promise_to_pdfs(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Cofre::class)
            ->assertSee('PDFs baixados aqui trazem seu nome e e-mail carimbados em cada página.')
            ->assertDontSee('Documentos com seu nome gravado em cada página.');
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Livewire/Membros/CofreTest.php`
Expected: FAIL — the new text isn't on the page yet.

- [ ] **Step 3: Update the banner copy**

In `resources/views/livewire/membros/cofre.blade.php`, replace:

```blade
            Documentos com seu nome gravado em cada página. Este espaço é individual e intransferível.
```

with:

```blade
            PDFs baixados aqui trazem seu nome e e-mail carimbados em cada página. Este espaço é individual e intransferível.
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Livewire/Membros/CofreTest.php`
Expected: PASS — all 5 tests (4 existing + 1 new).

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: All tests pass (aside from any failures already present on `main` before this plan started, unrelated to this change).

- [ ] **Step 6: Commit**

```bash
git add resources/views/livewire/membros/cofre.blade.php tests/Feature/Livewire/Membros/CofreTest.php
git commit -m "fix: scope the Cofre watermark promise to PDFs only (closes #44)"
```
