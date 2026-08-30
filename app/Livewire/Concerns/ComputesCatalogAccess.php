<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

trait ComputesCatalogAccess
{
    #[Computed]
    public function canSeeEmptyCatalog(): bool
    {
        return Auth::user()->is_admin || Auth::user()->isMentor();
    }
}
