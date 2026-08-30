<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendSessionReminderJob;
use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\MentorSessionReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendSessionReminderJobTest extends TestCase
{
    use RefreshDatabase;

    private function createSession(array $overrides = []): MentorSession
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);

        return MentorSession::create(array_merge([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addHour(),
        ], $overrides));
    }

    public function test_notifies_the_member_for_a_valid_upcoming_session(): void
    {
        Notification::fake();
        $session = $this->createSession();

        (new SendSessionReminderJob($session))->handle();

        Notification::assertSentTo($session->member, MentorSessionReminderNotification::class);
    }

    public function test_does_not_notify_when_the_session_was_cancelled(): void
    {
        Notification::fake();
        $session = $this->createSession(['cancelled_at' => now()]);

        (new SendSessionReminderJob($session))->handle();

        Notification::assertNothingSent();
    }

    public function test_does_not_notify_when_the_scheduled_time_already_passed(): void
    {
        Notification::fake();
        $session = $this->createSession(['scheduled_at' => now()->subHour()]);

        (new SendSessionReminderJob($session))->handle();

        Notification::assertNothingSent();
    }
}
