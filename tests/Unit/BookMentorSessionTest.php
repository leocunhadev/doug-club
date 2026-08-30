<?php

namespace Tests\Unit;

use App\Actions\BookMentorSession;
use App\Jobs\SendSessionReminderJob;
use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\MentorSessionBookedForMentorNotification;
use App\Notifications\MentorSessionBookedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
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

    public function test_notifies_the_member_and_the_mentor_when_a_session_is_booked(): void
    {
        Notification::fake();

        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        $scheduledAt = now()->addDays(3)->setTime(9, 0);

        $session = (new BookMentorSession)->handle($mentor, $member, $scheduledAt);

        Notification::assertSentTo($member, MentorSessionBookedNotification::class, function ($notification) use ($session) {
            return $notification->session->is($session);
        });
        Notification::assertSentTo($mentor, MentorSessionBookedForMentorNotification::class, function ($notification) use ($session) {
            return $notification->session->is($session);
        });
    }

    public function test_does_not_notify_anyone_when_the_slot_is_already_booked(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $firstMember = User::factory()->create(['tier' => 'club']);
        $secondMember = User::factory()->create(['tier' => 'club']);
        $scheduledAt = now()->addDays(3)->setTime(9, 0);

        (new BookMentorSession)->handle($mentor, $firstMember, $scheduledAt);

        Notification::fake();
        (new BookMentorSession)->handle($mentor, $secondMember, $scheduledAt);

        Notification::assertNotSentTo($secondMember, MentorSessionBookedNotification::class);
        Notification::assertNotSentTo($mentor, MentorSessionBookedForMentorNotification::class);
    }

    public function test_dispatches_a_reminder_job_delayed_to_one_hour_before_the_session(): void
    {
        Queue::fake();

        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        $scheduledAt = now()->addDays(3)->setTime(9, 0);

        $session = (new BookMentorSession)->handle($mentor, $member, $scheduledAt);

        Queue::assertPushed(SendSessionReminderJob::class, function ($job) use ($session, $scheduledAt) {
            return $job->session->is($session)
                && $job->delay !== null
                && abs($job->delay->timestamp - $scheduledAt->copy()->subHour()->timestamp) < 2;
        });
    }
}
