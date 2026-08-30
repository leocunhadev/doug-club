<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\EncontroFeedback;
use App\Models\LessonFeedback;
use App\Models\MentorCommitment;
use App\Models\MentorNote;
use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Radar extends Component
{
    use ComputesUserInitials;

    private const NO_SESSION_ALERT_THRESHOLD_DAYS = 30;

    private const NPS_WINDOW_DAYS = 30;

    #[Computed]
    public function todaySessions(): Collection
    {
        return MentorSession::query()
            ->whereDate('scheduled_at', now()->toDateString())
            ->whereNull('cancelled_at')
            ->orderBy('scheduled_at')
            ->with('member')
            ->get();
    }

    #[Computed]
    public function averageNpsScore(): ?float
    {
        $since = now()->subDays(self::NPS_WINDOW_DAYS);

        $scores = LessonFeedback::query()->where('created_at', '>=', $since)->pluck('score')
            ->merge(EncontroFeedback::query()->where('created_at', '>=', $since)->pluck('score'));

        return $scores->isEmpty() ? null : round((float) $scores->avg(), 1);
    }

    #[Computed]
    public function overdueMembers(): Collection
    {
        return User::query()
            ->where('tier', 'club')
            ->get()
            ->map(function (User $member) {
                $lastSession = MentorSession::query()
                    ->where('member_id', $member->id)
                    ->whereNull('cancelled_at')
                    ->where('scheduled_at', '<', now())
                    ->orderByDesc('scheduled_at')
                    ->first();

                $reference = $lastSession?->scheduled_at ?? $member->created_at;

                return [
                    'member' => $member,
                    'days_since' => (int) $reference->diffInDays(now()),
                ];
            })
            ->filter(fn (array $entry) => $entry['days_since'] > self::NO_SESSION_ALERT_THRESHOLD_DAYS)
            ->sortByDesc('days_since')
            ->values();
    }

    public function lastNoteFor(int $memberId): ?MentorNote
    {
        return MentorNote::query()
            ->where('member_id', $memberId)
            ->latest()
            ->first();
    }

    public function activeCommitmentFor(int $memberId): ?MentorCommitment
    {
        return MentorCommitment::query()
            ->where('member_id', $memberId)
            ->first();
    }

    public function render()
    {
        return view('livewire.membros.radar');
    }
}
