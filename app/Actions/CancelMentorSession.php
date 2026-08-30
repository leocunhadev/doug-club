<?php

namespace App\Actions;

use App\Models\MentorSession;

class CancelMentorSession
{
    public function handle(MentorSession $session): void
    {
        $session->update(['cancelled_at' => now()]);
    }
}
