<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Sobre extends Component
{
    use ComputesUserInitials;

    public function render()
    {
        return view('livewire.membros.sobre');
    }
}
