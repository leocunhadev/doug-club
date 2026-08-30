<?php

namespace Tests\Unit;

use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function createMentorSession(array $overrides = []): MentorSession
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);

        return MentorSession::create(array_merge([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3),
        ], $overrides));
    }

    public function test_mentor_and_member_relationships_resolve(): void
    {
        $session = $this->createMentorSession();

        $this->assertTrue($session->mentor->is(User::find($session->mentor_id)));
        $this->assertTrue($session->member->is(User::find($session->member_id)));
    }

    public function test_is_cancelled_is_true_when_cancelled_at_is_set(): void
    {
        $session = $this->createMentorSession(['cancelled_at' => now()]);

        $this->assertTrue($session->isCancelled());
    }

    public function test_is_cancelled_is_false_when_cancelled_at_is_null(): void
    {
        $session = $this->createMentorSession();

        $this->assertFalse($session->isCancelled());
    }

    public function test_is_upcoming_is_true_for_a_future_non_cancelled_session(): void
    {
        $session = $this->createMentorSession(['scheduled_at' => now()->addDay()]);

        $this->assertTrue($session->isUpcoming());
    }

    public function test_is_upcoming_is_false_for_a_cancelled_session(): void
    {
        $session = $this->createMentorSession(['scheduled_at' => now()->addDay(), 'cancelled_at' => now()]);

        $this->assertFalse($session->isUpcoming());
    }

    public function test_is_upcoming_is_false_for_a_past_session(): void
    {
        $session = $this->createMentorSession(['scheduled_at' => now()->subDay()]);

        $this->assertFalse($session->isUpcoming());
    }

    public function test_session_is_deleted_when_the_member_is_deleted(): void
    {
        $session = $this->createMentorSession();
        $member = $session->member;

        $member->delete();

        $this->assertDatabaseMissing('mentor_sessions', ['id' => $session->id]);
    }
}
