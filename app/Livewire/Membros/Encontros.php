<?php

namespace App\Livewire\Membros;

use App\Actions\SubmitEncontroNpsScore;
use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\Encontro;
use App\Models\EncontroFeedback;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Encontros extends Component
{
    use ComputesUserInitials;

    #[Computed]
    public function encontros()
    {
        $upcoming = Encontro::query()->with('lesson')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->get();

        $past = Encontro::query()->with('lesson')
            ->where('scheduled_at', '<', now())
            ->orderByDesc('scheduled_at')
            ->get();

        return $upcoming->concat($past);
    }

    #[Computed]
    public function ratedEncontroIds(): array
    {
        return EncontroFeedback::query()
            ->where('user_id', Auth::id())
            ->pluck('encontro_id')
            ->all();
    }

    public function submitEncontroNpsScore(int $encontroId, int $score, SubmitEncontroNpsScore $action): void
    {
        if (! Encontro::query()->whereKey($encontroId)->exists()) {
            return;
        }

        $action->handle(Auth::id(), $encontroId, $score);
    }

    public function render()
    {
        return view('livewire.membros.encontros');
    }
}
