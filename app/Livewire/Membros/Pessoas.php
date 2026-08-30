<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\BridgeRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Pessoas extends Component
{
    use ComputesUserInitials;

    #[Computed]
    public function members()
    {
        return User::query()
            ->where('tier', 'club')
            ->where('id', '!=', Auth::id())
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function requestedTargetIds(): array
    {
        return BridgeRequest::query()
            ->where('requester_id', Auth::id())
            ->pluck('target_id')
            ->all();
    }

    public function requestBridge(int $targetId): void
    {
        if ($targetId === Auth::id()) {
            return;
        }

        $isClubMember = User::query()
            ->where('id', $targetId)
            ->where('tier', 'club')
            ->exists();

        if (! $isClubMember) {
            return;
        }

        if (in_array($targetId, $this->requestedTargetIds, true)) {
            return;
        }

        BridgeRequest::firstOrCreate([
            'requester_id' => Auth::id(),
            'target_id' => $targetId,
        ]);

        unset($this->requestedTargetIds);
    }

    public function render()
    {
        return view('livewire.membros.pessoas');
    }
}
