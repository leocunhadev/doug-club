<?php

namespace App\Http\Controllers\Membros;

use App\Http\Controllers\Controller;
use App\Models\VaultDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VaultDocumentOpenController extends Controller
{
    public function __invoke(VaultDocument $document): StreamedResponse|RedirectResponse
    {
        abort_unless($document->member_id === request()->user()->id, 404);

        if ($document->hasUploadedFile()) {
            abort_unless(Storage::disk('public')->exists($document->file_path), 404);

            $this->markOpened($document);

            $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
            $filename = str_replace(['/', '\\'], '-', $document->title);

            return Storage::disk('public')->download(
                $document->file_path,
                "{$filename}.{$extension}",
            );
        }

        abort_unless(filled($document->file_url), 404);

        $this->markOpened($document);

        return redirect($document->file_url);
    }

    private function markOpened(VaultDocument $document): void
    {
        if ($document->isNew()) {
            $document->update(['opened_at' => now()]);
        }
    }
}
