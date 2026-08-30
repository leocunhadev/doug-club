<?php

namespace Tests\Unit;

use App\Actions\BookMentorSession;
use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookMentorSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_session(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        $scheduledAt = now()->addDays(3)->setTime(9, 0);

        $session = (new BookMentorSession)->handle($mentor, $member, $scheduledAt);

        $this->assertNotNull($session);
        $this->assertDatabaseHas('mentor_sessions', [
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
        ]);
    }

    public function test_returns_null_and_creates_nothing_when_the_slot_is_already_booked(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $firstMember = User::factory()->create(['tier' => 'club']);
        $secondMember = User::factory()->create(['tier' => 'club']);
        $scheduledAt = now()->addDays(3)->setTime(9, 0);

        (new BookMentorSession)->handle($mentor, $firstMember, $scheduledAt);
        $result = (new BookMentorSession)->handle($mentor, $secondMember, $scheduledAt);

        $this->assertNull($result);
        $this->assertSame(1, MentorSession::query()->where('mentor_id', $mentor->id)->count());
    }

    public function test_allows_rebooking_a_slot_whose_previous_session_was_cancelled(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $firstMember = User::factory()->create(['tier' => 'club']);
        $secondMember = User::factory()->create(['tier' => 'club']);
        $scheduledAt = now()->addDays(3)->setTime(9, 0);

        $original = (new BookMentorSession)->handle($mentor, $firstMember, $scheduledAt);
        $original->update(['cancelled_at' => now()]);

        $result = (new BookMentorSession)->handle($mentor, $secondMember, $scheduledAt);

        $this->assertNotNull($result);
        $this->assertSame($secondMember->id, $result->member_id);
    }
}
