<?php

namespace Tests\Unit\Notifications;

use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\MentorSessionReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorSessionReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createSession(): MentorSession
    {
        $mentor = User::factory()->create(['tier' => 'mentor', 'name' => 'Douglas Oliveira']);
        $member = User::factory()->create(['tier' => 'club', 'name' => 'Carla Nunes']);

        return MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addHour(),
        ]);
    }

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new MentorSessionReminderNotification($this->createSession()));
    }

    public function test_it_is_sent_only_via_mail(): void
    {
        $session = $this->createSession();

        $this->assertSame(['mail'], (new MentorSessionReminderNotification($session))->via($session->member));
    }

    public function test_mail_message_has_the_expected_subject_and_content(): void
    {
        $session = $this->createSession();

        $mail = (new MentorSessionReminderNotification($session))->toMail($session->member);
        $body = implode(' ', $mail->introLines);

        $this->assertSame('Sua sessão é daqui a pouco', $mail->subject);
        $this->assertStringContainsString('Douglas Oliveira', $body);
        $this->assertStringContainsString($session->scheduled_at->format('H:i'), $body);
        $this->assertSame(route('membros.agenda'), $mail->actionUrl);
    }
}
