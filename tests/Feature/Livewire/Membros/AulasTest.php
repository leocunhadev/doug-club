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
        $this->get('/aulas')->assertRedirect('/login');
    }

    public function test_regular_user_sees_the_lock_when_the_catalog_has_no_lessons_at_all(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Aulas::class)
            ->assertSee('Sua biblioteca de aulas está sendo preparada.');
    }

    public function test_mentor_sees_the_real_page_even_when_the_catalog_has_no_lessons_at_all(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        Livewire::test(Aulas::class)
            ->assertSee('Nenhuma aula nesta categoria ainda.')
            ->assertDontSee('Sua biblioteca de aulas está sendo preparada.');
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
            ->assertSee('1 aula na sua biblioteca');
    }

    public function test_category_filter_with_no_matching_lessons_shows_an_empty_state_message(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula encontro',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Aulas::class)
            ->call('selectCategory', 'Frameworks')
            ->assertSee('Nenhuma aula nesta categoria ainda.');
    }

    public function test_aula_card_shows_the_formatted_duration_when_present(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula com duração',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start', 'duration_seconds' => 3900,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(Aulas::class)->assertSee('1h 05min');
    }

    public function test_aula_card_omits_the_duration_chip_when_duration_is_unknown(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula sem duração',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start', 'duration_seconds' => null,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(Aulas::class)->assertDontSee('aula-card-duration', false);
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

    public function test_lesson_query_param_sets_the_featured_lesson_when_valid_and_available(): void
    {
        $course = $this->course();
        $defaultLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Higher position (default pick)',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 2,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        $deepLinkLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula via deep link',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $this->get('/aulas?lesson='.$deepLinkLesson->id)
            ->assertOk()
            ->assertSee("wire:key=\"hero-player-{$deepLinkLesson->id}\"", false);
    }

    public function test_lesson_query_param_is_ignored_when_it_points_to_an_unavailable_lesson(): void
    {
        $course = $this->course();
        $startLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Start tier lesson',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        $clubLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula club',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
            'category' => 'Encontros', 'tier' => 'club',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $this->get('/aulas?lesson='.$clubLesson->id)
            ->assertOk()
            ->assertSee("wire:key=\"hero-player-{$startLesson->id}\"", false)
            ->assertDontSee("wire:key=\"hero-player-{$clubLesson->id}\"", false);
    }

    public function test_lesson_query_param_is_ignored_when_the_lesson_does_not_exist(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/aulas?lesson=999999')
            ->assertOk();
    }

    public function test_search_filters_lessons_by_title(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Consumidor 4S na prática',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-02', 'position' => 2,
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Precificação sem medo',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(Aulas::class)
            ->set('search', 'consumidor')
            ->assertSee('Consumidor 4S na prática')
            ->assertDontSee('Precificação sem medo');
    }

    public function test_search_is_case_insensitive(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Consumidor 4S na prática',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(Aulas::class)
            ->set('search', 'CONSUMIDOR')
            ->assertSee('Consumidor 4S na prática');
    }

    public function test_search_matches_the_course_label(): void
    {
        $matchingCourse = Course::create(['label' => 'Vendas B2B', 'title' => '', 'position' => 20]);
        $otherCourse = $this->course();

        Lesson::create([
            'course_id' => $matchingCourse->id, 'number' => 1, 'title' => 'Aula qualquer',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
        Lesson::create([
            'course_id' => $otherCourse->id, 'number' => 1, 'title' => 'Outra aula',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(Aulas::class)
            ->set('search', 'b2b')
            ->assertSee('Aula qualquer')
            ->assertDontSee('Outra aula');
    }

    public function test_search_combines_with_the_category_filter(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Consumidor 4S na prática',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-02', 'position' => 2,
            'category' => 'Frameworks',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Consumidor 4S: encontro ao vivo',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros',
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(Aulas::class)
            ->set('search', 'consumidor')
            ->call('selectCategory', 'Frameworks')
            ->assertSee('Consumidor 4S na prática')
            ->assertDontSee('Consumidor 4S: encontro ao vivo');
    }

    public function test_search_never_leaks_lessons_outside_the_users_tier(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Consumidor 4S CLUB',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'tier' => 'club',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'start']));

        Livewire::test(Aulas::class)
            ->set('search', 'consumidor')
            ->assertDontSee('Consumidor 4S CLUB');
    }

    public function test_empty_state_shows_the_search_term_when_nothing_matches(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula qualquer',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(Aulas::class)
            ->set('search', 'termo-que-nao-existe')
            ->assertSee('Nenhuma aula encontrada para "termo-que-nao-existe".', false);
    }

    public function test_materiais_de_aula_link_points_to_the_dedicated_materials_page(): void
    {
        $course = $this->course();
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula com materiais',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs(User::factory()->create());

        $this->get('/aulas')
            ->assertOk()
            ->assertSee(route('membros.aulas.materiais', $lesson), false);
    }
}
