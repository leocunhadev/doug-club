<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Livewire\Concerns\TracksLessonProgress;
use App\Models\Lesson;
use App\Support\PersonaNavigation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Dashboard extends Component
{
    use ComputesUserInitials, TracksLessonProgress;

    #[Computed]
    public function newestLesson(): ?Lesson
    {
        return Lesson::query()
            ->with('course')
            ->where('tier', 'start')
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
        return view('livewire.membros.dashboard');
    }
}
