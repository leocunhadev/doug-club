<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BridgeSuggestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $otherMember,
        public string $tag,
        public bool $iAmTheLearner,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $line = $this->iAmTheLearner
            ? "O Douglas te apresentou a {$this->otherMember->name}, que pode te ajudar com {$this->tag}."
            : "O Douglas te apresentou a {$this->otherMember->name}, que quer aprender sobre {$this->tag} — e você pode ajudar.";

        return (new MailMessage)
            ->subject('Uma ponte foi feita pra você')
            ->greeting("Oi, {$notifiable->name}!")
            ->line($line)
            ->action('Ver pessoas do CLUB', route('membros.pessoas'));
    }
}
