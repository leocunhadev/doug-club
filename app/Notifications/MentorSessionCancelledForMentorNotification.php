<?php

namespace App\Notifications;

use App\Models\MentorSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentorSessionCancelledForMentorNotification extends Notification implements ShouldQueue
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
            ->subject('Sessão cancelada')
            ->greeting("Oi, {$notifiable->name}!")
            ->line("{$this->session->member->name} cancelou a sessão 1:1 que estava marcada para {$this->session->scheduled_at->format('d/m/Y \à\s H:i')}.")
            ->line('O horário abriu de novo na sua agenda.');
    }
}
