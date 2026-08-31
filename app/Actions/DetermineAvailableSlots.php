<?php

namespace App\Actions;

use App\Models\MentorAvailability;
use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Support\Collection;

class DetermineAvailableSlots
{
    private const SESSION_MINUTES = 90;
    private const BOOKING_WINDOW_DAYS = 14;
    private const MIN_NOTICE_HOURS = 24;

    /** @return Collection<int, \Carbon\Carbon> */
    public function handle(User $mentor): Collection
    {
        $availabilities = MentorAvailability::query()->where('mentor_id', $mentor->id)->where('active', true)->get();

        $bookedSlots = MentorSession::query()
            ->where('mentor_id', $mentor->id)
            ->whereNull('cancelled_at')
            ->where('scheduled_at', '>=', now())
            ->pluck('scheduled_at')
            ->map(fn ($dt) => $dt->format('Y-m-d H:i:s'))
            ->all();

        $earliestBookable = now()->addHours(self::MIN_NOTICE_HOURS);
        $slots = collect();

        for ($day = 0; $day < self::BOOKING_WINDOW_DAYS; $day++) {
            $date = today()->addDays($day);

            foreach ($availabilities->where('day_of_week', $date->dayOfWeek) as $availability) {
                $slotStart = $date->copy()->setTimeFromTimeString($availability->start_time->format('H:i'));
                $blockEnd = $date->copy()->setTimeFromTimeString($availability->end_time->format('H:i'));

                while ($slotStart->copy()->addMinutes(self::SESSION_MINUTES)->lte($blockEnd)) {
                    if ($slotStart->gte($earliestBookable)
                        && ! in_array($slotStart->format('Y-m-d H:i:s'), $bookedSlots, true)) {
                        $slots->push($slotStart->copy());
                    }

                    $slotStart->addMinutes(self::SESSION_MINUTES);
                }
            }
        }

        return $slots->sortBy(fn ($slot) => $slot->timestamp)->values();
    }
}
