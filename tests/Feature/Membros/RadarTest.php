<?php

namespace Tests\Feature\Membros;

use App\Livewire\Membros\Radar;
use App\Models\BridgeRequest;
use App\Models\Course;
use App\Models\Encontro;
use App\Models\EncontroFeedback;
use App\Models\Lesson;
use App\Models\LessonFeedback;
use App\Models\MentorCommitment;
use App\Models\MentorNote;
use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RadarTest extends TestCase
{
    use RefreshDatabase;

    private function lesson(): Lesson
    {
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);

        return Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
    }

    private function encontro(): Encontro
    {
        return Encontro::create([
            'tema' => 'O comercial é gente', 'quem' => 'Com Douglas',
            'scheduled_at' => now()->addDay(),
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/mentor/radar')->assertRedirect(route('login'));
    }

    public function test_club_tier_member_is_denied(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $this->get('/membros/mentor/radar')->assertRedirect(route('dashboard'));
    }

    public function test_today_kpi_counts_only_non_cancelled_sessions_scheduled_today(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($mentor);

        $today = User::factory()->create(['tier' => 'club', 'name' => 'Ana Hoje']);
        $yesterday = User::factory()->create(['tier' => 'club', 'name' => 'Beto Ontem']);
        $cancelledToday = User::factory()->create(['tier' => 'club', 'name' => 'Caio Cancelado']);

        MentorSession::create(['mentor_id' => $mentor->id, 'member_id' => $today->id, 'scheduled_at' => now()->setTime(10, 0)]);
        MentorSession::create(['mentor_id' => $mentor->id, 'member_id' => $yesterday->id, 'scheduled_at' => now()->subDay()->setTime(10, 0)]);
        MentorSession::create(['mentor_id' => $mentor->id, 'member_id' => $cancelledToday->id, 'scheduled_at' => now()->setTime(15, 0), 'cancelled_at' => now()]);

        Livewire::test(Radar::class)
            ->assertSee('Ana Hoje')
            ->assertDontSee('Beto Ontem')
            ->assertDontSee('Caio Cancelado');
    }

    public function test_today_kpi_lists_each_sessions_member_and_time(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($mentor);

        $first = User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes']);
        $second = User::factory()->create(['tier' => 'club', 'name' => 'Alessandra Ribeiro']);

        MentorSession::create(['mentor_id' => $mentor->id, 'member_id' => $first->id, 'scheduled_at' => now()->setTime(10, 0)]);
        MentorSession::create(['mentor_id' => $mentor->id, 'member_id' => $second->id, 'scheduled_at' => now()->setTime(15, 0)]);

        Livewire::test(Radar::class)
            ->assertSee('Ricardo Mendes às 10h')
            ->assertSee('Alessandra Ribeiro às 15h');
    }

    public function test_shows_no_sessions_today_message_when_there_are_none(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        Livewire::test(Radar::class)
            ->assertSee('Nenhuma sessão hoje.');
    }

    public function test_nps_average_combines_lesson_and_encontro_feedback_from_the_last_30_days(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $lesson = $this->lesson();
        $encontro = $this->encontro();

        LessonFeedback::create(['user_id' => User::factory()->create()->id, 'lesson_id' => $lesson->id, 'score' => 10, 'created_at' => now()]);
        EncontroFeedback::create(['user_id' => User::factory()->create()->id, 'encontro_id' => $encontro->id, 'score' => 8, 'created_at' => now()]);

        Livewire::test(Radar::class)
            ->assertSee('9,0');
    }

    public function test_nps_average_excludes_feedback_older_than_30_days(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $lesson = $this->lesson();

        LessonFeedback::create(['user_id' => User::factory()->create()->id, 'lesson_id' => $lesson->id, 'score' => 10])
            ->forceFill(['created_at' => now()])
            ->save();
        LessonFeedback::create(['user_id' => User::factory()->create()->id, 'lesson_id' => $lesson->id, 'score' => 0])
            ->forceFill(['created_at' => now()->subDays(31)])
            ->save();

        Livewire::test(Radar::class)
            ->assertSee('10,0')
            ->assertDontSee('5,0');
    }

    public function test_nps_average_shows_a_dash_when_there_is_no_recent_feedback(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        Livewire::test(Radar::class)
            ->assertSee('—');
    }

    public function test_member_overdue_for_more_than_30_days_since_last_session_is_flagged(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($mentor);
        $member = User::factory()->create(['tier' => 'club', 'name' => 'Caio Fonseca']);

        MentorSession::create(['mentor_id' => $mentor->id, 'member_id' => $member->id, 'scheduled_at' => now()->subDays(34)]);

        Livewire::test(Radar::class)
            ->assertSee('Caio Fonseca está há 34 dias sem sessão');
    }

    public function test_member_with_a_session_within_30_days_is_not_flagged(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($mentor);
        $member = User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes']);

        MentorSession::create(['mentor_id' => $mentor->id, 'member_id' => $member->id, 'scheduled_at' => now()->subDays(29)]);

        Livewire::test(Radar::class)
            ->assertSee('Nenhum mentorado atrasado.');
    }

    public function test_member_with_no_session_ever_created_more_than_30_days_ago_is_flagged(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        User::factory()->create(['tier' => 'club', 'name' => 'Marina Prado', 'created_at' => now()->subDays(40)]);

        Livewire::test(Radar::class)
            ->assertSee('Marina Prado está há 40 dias sem sessão');
    }

    public function test_member_with_no_session_ever_created_recently_is_not_flagged(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        User::factory()->create(['tier' => 'club', 'name' => 'Marina Prado', 'created_at' => now()->subDays(5)]);

        Livewire::test(Radar::class)
            ->assertSee('Nenhum mentorado atrasado.');
    }

    public function test_alert_with_more_than_one_overdue_member_shows_only_the_count(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($mentor);
        $first = User::factory()->create(['tier' => 'club']);
        $second = User::factory()->create(['tier' => 'club']);

        MentorSession::create(['mentor_id' => $mentor->id, 'member_id' => $first->id, 'scheduled_at' => now()->subDays(40)]);
        MentorSession::create(['mentor_id' => $mentor->id, 'member_id' => $second->id, 'scheduled_at' => now()->subDays(50)]);

        Livewire::test(Radar::class)
            ->assertSee('2 mentorados sem sessão há mais de 30 dias');
    }

    public function test_briefing_shows_the_latest_note_and_active_commitment_for_todays_sessions(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($mentor);
        $member = User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes']);

        MentorSession::create(['mentor_id' => $mentor->id, 'member_id' => $member->id, 'scheduled_at' => now()->setTime(10, 0)]);
        MentorNote::create(['member_id' => $member->id, 'mentor_id' => $mentor->id, 'title' => 'Antiga', 'body' => 'Nota antiga'])
            ->forceFill(['created_at' => now()->subDays(2)])
            ->save();
        MentorNote::create(['member_id' => $member->id, 'mentor_id' => $mentor->id, 'title' => 'Recente', 'body' => 'Decidiu assumir o comercial.'])
            ->forceFill(['created_at' => now()->subDay()])
            ->save();
        MentorCommitment::create(['member_id' => $member->id, 'text' => 'Gravar 3 conversas de venda']);

        Livewire::test(Radar::class)
            ->assertSee('Decidiu assumir o comercial.')
            ->assertDontSee('Nota antiga')
            ->assertSee('Gravar 3 conversas de venda');
    }

    public function test_briefing_shows_placeholders_when_there_is_no_note_or_commitment_yet(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($mentor);
        $member = User::factory()->create(['tier' => 'club']);

        MentorSession::create(['mentor_id' => $mentor->id, 'member_id' => $member->id, 'scheduled_at' => now()->setTime(10, 0)]);

        Livewire::test(Radar::class)
            ->assertSee('Nenhuma nota registrada ainda.');
    }

    public function test_suggested_bridges_shows_a_match_when_tags_overlap_case_insensitively(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes', 'learn_tags' => ['Precificação']]);
        User::factory()->create(['tier' => 'club', 'name' => 'Marina Alves', 'teach_tags' => ['precificação']]);

        Livewire::test(Radar::class)
            ->assertSee('Pontes sugeridas')
            ->assertSee('Ricardo Mendes')
            ->assertSee('Marina Alves')
            ->assertSee('Precificação')
            ->assertDontSee('Nenhuma ponte sugerida no momento.');
    }

    public function test_suggested_bridges_shows_the_empty_state_when_there_are_no_matches(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes', 'learn_tags' => ['Vendas']]);
        User::factory()->create(['tier' => 'club', 'name' => 'Marina Alves', 'teach_tags' => ['Marketing']]);

        Livewire::test(Radar::class)->assertSee('Nenhuma ponte sugerida no momento.');
    }

    public function test_suggested_bridges_excludes_a_pair_with_an_existing_bridge_request(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $learner = User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes', 'learn_tags' => ['Precificação']]);
        $teacher = User::factory()->create(['tier' => 'club', 'name' => 'Marina Alves', 'teach_tags' => ['precificação']]);
        BridgeRequest::create(['requester_id' => $learner->id, 'target_id' => $teacher->id]);

        Livewire::test(Radar::class)->assertSee('Nenhuma ponte sugerida no momento.');
    }

    public function test_suggested_bridges_excludes_members_without_matching_tags(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        User::factory()->create(['tier' => 'club', 'name' => 'Sem Tags']);
        User::factory()->create(['tier' => 'club', 'name' => 'Também Sem Tags']);

        Livewire::test(Radar::class)->assertSee('Nenhuma ponte sugerida no momento.');
    }

    public function test_suggested_bridges_caps_results_at_three(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        for ($i = 1; $i <= 4; $i++) {
            User::factory()->create(['tier' => 'club', 'name' => "Aluno {$i}", 'learn_tags' => ["assunto-{$i}"]]);
            User::factory()->create(['tier' => 'club', 'name' => "Professor {$i}", 'teach_tags' => ["assunto-{$i}"]]);
        }

        $matches = Livewire::test(Radar::class)->instance()->suggestedBridges();

        $this->assertCount(3, $matches);
    }
}
