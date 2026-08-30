<?php

namespace App\Actions;

use App\Models\MentorSession;
use App\Notifications\MentorSessionCancelledForMentorNotification;

class CancelMentorSession
{
    public function handle(MentorSession $session): void
    {
        $session->update(['cancelled_at' => now()]);

        $session->mentor->notify(new MentorSessionCancelledForMentorNotification($session));
    }
}
