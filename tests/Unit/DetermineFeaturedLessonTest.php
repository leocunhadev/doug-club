<?php

namespace Tests\Unit;

use App\Actions\DetermineFeaturedLesson;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetermineFeaturedLessonTest extends TestCase
{
    use RefreshDatabase;

    private function course(int $position = 10): Course
    {
        return Course::create(['label' => 'Curso', 'title' => 'Teste', 'position' => $position]);
    }

    public function test_defaults_to_first_lesson_of_highest_position_course_for_a_club_user(): void
    {
        $olderCourse = $this->course(10);
        $newerCourse = $this->course(50);

        Lesson::create([
            'course_id' => $olderCourse->id, 'number' => 1, 'title' => 'Aula antiga',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
        $newestLesson = Lesson::create([
            'course_id' => $newerCourse->id, 'number' => 1, 'title' => 'Aula nova',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $user = User::factory()->create(['tier' => 'club']);

        $this->assertSame($newestLesson->id, (new DetermineFeaturedLesson)->handle($user));
    }

    public function test_fallback_skips_a_club_only_lesson_for_a_start_tier_user_even_if_it_has_the_highest_position(): void
    {
        $course = $this->course();

        $startLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula start',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'tier' => 'start',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula club mais nova',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
            'tier' => 'club',
        ]);

        $user = User::factory()->create(['tier' => 'start']);

        $this->assertSame($startLesson->id, (new DetermineFeaturedLesson)->handle($user));
    }

    public function test_fallback_can_return_a_club_only_lesson_for_a_club_user(): void
    {
        $course = $this->course();

        $clubLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula club',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'tier' => 'club',
        ]);

        $user = User::factory()->create(['tier' => 'club']);

        $this->assertSame($clubLesson->id, (new DetermineFeaturedLesson)->handle($user));
    }

    public function test_most_recently_watched_lesson_wins_when_it_is_still_available_to_the_viewer(): void
    {
        $course = $this->course();

        $olderLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'tier' => 'start',
        ]);
        $recentLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula 2',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
            'tier' => 'start',
        ]);

        $user = User::factory()->create(['tier' => 'start']);

        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $olderLesson->id,
            'status' => 'watching', 'last_watched_at' => now()->subDay(),
        ]);
        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $recentLesson->id,
            'status' => 'watching', 'last_watched_at' => now(),
        ]);

        $this->assertSame($recentLesson->id, (new DetermineFeaturedLesson)->handle($user));
    }

    public function test_skips_a_recently_watched_lesson_that_is_no_longer_available_to_a_downgraded_viewer(): void
    {
        $course = $this->course();

        $stillAvailableLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula start mais antiga',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'tier' => 'start',
        ]);
        $noLongerAvailableLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula club assistida antes do downgrade',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
            'tier' => 'club',
        ]);

        $user = User::factory()->create(['tier' => 'start']);

        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $stillAvailableLesson->id,
            'status' => 'watching', 'last_watched_at' => now()->subDay(),
        ]);
        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $noLongerAvailableLesson->id,
            'status' => 'watching', 'last_watched_at' => now(),
        ]);

        $this->assertSame($stillAvailableLesson->id, (new DetermineFeaturedLesson)->handle($user));
    }

    public function test_returns_null_when_the_viewer_has_no_watchable_lessons_at_all(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula club',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'tier' => 'club',
        ]);

        $user = User::factory()->create(['tier' => 'start']);

        $this->assertNull((new DetermineFeaturedLesson)->handle($user));
    }
}
