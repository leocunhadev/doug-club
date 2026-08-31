<?php

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\VaultDocumentAddedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultDocumentAddedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new VaultDocumentAddedNotification('Planilha de precificação'));
    }

    public function test_it_is_sent_only_via_mail(): void
    {
        $notifiable = User::factory()->create();

        $this->assertSame(['mail'], (new VaultDocumentAddedNotification('Planilha de precificação'))->via($notifiable));
    }

    public function test_mail_message_has_the_expected_subject_and_content(): void
    {
        $notifiable = User::factory()->create(['name' => 'Ricardo Mendes']);

        $mail = (new VaultDocumentAddedNotification('Planilha de precificação'))->toMail($notifiable);
        $body = implode(' ', $mail->introLines);

        $this->assertSame('Novo documento no seu cofre', $mail->subject);
        $this->assertStringContainsString('Planilha de precificação', $body);
        $this->assertSame(route('membros.cofre'), $mail->actionUrl);
    }
}
