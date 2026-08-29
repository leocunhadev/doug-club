<?php

namespace App\Livewire\Membros;

use App\Actions\DetermineFeaturedLesson;
use App\Actions\MarkLessonAsCompleted;
use App\Actions\MarkLessonAsWatching;
use App\Actions\UpdateLessonWatchedSeconds;
use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Dashboard extends Component
{
    use ComputesUserInitials;

    public ?int $featuredLessonId = null;

    public function mount(DetermineFeaturedLesson $determineFeaturedLesson): void
    {
        $this->featuredLessonId = $determineFeaturedLesson->handle(Auth::id());
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

    #[Computed]
    public function featuredLesson(): ?Lesson
    {
        return Lesson::query()->with(['course', 'materials'])->find($this->featuredLessonId);
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
    public function courses()
    {
        return Course::query()
            ->with('lessons')
            ->orderByDesc('position')
            ->get();
    }

    public function render()
    {
        return view('livewire.membros.dashboard', [
            'watchingLessonId' => LessonProgress::query()
                ->where('user_id', Auth::id())
                ->where('lesson_id', $this->featuredLessonId)
                ->where('status', 'watching')
                ->exists() ? $this->featuredLessonId : null,
        ]);
    }
}
