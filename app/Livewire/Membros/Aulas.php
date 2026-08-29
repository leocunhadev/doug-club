<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Livewire\Concerns\TracksLessonProgress;
use App\Models\Lesson;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Aulas extends Component
{
    use ComputesUserInitials, TracksLessonProgress;

    public string $category = 'Tudo';

    public function selectCategory(string $category): void
    {
        $this->category = $category;
    }

    #[Computed]
    public function lessons()
    {
        return Lesson::query()->with('course')
            ->when(! Auth::user()->hasClubAccess(), fn ($q) => $q->where('tier', 'start'))
            ->when($this->category !== 'Tudo', fn ($q) => $q->where('category', $this->category))
            ->orderByDesc('published_at')
            ->orderByDesc('position')
            ->get();
    }

    #[Computed]
    public function totalCount(): int
    {
        return Lesson::query()
            ->when(! Auth::user()->hasClubAccess(), fn ($q) => $q->where('tier', 'start'))
            ->count();
    }

    public function render()
    {
        return view('livewire.membros.aulas');
    }
}
