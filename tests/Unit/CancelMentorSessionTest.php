<?php

namespace Tests\Unit;

use App\Actions\CancelMentorSession;
use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelMentorSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_cancelled_at(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        $session = MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3),
        ]);

        (new CancelMentorSession)->handle($session);

        $this->assertNotNull($session->fresh()->cancelled_at);
    }
}
