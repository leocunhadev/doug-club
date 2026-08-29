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
use App\Support\PersonaNavigation;
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

    #[Computed]
    public function newestLesson(): ?Lesson
    {
        return Lesson::query()
            ->with('course')
            ->orderByDesc('published_at')
            ->orderByDesc('position')
            ->first();
    }

    /**
     * @return array<int, array{label: string, route: string, available: bool}>
     */
    #[Computed]
    public function quickLinks(): array
    {
        $availability = collect((new PersonaNavigation)->tabs(Auth::user()->tier))->keyBy('route');

        $thirdLink = Auth::user()->hasClubAccess()
            ? ['label' => 'Marcar minha sessão', 'route' => 'membros.agenda']
            : ['label' => 'Conhecer o CLUB', 'route' => 'membros.upgrade'];

        return collect([
            ['label' => 'Biblioteca de aulas', 'route' => 'membros.aulas'],
            ['label' => 'Frameworks DO', 'route' => 'membros.frameworks'],
            $thirdLink,
        ])->map(fn (array $link) => [
            ...$link,
            'available' => $availability->get($link['route'])['available'] ?? false,
        ])->all();
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
