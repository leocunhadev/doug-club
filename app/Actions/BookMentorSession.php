<?php

namespace App\Actions;

use App\Jobs\SendSessionReminderJob;
use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\MentorSessionBookedForMentorNotification;
use App\Notifications\MentorSessionBookedNotification;
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

            $session = MentorSession::create([
                'mentor_id' => $mentor->id,
                'member_id' => $member->id,
                'scheduled_at' => $scheduledAt,
            ]);

            $session->member->notify(new MentorSessionBookedNotification($session));
            $session->mentor->notify(new MentorSessionBookedForMentorNotification($session));

            SendSessionReminderJob::dispatch($session)->delay($scheduledAt->copy()->subHour());

            return $session;
        });
    }
}
