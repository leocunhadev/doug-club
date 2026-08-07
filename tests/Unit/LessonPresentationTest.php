<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function makeLesson(array $overrides = []): Lesson
    {
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);

        return Lesson::create(array_merge([
            'course_id' => $course->id,
            'number' => 1,
            'title' => 'Aula 1',
            'video_provider' => 'youtube',
            'video_id' => 'abc123',
            'published_at' => '2026-01-01',
            'position' => 1,
        ], $overrides));
    }

    public function test_embed_url_for_vimeo(): void
    {
        $lesson = $this->makeLesson(['video_provider' => 'vimeo', 'video_id' => '76979871']);

        $this->assertSame('https://player.vimeo.com/video/76979871', $lesson->embed_url);
    }

    public function test_embed_url_for_youtube(): void
    {
        $lesson = $this->makeLesson(['video_provider' => 'youtube', 'video_id' => 'dQw4w9WgXcQ']);

        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $lesson->embed_url);
    }

    public function test_duration_formatted_under_an_hour(): void
    {
        $lesson = $this->makeLesson(['duration_seconds' => 2923]);

        $this->assertSame('48:43', $lesson->duration_formatted);
    }

    public function test_duration_formatted_over_an_hour(): void
    {
        $lesson = $this->makeLesson(['duration_seconds' => 4020]);

        $this->assertSame('1h 07min', $lesson->duration_formatted);
    }

    public function test_duration_formatted_pads_single_digit_seconds(): void
    {
        $lesson = $this->makeLesson(['duration_seconds' => 65]);

        $this->assertSame('1:05', $lesson->duration_formatted);
    }

    public function test_duration_formatted_drops_leftover_seconds_past_the_hour(): void
    {
        $lesson = $this->makeLesson(['duration_seconds' => 3661]);

        $this->assertSame('1h 01min', $lesson->duration_formatted);
    }

    public function test_duration_formatted_is_null_when_duration_is_null(): void
    {
        $lesson = $this->makeLesson(['duration_seconds' => null]);

        $this->assertNull($lesson->duration_formatted);
    }

    public function test_thumbnail_url_falls_back_to_youtube_frame(): void
    {
        $lesson = $this->makeLesson(['video_provider' => 'youtube', 'video_id' => 'dQw4w9WgXcQ', 'thumbnail_path' => null]);

        $this->assertSame('https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $lesson->thumbnail_url);
    }

    public function test_thumbnail_url_falls_back_to_null_for_vimeo_without_thumbnail_path(): void
    {
        $lesson = $this->makeLesson(['video_provider' => 'vimeo', 'video_id' => '76979871', 'thumbnail_path' => null]);

        $this->assertNull($lesson->thumbnail_url);
    }

    public function test_thumbnail_url_prefers_explicit_thumbnail_path(): void
    {
        $lesson = $this->makeLesson(['thumbnail_path' => 'https://cdn.example.com/thumb.jpg']);

        $this->assertSame('https://cdn.example.com/thumb.jpg', $lesson->thumbnail_url);
    }
}
