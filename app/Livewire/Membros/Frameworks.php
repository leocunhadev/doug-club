<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesCatalogAccess;
use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\Framework;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Frameworks extends Component
{
    use ComputesUserInitials;
    use ComputesCatalogAccess;

    #[Computed]
    public function frameworks()
    {
        return Framework::query()->with('lesson')->orderByDesc('position')->get();
    }

    public function render()
    {
        return view('livewire.membros.frameworks');
    }
}
