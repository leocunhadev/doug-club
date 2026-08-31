<?php

namespace App\Http\Controllers\Membros;

use App\Http\Controllers\Controller;
use App\Models\VaultDocument;
use App\Services\PdfWatermarker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToRetrieveMetadata;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class VaultDocumentOpenController extends Controller
{
    public function __invoke(VaultDocument $document, PdfWatermarker $watermarker): StreamedResponse|RedirectResponse
    {
        abort_unless($document->member_id === request()->user()->id, 404);

        if ($document->hasUploadedFile()) {
            abort_unless(Storage::disk('local')->exists($document->file_path), 404);

            $this->markOpened($document);

            $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
            $filename = str_replace(['/', '\\'], '-', $document->title).".{$extension}";

            if (strtolower($extension) === 'pdf') {
                return $this->downloadWatermarkedPdf($document, $watermarker, $filename);
            }

            return Storage::disk('local')->download($document->file_path, $filename);
        }

        abort_unless(filled($document->file_url), 404);

        $this->markOpened($document);

        return redirect($document->file_url);
    }

    private function downloadWatermarkedPdf(VaultDocument $document, PdfWatermarker $watermarker, string $filename): StreamedResponse
    {
        try {
            $size = Storage::disk('local')->size($document->file_path);
        } catch (UnableToRetrieveMetadata $e) {
            abort(404);
        }

        if ($size > 20 * 1024 * 1024) {
            return Storage::disk('local')->download($document->file_path, $filename);
        }

        $original = Storage::disk('local')->get($document->file_path);
        abort_if(is_null($original), 404);

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
