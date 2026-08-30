<?php

namespace Tests\Unit\Notifications;

use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\MentorSessionCancelledForMentorNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorSessionCancelledForMentorNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createSession(): MentorSession
    {
        $mentor = User::factory()->create(['tier' => 'mentor', 'name' => 'Douglas Oliveira']);
        $member = User::factory()->create(['tier' => 'club', 'name' => 'Carla Nunes']);

        return MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3)->setTime(9, 0),
            'cancelled_at' => now(),
        ]);
    }

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new MentorSessionCancelledForMentorNotification($this->createSession()));
    }

    public function test_it_is_sent_only_via_mail(): void
    {
        $session = $this->createSession();

        $this->assertSame(['mail'], (new MentorSessionCancelledForMentorNotification($session))->via($session->mentor));
    }

    public function test_mail_message_has_the_expected_subject_and_content(): void
    {
        $session = $this->createSession();

        $mail = (new MentorSessionCancelledForMentorNotification($session))->toMail($session->mentor);
        $body = implode(' ', $mail->introLines);

        $this->assertSame('Sessão cancelada', $mail->subject);
        $this->assertStringContainsString('Carla Nunes', $body);
        $this->assertStringContainsString($session->scheduled_at->format('d/m/Y \à\s H:i'), $body);
        $this->assertStringContainsString('abriu de novo', $body);
    }
}
