<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Livewire\Concerns\TracksLessonProgress;
use App\Models\Lesson;
use App\Models\MentorNote;
use App\Models\MentorSession;
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
     * @return array{title: string, subtitle: string, ctaLabel: string, ctaRoute: string}|null
     */
    #[Computed]
    public function nextSessionCard(): ?array
    {
        $user = Auth::user();

        if (! $user->hasClubAccess()) {
            return null;
        }

        $query = MentorSession::query()
            ->whereNull('cancelled_at')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at');

        $session = $user->isMentor()
            ? $query->where('mentor_id', $user->id)->with('member')->first()
            : $query->where('member_id', $user->id)->first();

        if ($session) {
            return [
                'title' => $session->scheduled_at->format('d/m/Y \à\s H:i'),
                'subtitle' => $user->isMentor()
                    ? "Sessão 1:1 com {$session->member->name}."
                    : 'Sessão 1:1 · 90 minutos.',
                'ctaLabel' => $user->isMentor() ? 'Ver no Radar' : 'Ver minha agenda',
                'ctaRoute' => $user->isMentor() ? 'mentor.radar' : 'membros.agenda',
            ];
        }

        return [
            'title' => $user->isMentor() ? 'Nenhuma sessão marcada' : 'Marque sua sessão',
            'subtitle' => $user->isMentor()
                ? 'Nenhum membro marcou uma sessão com você ainda.'
                : 'Escolha um horário disponível na agenda do Douglas.',
            'ctaLabel' => $user->isMentor() ? 'Configurar disponibilidade' : 'Marcar sessão',
            'ctaRoute' => $user->isMentor() ? 'mentor.disp' : 'membros.agenda',
        ];
    }

    #[Computed]
    public function latestMentorNote(): ?MentorNote
    {
        $user = Auth::user();

        if ($user->tier !== 'club') {
            return null;
        }

        return MentorNote::query()
            ->where('member_id', $user->id)
            ->latest()
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
