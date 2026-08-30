<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\MentorCommitment;
use App\Models\MentorNote;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Dossies extends Component
{
    use ComputesUserInitials;

    public ?int $selectedMemberId = null;

    public string $noteTitle = '';

    public string $noteBody = '';

    public string $commitmentInput = '';

    public function mount(): void
    {
        $this->selectedMemberId = $this->members->first()?->id;
        $this->loadCommitmentInput();
    }

    #[Computed]
    public function members()
    {
        return User::query()
            ->where('tier', 'club')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedMember(): ?User
    {
        return $this->members->firstWhere('id', $this->selectedMemberId);
    }

    #[Computed]
    public function notes()
    {
        return MentorNote::query()
            ->where('member_id', $this->selectedMemberId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function selectMember(int $memberId): void
    {
        if (! $this->members->contains('id', $memberId)) {
            return;
        }

        $this->selectedMemberId = $memberId;
        $this->reset('noteTitle', 'noteBody');
        $this->loadCommitmentInput();
    }

    public function addNote(): void
    {
        $this->validate([
            'noteTitle' => ['required', 'string', 'max:255'],
            'noteBody' => ['required', 'string', 'max:2000'],
        ]);

        MentorNote::create([
            'member_id' => $this->selectedMemberId,
            'mentor_id' => Auth::id(),
            'title' => $this->noteTitle,
            'body' => $this->noteBody,
        ]);

        $this->reset('noteTitle', 'noteBody');
    }

    public function saveCommitment(): void
    {
        $this->validate([
            'commitmentInput' => ['nullable', 'string', 'max:500'],
        ]);

        MentorCommitment::updateOrCreate(
            ['member_id' => $this->selectedMemberId],
            ['text' => trim($this->commitmentInput) ?: null],
        );
    }

    private function loadCommitmentInput(): void
    {
        $this->commitmentInput = MentorCommitment::query()
            ->where('member_id', $this->selectedMemberId)
            ->value('text') ?? '';
    }

    public function render()
    {
        return view('livewire.membros.dossies');
    }
}
