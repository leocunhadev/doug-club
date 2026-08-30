<?php

namespace App\Actions;

use App\Models\MentorSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookMentorSession
{
    public function handle(User $mentor, User $member, Carbon $scheduledAt): ?MentorSession
    {
        return DB::transaction(function () use ($mentor, $member, $scheduledAt) {
            $alreadyBooked = MentorSession::query()
                ->where('mentor_id', $mentor->id)
                ->where('scheduled_at', $scheduledAt)
                ->whereNull('cancelled_at')
                ->exists();

            if ($alreadyBooked) {
                return null;
            }

            return MentorSession::create([
                'mentor_id' => $mentor->id,
                'member_id' => $member->id,
                'scheduled_at' => $scheduledAt,
            ]);
        });
    }
}
