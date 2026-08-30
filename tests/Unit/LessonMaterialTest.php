<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonMaterialTest extends TestCase
{
    use RefreshDatabase;

    private function lesson(): Lesson
    {
        $course = Course::create([
            'label' => 'Módulo 1', 'title' => 'Fundamentos', 'description' => null, 'position' => 10,
        ]);

        return Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula de teste',
            'video_provider' => 'youtube', 'video_id' => 'abc123', 'published_at' => '2026-01-01', 'position' => 10,
        ]);
    }

    public function test_has_uploaded_file_is_true_when_file_path_is_set(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Apostila',
            'file_path' => 'lesson-materials/abc.pdf',
        ]);

        $this->assertTrue($material->hasUploadedFile());
    }

    public function test_has_uploaded_file_is_false_when_file_path_is_null(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Slides',
            'file_url' => 'https://example.com/slides.pdf',
        ]);

        $this->assertFalse($material->hasUploadedFile());
    }

    public function test_icon_label_is_pdf_for_a_pdf_upload(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Apostila',
            'file_path' => 'lesson-materials/insights.pdf',
        ]);

        $this->assertSame('PDF', $material->icon_label);
    }

    public function test_icon_label_is_video_for_a_video_upload(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Gravação',
            'file_path' => 'lesson-materials/gravacao.mp4',
        ]);

        $this->assertSame('VÍDEO', $material->icon_label);
    }

    public function test_icon_label_is_xlsx_for_a_spreadsheet_upload(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Planilha',
            'file_path' => 'lesson-materials/tabela.xlsx',
        ]);

        $this->assertSame('XLSX', $material->icon_label);
    }

    public function test_icon_label_is_doc_for_a_word_upload(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Plano',
            'file_path' => 'lesson-materials/plano.docx',
        ]);

        $this->assertSame('DOC', $material->icon_label);
    }

    public function test_icon_label_is_link_for_an_external_url_with_no_recognizable_extension(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Vídeo externo',
            'file_url' => 'https://vimeo.com/76979871',
        ]);

        $this->assertSame('LINK', $material->icon_label);
    }

    public function test_icon_label_is_arquivo_for_an_unrecognized_extension(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Arquivo genérico',
            'file_path' => 'lesson-materials/arquivo.zip',
        ]);

        $this->assertSame('ARQUIVO', $material->icon_label);
    }
}
