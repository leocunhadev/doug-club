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
}
