<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\VaultDocument;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Cofre extends Component
{
    use ComputesUserInitials;

    #[Computed]
    public function documents()
    {
        return VaultDocument::query()
            ->where('member_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();
    }

    public function render()
    {
        return view('livewire.membros.cofre');
    }
}
