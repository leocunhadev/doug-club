<?php

namespace App\Jobs;

use App\Models\MentorSession;
use App\Notifications\MentorSessionReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSessionReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public MentorSession $session) {}

    public function handle(): void
    {
        $this->session->refresh();

        if ($this->session->isCancelled() || $this->session->scheduled_at->isPast()) {
            return;
        }

        $this->session->member->notify(new MentorSessionReminderNotification($this->session));
    }
}
