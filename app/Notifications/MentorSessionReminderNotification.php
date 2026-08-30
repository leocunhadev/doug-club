<?php

namespace App\Notifications;

use App\Models\MentorSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentorSessionReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MentorSession $session) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sua sessão é daqui a pouco')
            ->greeting("Oi, {$notifiable->name}!")
            ->line("Sua sessão 1:1 com {$this->session->mentor->name} é às {$this->session->scheduled_at->format('H:i')}.")
            ->action('Ver minha agenda', route('membros.agenda'));
    }
}
