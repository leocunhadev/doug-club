<?php

namespace Tests\Unit;

use App\Actions\SubmitLessonNpsScore;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmitLessonNpsScoreTest extends TestCase
{
    use RefreshDatabase;

    private function lesson(): Lesson
    {
        $course = Course::create(['label' => 'Curso', 'title' => 'Teste', 'position' => 10]);

        return Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula',
            'video_provider' => 'vimeo', 'video_id' => '123', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
    }

    public function test_creates_feedback_with_the_given_score(): void
    {
        $user = User::factory()->create();
        $lesson = $this->lesson();

        (new SubmitLessonNpsScore)->handle($user->id, $lesson->id, 9);

        $this->assertDatabaseHas('lesson_feedback', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'score' => 9,
        ]);
    }

    public function test_resubmitting_updates_the_existing_score_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $lesson = $this->lesson();

        (new SubmitLessonNpsScore)->handle($user->id, $lesson->id, 5);
        (new SubmitLessonNpsScore)->handle($user->id, $lesson->id, 8);

        $this->assertSame(1, LessonFeedback::query()->where('user_id', $user->id)->where('lesson_id', $lesson->id)->count());
        $this->assertDatabaseHas('lesson_feedback', ['user_id' => $user->id, 'lesson_id' => $lesson->id, 'score' => 8]);
    }

    public function test_score_above_10_is_clamped_to_10(): void
    {
        $user = User::factory()->create();
        $lesson = $this->lesson();

        (new SubmitLessonNpsScore)->handle($user->id, $lesson->id, 99);

        $this->assertDatabaseHas('lesson_feedback', ['user_id' => $user->id, 'lesson_id' => $lesson->id, 'score' => 10]);
    }

    public function test_score_below_0_is_clamped_to_0(): void
    {
        $user = User::factory()->create();
        $lesson = $this->lesson();

        (new SubmitLessonNpsScore)->handle($user->id, $lesson->id, -3);

        $this->assertDatabaseHas('lesson_feedback', ['user_id' => $user->id, 'lesson_id' => $lesson->id, 'score' => 0]);
    }
}
