<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

trait ComputesUserInitials
{
    #[Computed]
    public function userInitials(): string
    {
        return Auth::user()->initials;
    }
}
