<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\BridgeRequest;
use App\Models\EncontroFeedback;
use App\Models\FrameworkDownload;
use App\Models\Lesson;
use App\Models\LessonFeedback;
use App\Models\LessonProgress;
use App\Models\MentorCommitment;
use App\Models\MentorNote;
use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\BridgeSuggestedNotification;
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

    #[Computed]
    public function suggestedBridges(): Collection
    {
        $members = User::query()
            ->where('tier', 'club')
            ->get(['id', 'name', 'teach_tags', 'learn_tags']);

        $connectedPairs = BridgeRequest::query()
            ->get(['requester_id', 'target_id'])
            ->flatMap(fn (BridgeRequest $br) => ["{$br->requester_id}-{$br->target_id}", "{$br->target_id}-{$br->requester_id}"])
            ->flip();

        $matches = collect();

        foreach ($members as $learner) {
            foreach ($members as $teacher) {
                if ($learner->id === $teacher->id) {
                    continue;
                }

                if (isset($connectedPairs["{$learner->id}-{$teacher->id}"])) {
                    continue;
                }

                $matchedTag = collect($learner->learn_tags ?? [])
                    ->first(fn (string $tag) => collect($teacher->teach_tags ?? [])
                        ->contains(fn (string $t) => mb_strtolower($t) === mb_strtolower($tag)));

                if ($matchedTag !== null) {
                    $matches->push([
                        'learner' => $learner,
                        'teacher' => $teacher,
                        'tag' => $matchedTag,
                    ]);
                }
            }
        }

        return $matches->take(3);
    }

    public function makeBridge(int $learnerId, int $teacherId, string $tag): void
    {
        $learner = User::query()->where('id', $learnerId)->where('tier', 'club')->first();
        $teacher = User::query()->where('id', $teacherId)->where('tier', 'club')->first();

        if (! $learner || ! $teacher) {
            return;
        }

        $alreadyConnected = BridgeRequest::query()
            ->where(fn ($q) => $q->where('requester_id', $learnerId)->where('target_id', $teacherId))
            ->orWhere(fn ($q) => $q->where('requester_id', $teacherId)->where('target_id', $learnerId))
            ->exists();

        if ($alreadyConnected) {
            return;
        }

        BridgeRequest::create(['requester_id' => $learnerId, 'target_id' => $teacherId]);

        $learner->notify(new BridgeSuggestedNotification($teacher, $tag, iAmTheLearner: true));
        $teacher->notify(new BridgeSuggestedNotification($learner, $tag, iAmTheLearner: false));

        $this->dispatch('toast', message: "Apresentação enviada para {$learner->name} e {$teacher->name}.");

        unset($this->suggestedBridges);
    }

    #[Computed]
    public function engagedStartMembers(): Collection
    {
        $startLessonIds = Lesson::query()
            ->where('tier', 'start')
            ->whereNotNull('published_at')
            ->pluck('id');

        if ($startLessonIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->where('tier', 'start')
            ->get()
            ->filter(function (User $member) use ($startLessonIds) {
                $completedCount = LessonProgress::query()
                    ->where('user_id', $member->id)
                    ->where('status', 'completed')
                    ->whereIn('lesson_id', $startLessonIds)
                    ->count();

                if ($completedCount < $startLessonIds->count()) {
                    return false;
                }

                $distinctFrameworksDownloaded = FrameworkDownload::query()
                    ->where('user_id', $member->id)
                    ->distinct('framework_id')
                    ->count('framework_id');

                return $distinctFrameworksDownloaded >= 2;
            })
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
