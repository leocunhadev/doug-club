<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\Encontro;
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

    public function render()
    {
        return view('livewire.membros.encontros');
    }
}
