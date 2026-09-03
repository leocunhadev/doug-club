<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\Dashboard;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonFeedback;
use App\Models\LessonProgress;
use App\Models\MentorNote;
use App\Models\MentorSession;
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
        $this->get('/')->assertRedirect('/login');
    }

    public function test_the_global_toast_component_is_present_on_the_page(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Dashboard::class)->assertSeeHtml('x-on:toast.window');
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

    public function test_submit_nps_score_records_the_feedback(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('submitNpsScore', $lesson->id, 9);

        $this->assertDatabaseHas('lesson_feedback', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'score' => 9,
        ]);
    }

    public function test_submit_nps_score_is_ignored_for_a_lesson_unavailable_to_the_users_tier(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula CLUB',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
            'tier' => 'club',
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('submitNpsScore', $lesson->id, 9);

        $this->assertDatabaseMissing('lesson_feedback', ['lesson_id' => $lesson->id]);
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

    public function test_hero_player_passes_has_feedback_false_when_the_user_has_not_rated_the_lesson(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('hasFeedback: false', false);
    }

    public function test_hero_player_passes_has_feedback_true_when_the_user_already_rated_the_lesson(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
        LessonFeedback::create(['user_id' => $user->id, 'lesson_id' => $lesson->id, 'score' => 10]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('hasFeedback: true', false);
    }

    public function test_the_shared_nps_modal_is_present_on_the_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('open-nps-modal', false);
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

    public function test_user_dropdown_links_to_the_sobre_page(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Dashboard::class)
            ->assertSee('Sobre')
            ->assertSee(route('membros.sobre'), false);
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

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee(route('membros.aulas.materiais', $lesson), false);
    }

    public function test_dashboard_page_renders_through_the_paper_layout(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
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

    public function test_footer_renders_brand_and_tagline(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Dashboard::class)
            ->assertSee('DO.ing CLUB')
            ->assertSee('Decisão Orientada')
            ->assertSee('Tudo é gente. Até o software.')
            ->assertDontSee('Política de Privacidade')
            ->assertDontSee('Todos os direitos reservados')
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

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/agenda"[^>]*>\s*Marcar minha sessão#s',
            $html,
        );

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/aulas"[^>]*>\s*Biblioteca de aulas#s',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/frameworks"[^>]*>\s*Frameworks DO#s',
            $html,
        );
    }

    public function test_start_tier_quick_links_show_conhecer_o_club_as_the_third_link(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'start']));

        Livewire::test(Dashboard::class)->assertSee('Conhecer o CLUB');
    }

    public function test_start_tier_conhecer_o_club_link_is_clickable_not_locked(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $html = Livewire::test(Dashboard::class)->html();

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/upgrade"[^>]*>\s*Conhecer o CLUB#s',
            $html,
        );
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

    public function test_club_member_without_a_booked_session_sees_a_cta_to_book_one(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Dashboard::class)
            ->assertSee('Sua próxima sessão 1:1')
            ->assertSee('Marque sua sessão')
            ->assertSee(route('membros.agenda'), false)
            ->assertDontSee('09 de julho')
            ->assertDontSee('Adicionar ao calendário');
    }

    public function test_club_member_with_a_booked_session_sees_the_real_date(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        $session = MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3)->setTime(9, 0),
        ]);

        $this->actingAs($member);

        Livewire::test(Dashboard::class)
            ->assertSee('Sua próxima sessão 1:1')
            ->assertSee($session->scheduled_at->format('d/m/Y \à\s H:i'))
            ->assertSee('Sessão 1:1 · 90 minutos.')
            ->assertSee(route('membros.agenda'), false)
            ->assertDontSee('Marque sua sessão');
    }

    public function test_club_member_does_not_see_another_members_booked_session(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $otherMember = User::factory()->create(['tier' => 'club']);
        MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $otherMember->id,
            'scheduled_at' => now()->addDays(3)->setTime(9, 0),
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Dashboard::class)->assertSee('Marque sua sessão');
    }

    public function test_club_member_does_not_see_a_cancelled_session(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3)->setTime(9, 0),
            'cancelled_at' => now(),
        ]);

        $this->actingAs($member);

        Livewire::test(Dashboard::class)->assertSee('Marque sua sessão');
    }

    public function test_mentor_without_an_upcoming_session_sees_a_cta_to_configure_availability(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        Livewire::test(Dashboard::class)
            ->assertSee('Sua próxima sessão 1:1')
            ->assertSee('Nenhuma sessão marcada')
            ->assertSee(route('mentor.disp'), false);
    }

    public function test_mentor_with_an_upcoming_session_sees_the_member_name_and_real_date(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club', 'name' => 'Carla Nunes']);
        $session = MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3)->setTime(9, 0),
        ]);

        $this->actingAs($mentor);

        Livewire::test(Dashboard::class)
            ->assertSee('Sua próxima sessão 1:1')
            ->assertSee($session->scheduled_at->format('d/m/Y \à\s H:i'))
            ->assertSee('Sessão 1:1 com Carla Nunes.')
            ->assertSee(route('mentor.radar'), false)
            ->assertDontSee('Nenhuma sessão marcada');
    }

    public function test_start_tier_never_sees_the_session_card(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'start']));

        Livewire::test(Dashboard::class)->assertDontSee('Sua próxima sessão 1:1');
    }

    public function test_club_member_with_a_note_sees_the_where_we_left_off_block(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor', 'name' => 'Douglas Oliveira']);
        $member = User::factory()->create(['tier' => 'club']);
        MentorNote::create([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'Discurso de venda', 'body' => 'Você decidiu assumir o discurso de venda.',
        ]);

        $this->actingAs($member);

        Livewire::test(Dashboard::class)
            ->assertSee('Você decidiu assumir o discurso de venda.')
            ->assertSee('Onde paramos · nota de Douglas Oliveira')
            ->assertDontSee('Discurso de venda');
    }

    public function test_club_member_without_a_note_does_not_see_the_where_we_left_off_block(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Dashboard::class)->assertDontSee('Onde paramos');
    }

    public function test_club_member_sees_only_their_own_latest_note(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        MentorNote::create([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'Nota antiga', 'body' => 'Primeira nota.',
        ]);
        $newest = MentorNote::create([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'Nota recente', 'body' => 'Segunda nota, mais recente.',
        ]);
        $newest->forceFill(['created_at' => now()->addMinute()])->save();

        $otherMember = User::factory()->create(['tier' => 'club']);
        MentorNote::create([
            'member_id' => $otherMember->id, 'mentor_id' => $mentor->id,
            'title' => 'Nota de outro membro', 'body' => 'Isso não deveria aparecer.',
        ]);

        $this->actingAs($member);

        Livewire::test(Dashboard::class)
            ->assertSee('Segunda nota, mais recente.')
            ->assertDontSee('Primeira nota.')
            ->assertDontSee('Isso não deveria aparecer.');
    }

    public function test_mentor_never_sees_the_where_we_left_off_block(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        MentorNote::create([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'Nota', 'body' => 'Isso é sobre o membro, não sobre o mentor.',
        ]);

        $this->actingAs($mentor);

        Livewire::test(Dashboard::class)->assertDontSee('Onde paramos');
    }

    public function test_hero_player_refuses_to_render_a_club_only_lesson_for_a_start_tier_viewer(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $clubLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula só de club',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'club',
        ]);

        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $clubLesson->id,
            'status' => 'watching', 'last_watched_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertDontSee("wire:key=\"hero-player-{$clubLesson->id}\"", false)
            ->assertSee('Nenhuma aula disponível ainda.');
    }

    public function test_newest_lesson_card_never_recommends_a_club_only_lesson_to_a_start_tier_user(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula start mais antiga',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula club mais nova',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-06-01', 'position' => 2,
            'category' => 'Encontros', 'tier' => 'club',
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('Aula start mais antiga')
            ->assertDontSee('Aula club mais nova');
    }
}
