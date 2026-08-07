<?php

namespace App\Livewire\Membros;

use App\Actions\MarkLessonAsWatching;
use App\Models\Course;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function watchLesson(int $lessonId, MarkLessonAsWatching $action): void
    {
        $action->handle(Auth::id(), $lessonId);

        $this->dispatch('lesson-watched', lessonId: $lessonId);
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
