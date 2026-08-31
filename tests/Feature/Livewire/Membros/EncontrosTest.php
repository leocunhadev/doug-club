<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\Encontros;
use App\Models\Course;
use App\Models\Encontro;
use App\Models\EncontroFeedback;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EncontrosTest extends TestCase
{
    use RefreshDatabase;

    private function encontro(array $overrides = []): Encontro
    {
        return Encontro::create(array_merge([
            'tema' => 'O comercial é gente', 'quem' => 'Com Douglas',
            'scheduled_at' => now()->addDay(),
        ], $overrides));
    }

    private function lesson(): Lesson
    {
        $course = Course::create(['label' => 'Curso', 'title' => 'Teste', 'position' => 10]);

        return Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Gravação',
            'video_provider' => 'vimeo', 'video_id' => '123', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/encontros')->assertRedirect('/login');
    }

    public function test_start_tier_is_redirected_to_the_upgrade_pitch(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $this->get('/encontros')->assertRedirect(route('membros.upgrade'));
    }

    public function test_club_tier_can_access_the_page(): void
    {
        $this->encontro();

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $this->get('/encontros')->assertOk()->assertSee('Encontros do grupo');
    }

    public function test_mentor_tier_can_access_the_page(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        $this->get('/encontros')->assertOk();
    }

    public function test_upcoming_come_first_ascending_then_past_descending(): void
    {
        $this->encontro(['tema' => 'Daqui a dois dias', 'scheduled_at' => now()->addDays(2)]);
        $this->encontro(['tema' => 'Amanhã', 'scheduled_at' => now()->addDay()]);
        $this->encontro(['tema' => 'Ontem', 'scheduled_at' => now()->subDay()]);
        $this->encontro(['tema' => 'Semana passada', 'scheduled_at' => now()->subWeek()]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Encontros::class)
            ->assertSeeInOrder(['Amanhã', 'Daqui a dois dias', 'Ontem', 'Semana passada']);
    }

    public function test_proximo_badge_appears_only_on_the_nearest_upcoming_encontro(): void
    {
        $this->encontro(['tema' => 'Mais distante', 'scheduled_at' => now()->addDays(5)]);
        $this->encontro(['tema' => 'Mais próximo', 'scheduled_at' => now()->addDay()]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $html = Livewire::test(Encontros::class)->html();

        $this->assertSame(1, substr_count($html, 'Próximo'));
    }

    public function test_future_encontro_with_access_url_shows_the_entrar_link(): void
    {
        $this->encontro(['access_url' => 'https://zoom.us/j/123']);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $html = Livewire::test(Encontros::class)->html();

        $this->assertStringContainsString('https://zoom.us/j/123', $html);
        $this->assertStringContainsString('Entrar', $html);
    }

    public function test_future_encontro_without_access_url_shows_link_em_breve(): void
    {
        $this->encontro(['access_url' => null]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Encontros::class)->assertSee('Link em breve');
    }

    public function test_past_encontro_with_recording_links_to_the_library(): void
    {
        $lesson = $this->lesson();
        $this->encontro([
            'scheduled_at' => now()->subDay(), 'recording_lesson_id' => $lesson->id,
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $html = Livewire::test(Encontros::class)->html();

        $this->assertStringContainsString('Ver na biblioteca', $html);
        $this->assertStringContainsString(route('membros.aulas', ['lesson' => $lesson->id]), $html);
    }

    public function test_past_encontro_without_recording_shows_gravacao_em_breve(): void
    {
        $this->encontro(['scheduled_at' => now()->subDay(), 'recording_lesson_id' => null]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Encontros::class)->assertSee('Gravação em breve');
    }

    public function test_empty_state_shown_with_no_encontros(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Encontros::class)->assertSee('Nenhum encontro agendado ainda.');
    }

    public function test_avaliar_button_appears_only_on_past_encontros(): void
    {
        $this->encontro(['tema' => 'Futuro', 'scheduled_at' => now()->addDay()]);
        $this->encontro(['tema' => 'Passado', 'scheduled_at' => now()->subDay()]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $html = Livewire::test(Encontros::class)->html();

        $this->assertSame(1, substr_count($html, 'Avaliar'));
    }

    public function test_avaliar_button_disappears_after_the_user_already_rated_that_encontro(): void
    {
        $ratedEncontro = $this->encontro(['tema' => 'Já avaliado', 'scheduled_at' => now()->subDay()]);
        $this->encontro(['tema' => 'Ainda não avaliado', 'scheduled_at' => now()->subDays(2)]);

        $user = User::factory()->create(['tier' => 'club']);
        EncontroFeedback::create(['user_id' => $user->id, 'encontro_id' => $ratedEncontro->id, 'score' => 8]);

        $this->actingAs($user);

        $html = Livewire::test(Encontros::class)->html();

        $this->assertSame(1, substr_count($html, 'Avaliar'));
    }

    public function test_submit_encontro_nps_score_persists_the_score(): void
    {
        $encontro = $this->encontro(['scheduled_at' => now()->subDay()]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Encontros::class)
            ->call('submitEncontroNpsScore', $encontro->id, 9);

        $this->assertDatabaseHas('encontro_feedback', [
            'encontro_id' => $encontro->id,
            'score' => 9,
        ]);
    }

    public function test_submit_encontro_nps_score_is_a_no_op_for_a_nonexistent_encontro(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Encontros::class)
            ->call('submitEncontroNpsScore', 999999, 9);

        $this->assertDatabaseMissing('encontro_feedback', ['encontro_id' => 999999]);
    }
}
