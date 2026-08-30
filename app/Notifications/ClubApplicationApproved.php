<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClubApplicationApproved extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Você foi aprovado pro CLUB!')
            ->greeting("Oi, {$notifiable->name}!")
            ->line('O Douglas analisou sua aplicação e você já faz parte do CLUB.')
            ->line('Sessões 1:1, cofre de documentos, encontros ao vivo e a rede de pessoas do CLUB já estão liberados pra você.')
            ->action('Entrar no CLUB', route('dashboard'))
            ->line('Bem-vindo!');
    }
}
