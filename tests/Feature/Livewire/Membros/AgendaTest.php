<?php

namespace Tests\Feature\Livewire\Membros;

use App\Actions\BookMentorSession;
use App\Livewire\Membros\Agenda;
use App\Models\MentorAvailability;
use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use RefreshDatabase;

    private function mentor(): User
    {
        return User::factory()->create(['tier' => 'mentor']);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/agenda')->assertRedirect('/login');
    }

    public function test_start_tier_is_redirected_to_the_upgrade_pitch(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $this->get('/membros/agenda')->assertRedirect(route('membros.upgrade'));
    }

    public function test_club_member_without_a_session_sees_the_booking_calendar(): void
    {
        $mentor = $this->mentor();
        $targetDate = now()->addDays(5)->startOfDay();
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:30',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Agenda::class)
            ->assertSee($targetDate->format('d'))
            ->assertDontSee('Cancelar sessão');
    }

    public function test_club_member_sees_an_empty_state_when_the_mentor_has_no_available_slots(): void
    {
        $this->mentor();

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Agenda::class)
            ->assertSee('Nenhum horário disponível no momento.');
    }

    public function test_club_member_with_an_upcoming_session_sees_it_instead_of_the_calendar(): void
    {
        $mentor = $this->mentor();
        $member = User::factory()->create(['tier' => 'club']);
        MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3)->setTime(9, 0),
        ]);

        $this->actingAs($member);

        Livewire::test(Agenda::class)
            ->assertSee('Cancelar sessão');
    }

    public function test_selecting_a_slot_shows_the_confirmation_card_without_booking_it(): void
    {
        $mentor = $this->mentor();
        $targetDate = now()->addDays(5)->startOfDay();
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:30',
        ]);

        $member = User::factory()->create(['tier' => 'club']);
        $this->actingAs($member);

        $slot = $targetDate->copy()->setTime(9, 0);

        Livewire::test(Agenda::class)
            ->call('selectDate', $targetDate->format('Y-m-d'))
            ->call('selectSlot', $slot->toIso8601String())
            ->assertSee('Confirmar sessão')
            ->assertDontSee('Cancelar sessão');

        $this->assertDatabaseMissing('mentor_sessions', ['member_id' => $member->id]);
    }

    public function test_confirming_a_selected_slot_creates_the_session(): void
    {
        Notification::fake();
        Queue::fake();

        $mentor = $this->mentor();
        $targetDate = now()->addDays(5)->startOfDay();
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:30',
        ]);

        $member = User::factory()->create(['tier' => 'club']);
        $this->actingAs($member);

        $slot = $targetDate->copy()->setTime(9, 0);

        Livewire::test(Agenda::class)
            ->call('selectDate', $targetDate->format('Y-m-d'))
            ->call('selectSlot', $slot->toIso8601String())
            ->call('confirmBooking')
            ->assertSee('Cancelar sessão');

        $this->assertDatabaseHas('mentor_sessions', [
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
        ]);
    }

    public function test_clearing_the_selection_hides_the_confirmation_card(): void
    {
        $mentor = $this->mentor();
        $targetDate = now()->addDays(5)->startOfDay();
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:30',
        ]);

        $member = User::factory()->create(['tier' => 'club']);
        $this->actingAs($member);

        $slot = $targetDate->copy()->setTime(9, 0);

        Livewire::test(Agenda::class)
            ->call('selectDate', $targetDate->format('Y-m-d'))
            ->call('selectSlot', $slot->toIso8601String())
            ->call('clearSelection')
            ->assertDontSee('Confirmar sessão');
    }

    public function test_changing_the_date_clears_a_pending_selection(): void
    {
        $mentor = $this->mentor();
        $firstDate = now()->addDays(5)->startOfDay();
        $secondDate = now()->addDays(6)->startOfDay();
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $firstDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:30',
        ]);
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $secondDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:30',
        ]);

        $member = User::factory()->create(['tier' => 'club']);
        $this->actingAs($member);

        $slot = $firstDate->copy()->setTime(9, 0);

        Livewire::test(Agenda::class)
            ->call('selectDate', $firstDate->format('Y-m-d'))
            ->call('selectSlot', $slot->toIso8601String())
            ->call('selectDate', $secondDate->format('Y-m-d'))
            ->assertDontSee('Confirmar sessão');
    }

    public function test_selecting_a_slot_not_in_the_available_list_is_ignored(): void
    {
        $mentor = $this->mentor();
        // No availability created at all — every slot is invalid.
        $member = User::factory()->create(['tier' => 'club']);
        $this->actingAs($member);

        $bogusSlot = now()->addDays(5)->setTime(9, 0);

        Livewire::test(Agenda::class)
            ->call('selectSlot', $bogusSlot->toIso8601String())
            ->call('confirmBooking');

        $this->assertDatabaseMissing('mentor_sessions', ['member_id' => $member->id]);
    }

    public function test_confirming_a_slot_that_was_just_taken_dispatches_a_toast(): void
    {
        $mentor = $this->mentor();
        $targetDate = now()->addDays(5)->startOfDay();
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:30',
        ]);
        $member = User::factory()->create(['tier' => 'club']);
        $this->actingAs($member);

        $slot = $targetDate->copy()->setTime(9, 0);

        // Forces the "someone else grabbed this slot" branch (BookMentorSession returning
        // null) regardless of DB state — the race itself is pre-existing, already-tested
        // behavior; this test only verifies the toast fires on that outcome.
        $this->app->bind(BookMentorSession::class, fn () => new class extends BookMentorSession
        {
            public function handle($mentor, $member, $scheduledAt): ?MentorSession
            {
                return null;
            }
        });

        Livewire::test(Agenda::class)
            ->call('selectDate', $targetDate->format('Y-m-d'))
            ->call('selectSlot', $slot->toIso8601String())
            ->call('confirmBooking')
            ->assertDispatched('toast', message: 'Esse horário acabou de ser preenchido. Escolha outro.');

        $this->assertDatabaseMissing('mentor_sessions', ['member_id' => $member->id]);
    }

    public function test_cancelling_more_than_24_hours_ahead_cancels_the_session(): void
    {
        Notification::fake();

        $mentor = $this->mentor();
        $member = User::factory()->create(['tier' => 'club']);
        $session = MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3),
        ]);

        $this->actingAs($member);

        Livewire::test(Agenda::class)->call('cancelSession');

        $this->assertNotNull($session->fresh()->cancelled_at);
    }

    public function test_cancelling_inside_24_hours_is_ignored(): void
    {
        $mentor = $this->mentor();
        $member = User::factory()->create(['tier' => 'club']);
        $session = MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addHours(12),
        ]);

        $this->actingAs($member);

        Livewire::test(Agenda::class)->call('cancelSession');

        $this->assertNull($session->fresh()->cancelled_at);
    }

    public function test_cannot_cancel_another_members_session(): void
    {
        $mentor = $this->mentor();
        $owner = User::factory()->create(['tier' => 'club']);
        $otherMember = User::factory()->create(['tier' => 'club']);
        $session = MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $owner->id,
            'scheduled_at' => now()->addDays(3),
        ]);

        $this->actingAs($otherMember);

        Livewire::test(Agenda::class)->call('cancelSession');

        $this->assertNull($session->fresh()->cancelled_at);
    }
}
