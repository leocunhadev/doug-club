<?php

namespace App\Livewire\Membros;

use App\Actions\BookMentorSession;
use App\Actions\CancelMentorSession;
use App\Actions\DetermineAvailableSlots;
use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\MentorSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Agenda extends Component
{
    use ComputesUserInitials;

    public ?string $selectedDate = null;

    #[Computed]
    public function mentor(): ?User
    {
        return User::query()->where('tier', 'mentor')->first();
    }

    #[Computed]
    public function upcomingSession(): ?MentorSession
    {
        return MentorSession::query()
            ->where('member_id', Auth::id())
            ->whereNull('cancelled_at')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->first();
    }

    #[Computed]
    public function availableSlots(): Collection
    {
        if (! $this->mentor || $this->upcomingSession) {
            return collect();
        }

        return (new DetermineAvailableSlots)->handle($this->mentor);
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
    }

    public function bookSlot(string $slot, BookMentorSession $action): void
    {
        if (! $this->mentor || $this->upcomingSession) {
            return;
        }

        $scheduledAt = Carbon::parse($slot);

        if (! $this->availableSlots->contains(fn ($s) => $s->equalTo($scheduledAt))) {
            return;
        }

        $session = $action->handle($this->mentor, Auth::user(), $scheduledAt);

        if (! $session) {
            $this->dispatch('toast', message: 'Esse horário acabou de ser preenchido. Escolha outro.');
        }

        unset($this->availableSlots, $this->upcomingSession);
    }

    public function cancelSession(CancelMentorSession $action): void
    {
        $session = $this->upcomingSession;

        if (! $session || $session->member_id !== Auth::id()) {
            return;
        }

        if ($session->scheduled_at->lt(now()->addHours(24))) {
            return;
        }

        $action->handle($session);

        unset($this->upcomingSession, $this->availableSlots);
    }

    public function render()
    {
        return view('livewire.membros.agenda');
    }
}
