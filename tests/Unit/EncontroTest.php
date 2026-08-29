<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Encontro;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncontroTest extends TestCase
{
    use RefreshDatabase;

    private function lesson(): Lesson
    {
        $course = Course::create(['label' => 'Curso', 'title' => 'Teste', 'position' => 10]);

        return Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Gravação do encontro',
            'video_provider' => 'vimeo', 'video_id' => '123', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
    }

    public function test_is_past_is_true_for_a_past_encontro(): void
    {
        $encontro = Encontro::create([
            'tema' => 'O comercial é gente', 'quem' => 'Com Douglas',
            'scheduled_at' => now()->subDay(),
        ]);

        $this->assertTrue($encontro->isPast());
    }

    public function test_is_past_is_false_for_a_future_encontro(): void
    {
        $encontro = Encontro::create([
            'tema' => 'Precificação sem medo', 'quem' => 'Convidada: Marina Prado',
            'scheduled_at' => now()->addDay(),
        ]);

        $this->assertFalse($encontro->isPast());
    }

    public function test_lesson_relationship_resolves_through_the_custom_fk(): void
    {
        $lesson = $this->lesson();
        $encontro = Encontro::create([
            'tema' => 'O comercial é gente', 'quem' => 'Com Douglas',
            'scheduled_at' => now()->subDay(), 'recording_lesson_id' => $lesson->id,
        ]);

        $this->assertTrue($encontro->lesson->is($lesson));
    }

    public function test_recording_lesson_id_is_nulled_when_the_linked_lesson_is_deleted(): void
    {
        $lesson = $this->lesson();
        $encontro = Encontro::create([
            'tema' => 'O comercial é gente', 'quem' => 'Com Douglas',
            'scheduled_at' => now()->subDay(), 'recording_lesson_id' => $lesson->id,
        ]);

        $lesson->delete();

        $this->assertNull($encontro->fresh()->recording_lesson_id);
    }

    public function test_scheduled_month_label_returns_the_pt_br_abbreviation(): void
    {
        $encontro = Encontro::create([
            'tema' => 'Decisão orientada por dados', 'quem' => 'Com Douglas',
            'scheduled_at' => '2026-07-29 19:00:00',
        ]);

        $this->assertSame('jul', $encontro->scheduled_month_label);
    }
}
