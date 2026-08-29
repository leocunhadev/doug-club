<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\Aulas;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AulasTest extends TestCase
{
    use RefreshDatabase;

    private function course(): Course
    {
        return Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/aulas')->assertRedirect('/login');
    }

    public function test_start_tier_only_sees_start_tier_lessons(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula start',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula club',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
            'category' => 'Encontros', 'tier' => 'club',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'start']));

        Livewire::test(Aulas::class)
            ->assertSee('Aula start')
            ->assertDontSee('Aula club');
    }

    public function test_club_tier_sees_every_lesson(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula start',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula club',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
            'category' => 'Encontros', 'tier' => 'club',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Aulas::class)
            ->assertSee('Aula start')
            ->assertSee('Aula club');
    }

    public function test_exclusivo_club_suffix_shown_only_on_club_tier_cards(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula start',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula club',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
            'category' => 'Encontros', 'tier' => 'club',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $html = Livewire::test(Aulas::class)->html();

        $this->assertSame(1, substr_count($html, 'Exclusivo CLUB'));
    }

    public function test_category_filter_narrows_the_grid(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula encontro',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula framework',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
            'category' => 'Frameworks', 'tier' => 'start',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Aulas::class)
            ->call('selectCategory', 'Frameworks')
            ->assertSee('Aula framework')
            ->assertDontSee('Aula encontro');
    }

    public function test_total_count_ignores_the_category_filter_but_respects_tier(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula encontro start',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula framework club',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
            'category' => 'Frameworks', 'tier' => 'club',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'start']));

        Livewire::test(Aulas::class)
            ->call('selectCategory', 'Encontros')
            ->assertSet('category', 'Encontros')
            ->assertSee('1 aulas na sua biblioteca');
    }

    public function test_watching_a_lesson_from_the_grid_updates_the_hero_player(): void
    {
        $course = $this->course();
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(Aulas::class)
            ->call('watchLesson', $lesson->id)
            ->assertSet('featuredLessonId', $lesson->id)
            ->assertSee("wire:key=\"hero-player-{$lesson->id}\"", false);
    }

    public function test_watching_badge_appears_on_exactly_the_featured_lesson_card(): void
    {
        $course = $this->course();
        $watchedLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 2,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula 2',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);

        $user = User::factory()->create();
        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $watchedLesson->id,
            'status' => 'watching', 'last_watched_at' => now(),
        ]);

        $this->actingAs($user);

        $html = Livewire::test(Aulas::class)->html();

        $this->assertSame(1, substr_count($html, 'Assistindo'));

        preg_match_all(
            '/<button[^>]*wire:click="watchLesson\((\d+)\)"[^>]*>(.*?)<\/button>/s',
            $html,
            $cards,
            PREG_SET_ORDER,
        );

        $cardsWithBadge = array_values(array_filter($cards, fn (array $card) => str_contains($card[2], 'Assistindo')));

        $this->assertCount(1, $cardsWithBadge, 'Expected exactly one card to contain the "Assistindo" badge.');
        $this->assertSame((string) $watchedLesson->id, $cardsWithBadge[0][1]);
    }
}
