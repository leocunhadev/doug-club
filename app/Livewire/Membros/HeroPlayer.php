<?php

namespace App\Livewire\Membros;

use App\Actions\DetermineFeaturedLesson;
use App\Models\Lesson;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class HeroPlayer extends Component
{
    public ?int $featuredLessonId = null;

    public bool $showMaterials = false;

    public function mount(DetermineFeaturedLesson $determineFeaturedLesson): void
    {
        $this->featuredLessonId = $determineFeaturedLesson->handle(Auth::id());
    }

    #[On('lesson-watched')]
    public function refreshFeaturedLesson(int $lessonId): void
    {
        $this->featuredLessonId = $lessonId;
        $this->showMaterials = false;
    }

    public function toggleMaterials(): void
    {
        $this->showMaterials = ! $this->showMaterials;
    }

    #[Computed]
    public function featuredLesson(): ?Lesson
    {
        return Lesson::query()->with(['course', 'materials'])->find($this->featuredLessonId);
    }

    public function render()
    {
        return view('livewire.membros.hero-player');
    }
}
