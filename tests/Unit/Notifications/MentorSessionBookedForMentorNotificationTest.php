<?php

namespace Tests\Unit\Notifications;

use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\MentorSessionBookedForMentorNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorSessionBookedForMentorNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createSession(): MentorSession
    {
        $mentor = User::factory()->create(['tier' => 'mentor', 'name' => 'Douglas Oliveira']);
        $member = User::factory()->create(['tier' => 'club', 'name' => 'Carla Nunes']);

        return MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3)->setTime(9, 0),
        ]);
    }

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new MentorSessionBookedForMentorNotification($this->createSession()));
    }

    public function test_it_is_sent_only_via_mail(): void
    {
        $session = $this->createSession();

        $this->assertSame(['mail'], (new MentorSessionBookedForMentorNotification($session))->via($session->mentor));
    }

    public function test_mail_message_has_the_expected_subject_and_content(): void
    {
        $session = $this->createSession();

        $mail = (new MentorSessionBookedForMentorNotification($session))->toMail($session->mentor);
        $body = implode(' ', $mail->introLines);

        $this->assertSame('Nova sessão marcada', $mail->subject);
        $this->assertStringContainsString('Carla Nunes', $body);
        $this->assertStringContainsString($session->scheduled_at->format('d/m/Y \à\s H:i'), $body);
    }
}
