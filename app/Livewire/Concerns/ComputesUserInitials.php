<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

trait ComputesUserInitials
{
    #[Computed]
    public function userInitials(): string
    {
        $initials = collect(explode(' ', Auth::user()->name))
            ->filter()
            ->map(fn (string $part) => mb_substr($part, 0, 1))
            ->take(2)
            ->implode('');

        return mb_strtoupper($initials);
    }
}
