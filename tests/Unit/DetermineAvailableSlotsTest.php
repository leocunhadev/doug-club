<?php

namespace Tests\Unit;

use App\Actions\DetermineAvailableSlots;
use App\Models\MentorAvailability;
use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetermineAvailableSlotsTest extends TestCase
{
    use RefreshDatabase;

    private function mentor(): User
    {
        return User::factory()->create(['tier' => 'mentor']);
    }

    public function test_a_three_hour_block_yields_exactly_two_ninety_minute_slots(): void
    {
        $mentor = $this->mentor();
        $targetDate = now()->addDays(5)->startOfDay();

        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $slots = (new DetermineAvailableSlots)->handle($mentor)
            ->filter(fn ($slot) => $slot->isSameDay($targetDate));

        $this->assertCount(2, $slots);
        $this->assertSame('09:00', $slots->first()->format('H:i'));
        $this->assertSame('10:30', $slots->last()->format('H:i'));
    }

    public function test_a_block_that_does_not_fit_a_whole_multiple_of_ninety_minutes_has_no_partial_slot(): void
    {
        $mentor = $this->mentor();
        $targetDate = now()->addDays(5)->startOfDay();

        // 100 minutes: one full 90-min slot fits (09:00-10:30), the remaining 10 minutes don't.
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:40',
        ]);

        $slots = (new DetermineAvailableSlots)->handle($mentor)
            ->filter(fn ($slot) => $slot->isSameDay($targetDate));

        $this->assertCount(1, $slots);
        $this->assertSame('09:00', $slots->first()->format('H:i'));
    }

    public function test_slots_inside_the_24_hour_minimum_notice_window_are_excluded(): void
    {
        $mentor = $this->mentor();
        $today = now()->dayOfWeek;

        // A block covering right now through the next few hours today — entirely inside the
        // 24h notice window, so it must produce zero slots for today.
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $today,
            'start_time' => '00:00', 'end_time' => '23:59',
        ]);

        $slots = (new DetermineAvailableSlots)->handle($mentor)
            ->filter(fn ($slot) => $slot->isToday());

        $this->assertCount(0, $slots);
    }

    public function test_an_already_booked_slot_is_excluded(): void
    {
        $mentor = $this->mentor();
        $member = User::factory()->create(['tier' => 'club']);
        $targetDate = now()->addDays(5)->startOfDay();

        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:30',
        ]);

        MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => $targetDate->copy()->setTime(9, 0),
        ]);

        $slots = (new DetermineAvailableSlots)->handle($mentor)
            ->filter(fn ($slot) => $slot->isSameDay($targetDate));

        $this->assertCount(0, $slots);
    }

    public function test_a_cancelled_booking_frees_the_slot_back_up(): void
    {
        $mentor = $this->mentor();
        $member = User::factory()->create(['tier' => 'club']);
        $targetDate = now()->addDays(5)->startOfDay();

        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:30',
        ]);

        MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => $targetDate->copy()->setTime(9, 0),
            'cancelled_at' => now(),
        ]);

        $slots = (new DetermineAvailableSlots)->handle($mentor)
            ->filter(fn ($slot) => $slot->isSameDay($targetDate));

        $this->assertCount(1, $slots);
    }

    public function test_slots_beyond_the_14_day_window_are_excluded(): void
    {
        $mentor = $this->mentor();
        $farDate = now()->addDays(20)->startOfDay();

        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $farDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:30',
        ]);

        $slots = (new DetermineAvailableSlots)->handle($mentor)
            ->filter(fn ($slot) => $slot->isSameDay($farDate));

        $this->assertCount(0, $slots);
    }
}
