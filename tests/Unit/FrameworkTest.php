<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Framework;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrameworkTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_uploaded_file_is_true_when_pdf_path_is_set(): void
    {
        $framework = Framework::create([
            'code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste',
            'pdf_path' => 'framework-pdfs/4s.pdf', 'position' => 10,
        ]);

        $this->assertTrue($framework->hasUploadedFile());
    }

    public function test_has_uploaded_file_is_false_when_pdf_path_is_null(): void
    {
        $framework = Framework::create([
            'code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste',
            'pdf_url' => 'https://example.com/4s.pdf', 'position' => 10,
        ]);

        $this->assertFalse($framework->hasUploadedFile());
    }

    public function test_lesson_relationship_resolves(): void
    {
        $course = Course::create(['label' => 'Curso', 'title' => 'Teste', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula vinculada',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
        $framework = Framework::create([
            'code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste',
            'lesson_id' => $lesson->id, 'position' => 10,
        ]);

        $this->assertTrue($framework->lesson->is($lesson));
    }

    public function test_lesson_id_is_nulled_when_the_linked_lesson_is_deleted(): void
    {
        $course = Course::create(['label' => 'Curso', 'title' => 'Teste', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula a apagar',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
        $framework = Framework::create([
            'code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste',
            'lesson_id' => $lesson->id, 'position' => 10,
        ]);

        $lesson->delete();

        $this->assertNull($framework->fresh()->lesson_id);
    }
}
