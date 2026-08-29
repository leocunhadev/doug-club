<?php

namespace App\Http\Controllers\Membros;

use App\Http\Controllers\Controller;
use App\Models\Framework;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FrameworkPdfDownloadController extends Controller
{
    public function __invoke(Framework $framework): StreamedResponse
    {
        abort_unless(
            $framework->hasUploadedFile() && Storage::disk('public')->exists($framework->pdf_path),
            404,
        );

        $extension = pathinfo($framework->pdf_path, PATHINFO_EXTENSION);
        $filename = str_replace(['/', '\\'], '-', $framework->title);

        return Storage::disk('public')->download(
            $framework->pdf_path,
            "{$filename}.{$extension}",
        );
    }
}
