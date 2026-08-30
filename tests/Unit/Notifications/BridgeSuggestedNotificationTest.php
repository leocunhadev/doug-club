<?php

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\BridgeSuggestedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeSuggestedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_implements_should_queue(): void
    {
        $other = User::factory()->create(['name' => 'Marina Alves']);

        $this->assertInstanceOf(ShouldQueue::class, new BridgeSuggestedNotification($other, 'precificação', true));
    }

    public function test_it_is_sent_only_via_mail(): void
    {
        $notifiable = User::factory()->create();
        $other = User::factory()->create(['name' => 'Marina Alves']);

        $this->assertSame(['mail'], (new BridgeSuggestedNotification($other, 'precificação', true))->via($notifiable));
    }

    public function test_mail_message_for_the_learner_mentions_what_the_other_can_teach(): void
    {
        $notifiable = User::factory()->create(['name' => 'Ricardo Mendes']);
        $other = User::factory()->create(['name' => 'Marina Alves']);

        $mail = (new BridgeSuggestedNotification($other, 'precificação', true))->toMail($notifiable);
        $body = implode(' ', $mail->introLines);

        $this->assertSame('Uma ponte foi feita pra você', $mail->subject);
        $this->assertStringContainsString('Marina Alves', $body);
        $this->assertStringContainsString('pode te ajudar com precificação', $body);
        $this->assertSame(route('membros.pessoas'), $mail->actionUrl);
    }

    public function test_mail_message_for_the_teacher_mentions_what_the_other_wants_to_learn(): void
    {
        $notifiable = User::factory()->create(['name' => 'Marina Alves']);
        $other = User::factory()->create(['name' => 'Ricardo Mendes']);

        $mail = (new BridgeSuggestedNotification($other, 'precificação', false))->toMail($notifiable);
        $body = implode(' ', $mail->introLines);

        $this->assertStringContainsString('Ricardo Mendes', $body);
        $this->assertStringContainsString('quer aprender sobre precificação', $body);
    }
}
