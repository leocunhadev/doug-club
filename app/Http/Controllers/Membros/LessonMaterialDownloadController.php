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
        abort_unless($material->hasUploadedFile(), 404);

        $extension = pathinfo($material->file_path, PATHINFO_EXTENSION);

        return Storage::disk('public')->download(
            $material->file_path,
            "{$material->title}.{$extension}",
        );
    }
}
