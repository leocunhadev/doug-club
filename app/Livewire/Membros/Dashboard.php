<?php

namespace App\Livewire\Membros;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public ?int $featuredLessonId = null;

    public function mount(): void
    {
        $lastWatched = LessonProgress::query()
            ->where('user_id', Auth::id())
            ->latest('last_watched_at')
            ->first();

        $this->featuredLessonId = $lastWatched?->lesson_id
            ?? Lesson::query()
                ->join('courses', 'courses.id', '=', 'lessons.course_id')
                ->orderByDesc('courses.position')
                ->orderByDesc('lessons.position')
                ->value('lessons.id');
    }

    public function watchLesson(int $lessonId): void
    {
        LessonProgress::query()->updateOrCreate(
            ['user_id' => Auth::id(), 'lesson_id' => $lessonId],
            ['status' => 'watching', 'last_watched_at' => now()],
        );

        $this->featuredLessonId = $lessonId;
    }

    #[Computed]
    public function featuredLesson(): ?Lesson
    {
        return Lesson::query()->with('materials')->find($this->featuredLessonId);
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
                ->where('status', 'watching')
                ->latest('last_watched_at')
                ->value('lesson_id'),
        ]);
    }
}
