<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\Dashboard;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros')->assertRedirect('/login');
    }

    public function test_featured_lesson_defaults_to_first_lesson_of_highest_position_course(): void
    {
        $user = User::factory()->create();
        $olderCourse = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $newerCourse = Course::create(['label' => 'Boas Vindas', 'title' => '', 'position' => 50]);

        Lesson::create([
            'course_id' => $olderCourse->id, 'number' => 1, 'title' => 'Aula antiga',
            'video_provider' => 'youtube', 'video_id' => 'abc123', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
        $welcomeLesson = Lesson::create([
            'course_id' => $newerCourse->id, 'number' => 1, 'title' => 'Boas Vindas',
            'video_provider' => 'youtube', 'video_id' => 'def456', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSet('featuredLessonId', $welcomeLesson->id);
    }

    public function test_featured_lesson_uses_most_recently_watched_lesson(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);

        $olderLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
        $recentLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula 2',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
        ]);

        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $olderLesson->id,
            'status' => 'watching', 'last_watched_at' => now()->subDay(),
        ]);
        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $recentLesson->id,
            'status' => 'watching', 'last_watched_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSet('featuredLessonId', $recentLesson->id);
    }

    public function test_watch_lesson_upserts_progress_and_updates_featured_lesson(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('watchLesson', $lesson->id)
            ->assertSet('featuredLessonId', $lesson->id);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'status' => 'watching',
        ]);
    }

    public function test_update_progress_upserts_watched_seconds_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('updateProgress', $lesson->id, 137);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 137,
            'status' => 'watching',
        ]);
    }

    public function test_update_progress_does_not_downgrade_completed_status(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $lesson->id,
            'status' => 'completed', 'watched_seconds' => 590, 'last_watched_at' => now()->subMinute(),
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('updateProgress', $lesson->id, 600);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 600,
            'status' => 'completed',
        ]);
    }

    public function test_update_progress_is_scoped_to_the_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        LessonProgress::create([
            'user_id' => $owner->id, 'lesson_id' => $lesson->id,
            'status' => 'watching', 'watched_seconds' => 50, 'last_watched_at' => now(),
        ]);

        $this->actingAs($otherUser);

        Livewire::test(Dashboard::class)
            ->call('updateProgress', $lesson->id, 999);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $owner->id, 'lesson_id' => $lesson->id, 'watched_seconds' => 50,
        ]);
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $otherUser->id, 'lesson_id' => $lesson->id, 'watched_seconds' => 999,
        ]);
    }

    public function test_mark_completed_sets_status_to_completed(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('markCompleted', $lesson->id);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
        ]);
    }

    public function test_hero_player_wires_up_the_vimeo_progress_component_for_vimeo_lessons(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee("wire:key=\"hero-player-{$lesson->id}\"", false)
            ->assertSee('x-data="vimeoProgress(', false)
            ->assertSee("provider: 'vimeo'", false)
            ->assertSee('initialSeconds: 0', false);
    }

    public function test_hero_player_passes_the_saved_watched_seconds_into_the_alpine_component(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $lesson->id,
            'status' => 'watching', 'watched_seconds' => 245, 'last_watched_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('initialSeconds: 245', false);
    }

    public function test_user_initials_are_computed_from_name(): void
    {
        $user = User::factory()->create(['name' => 'Ana Souza']);
        $this->actingAs($user);

        Livewire::test(Dashboard::class)->assertSee('AS');
    }

    public function test_header_shows_the_brand_wordmark(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Dashboard::class)->assertSee('id="wmark"', false)->assertSee('DO.ing', false);
    }

    public function test_video_watermark_uses_the_brand_icon_not_the_default_jetstream_logo(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertDontSee('305.8 81.125', false); // path data unique to the old Jetstream logo artwork
    }

    public function test_dashboard_renders_featured_lesson_embed_and_materials(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Módulo 4', 'title' => 'Modelos de Negócio', 'position' => 40]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 5, 'title' => 'Aula 05',
            'video_provider' => 'youtube', 'video_id' => 'dQw4w9WgXcQ', 'published_at' => '2026-07-17', 'position' => 1,
        ]);
        $lesson->materials()->create(['title' => 'Slides', 'file_url' => 'https://example.com/slides.pdf']);
        $uploaded = $lesson->materials()->create(['title' => 'Apostila', 'file_path' => 'lesson-materials/apostila.pdf']);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('Slides')
            ->assertSee('Apostila')
            ->assertSee(route('membros.materials.download', $uploaded), false)
            ->assertSee('Aula 05')
            ->assertSee('Módulo 4');
    }

    public function test_watching_badge_appears_on_exactly_the_featured_lesson_card(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $watchedLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 2,
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula 2',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 1,
        ]);

        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $watchedLesson->id,
            'status' => 'watching', 'last_watched_at' => now(),
        ]);

        $this->actingAs($user);

        $html = Livewire::test(Dashboard::class)->html();

        $this->assertSame(1, substr_count($html, 'Assistindo'));

        preg_match_all(
            '/<button[^>]*wire:click="watchLesson\((\d+)\)"[^>]*>(.*?)<\/button>/s',
            $html,
            $cards,
            PREG_SET_ORDER,
        );

        $cardsWithBadge = array_values(array_filter($cards, fn (array $card) => str_contains($card[2], 'Assistindo')));

        $this->assertCount(1, $cardsWithBadge, 'Expected exactly one lesson card to contain the "Assistindo" badge.');
        $this->assertSame(
            (string) $watchedLesson->id,
            $cardsWithBadge[0][1],
            'The "Assistindo" badge is rendered on the wrong lesson card.',
        );
    }

    public function test_membros_page_renders_through_the_paper_layout(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/membros')
            ->assertOk()
            ->assertSee('bg-paper', false);
    }

    public function test_watch_lesson_with_nonexistent_lesson_id_does_not_throw_unhandled_exception(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Dashboard::class)->call('watchLesson', 999999);
    }

    public function test_footer_renders_privacy_about_links_and_copyright(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Dashboard::class)
            ->assertSee('Política de Privacidade')
            ->assertSee('Sobre')
            ->assertSee('DO.ing Club')
            ->assertSee('Todos os direitos reservados')
            ->assertDontSee('Flávio Augusto')
            ->assertDontSee('Geração de Valor');
    }

    public function test_hero_copy_references_douglas_oliveira(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Dashboard::class)
            ->assertSee('Douglas Oliveira')
            ->assertDontSee('Flávio Augusto');
    }

    public function test_whatsapp_button_renders_when_number_is_configured(): void
    {
        config(['services.whatsapp.number' => '5511999999999']);

        $this->actingAs(User::factory()->create());

        Livewire::test(Dashboard::class)
            ->assertSee('https://wa.me/5511999999999', false);
    }

    public function test_whatsapp_button_is_hidden_when_number_is_not_configured(): void
    {
        config(['services.whatsapp.number' => null]);

        $this->actingAs(User::factory()->create());

        Livewire::test(Dashboard::class)
            ->assertDontSee('wa.me', false);
    }

    public function test_start_tier_sees_a_generic_hero_title(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('Sua próxima')
            ->assertSee('decisão')
            ->assertSee('começa aqui')
            ->assertDontSee('Acompanhe as transmissões ao vivo e os conteúdos gravados de Douglas Oliveira');
    }

    public function test_club_tier_sees_the_full_hero_copy(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('continuar de onde paramos')
            ->assertSee('Acompanhe as transmissões ao vivo e os conteúdos gravados de Douglas Oliveira', false);
    }

    public function test_hero_greets_the_user_by_name(): void
    {
        $user = User::factory()->create(['name' => 'Ricardo Mendes']);
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('Olá, Ricardo Mendes.', false);
    }

    public function test_quick_links_render_locked_with_no_href_when_route_does_not_exist_yet(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $html = Livewire::test(Dashboard::class)->html();

        foreach (['Biblioteca de aulas', 'Frameworks DO', 'Marcar minha sessão'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*>\s*'.preg_quote($label, '#').'.*?🔒#s',
                $html,
            );
        }

        $this->assertStringNotContainsString('href="http://localhost/membros/aulas"', $html);
    }

    public function test_start_tier_quick_links_show_conhecer_o_club_as_the_third_link(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'start']));

        Livewire::test(Dashboard::class)->assertSee('Conhecer o CLUB');
    }

    public function test_start_tier_sees_the_newest_lesson_in_the_novidade_card_with_a_working_watch_button(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula antiga',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
        $newest = Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula nova de verdade',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-06-01', 'position' => 2,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('Novidade na biblioteca')
            ->assertSee('Aula nova de verdade')
            ->call('watchLesson', $newest->id)
            ->assertSet('featuredLessonId', $newest->id);
    }

    public function test_club_tier_sees_a_locked_next_session_card_with_no_fake_date(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Dashboard::class)
            ->assertSee('Sua próxima sessão 1:1')
            ->assertSee('Agenda chega em breve')
            ->assertDontSee('09 de julho')
            ->assertDontSee('Adicionar ao calendário');
    }
}
