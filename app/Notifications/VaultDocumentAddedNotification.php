<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VaultDocumentAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $title) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Novo documento no seu cofre')
            ->greeting("Oi, {$notifiable->name}!")
            ->line("O Douglas adicionou um novo documento no seu cofre: {$this->title}.")
            ->action('Ver meu cofre', route('membros.cofre'));
    }
}
