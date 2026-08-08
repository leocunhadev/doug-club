<?php

namespace App\Http\Controllers\Membros;

use App\Http\Controllers\Controller;
use App\Models\LessonMaterial;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonMaterialDownloadController extends Controller
{
    public function __invoke(LessonMaterial $material): StreamedResponse
    {
        abort_unless(
            $material->hasUploadedFile() && Storage::disk('public')->exists($material->file_path),
            404,
        );

        $extension = pathinfo($material->file_path, PATHINFO_EXTENSION);
        $filename = str_replace(['/', '\\'], '-', $material->title);

        return Storage::disk('public')->download(
            $material->file_path,
            "{$filename}.{$extension}",
        );
    }
}
