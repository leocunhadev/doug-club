<?php

namespace App\Livewire\Concerns;

use App\Actions\DetermineFeaturedLesson;
use App\Actions\MarkLessonAsCompleted;
use App\Actions\MarkLessonAsWatching;
use App\Actions\SubmitLessonNpsScore;
use App\Actions\UpdateLessonWatchedSeconds;
use App\Models\Lesson;
use App\Models\LessonFeedback;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

trait TracksLessonProgress
{
    public ?int $featuredLessonId = null;

    public function mount(DetermineFeaturedLesson $determineFeaturedLesson): void
    {
        $this->featuredLessonId = $determineFeaturedLesson->handle(Auth::user());
    }

    public function watchLesson(int $lessonId, MarkLessonAsWatching $action): void
    {
        $action->handle(Auth::id(), $lessonId);

        $this->featuredLessonId = $lessonId;
    }

    public function updateProgress(int $lessonId, int $seconds, UpdateLessonWatchedSeconds $action): void
    {
        $action->handle(Auth::id(), $lessonId, $seconds);
    }

    public function markCompleted(int $lessonId, MarkLessonAsCompleted $action): void
    {
        $action->handle(Auth::id(), $lessonId);
    }

    public function submitNpsScore(int $lessonId, int $score, SubmitLessonNpsScore $action): void
    {
        $lesson = Lesson::query()->find($lessonId);

        if (! $lesson || ! $lesson->isAvailableFor(Auth::user())) {
            return;
        }

        $action->handle(Auth::id(), $lessonId, $score);
    }

    #[Computed]
    public function featuredLesson(): ?Lesson
    {
        return Lesson::query()->with(['course'])->find($this->featuredLessonId);
    }

    #[Computed]
    public function featuredProgress(): ?LessonProgress
    {
        if ($this->featuredLessonId === null) {
            return null;
        }

        return LessonProgress::query()
            ->where('user_id', Auth::id())
            ->where('lesson_id', $this->featuredLessonId)
            ->first();
    }

    #[Computed]
    public function featuredHasFeedback(): bool
    {
        if ($this->featuredLessonId === null) {
            return false;
        }

        return LessonFeedback::query()
            ->where('user_id', Auth::id())
            ->where('lesson_id', $this->featuredLessonId)
            ->exists();
    }
}
