<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\ClubApplication;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Upgrade extends Component
{
    use ComputesUserInitials;

    #[Computed]
    public function hasApplied(): bool
    {
        return ClubApplication::query()
            ->where('user_id', Auth::id())
            ->exists();
    }

    public function apply(): void
    {
        if ($this->hasApplied) {
            return;
        }

        ClubApplication::create(['user_id' => Auth::id()]);

        unset($this->hasApplied);
    }

    public function render()
    {
        return view('livewire.membros.upgrade');
    }
}
