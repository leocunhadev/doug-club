<?php

namespace App\Notifications;

use App\Models\MentorSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentorSessionBookedForMentorNotification extends Notification implements ShouldQueue
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
            ->subject('Nova sessão marcada')
            ->greeting("Oi, {$notifiable->name}!")
            ->line("{$this->session->member->name} marcou uma sessão 1:1 com você para {$this->session->scheduled_at->format('d/m/Y \à\s H:i')}.");
    }
}
