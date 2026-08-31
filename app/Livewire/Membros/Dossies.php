<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\MentorCommitment;
use App\Models\MentorNote;
use App\Models\User;
use App\Models\VaultDocument;
use App\Notifications\VaultDocumentAddedNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.membros')]
class Dossies extends Component
{
    use ComputesUserInitials;

    #[Locked]
    public ?int $selectedMemberId = null;

    public string $noteTitle = '';

    public string $noteBody = '';

    public string $commitmentInput = '';

    public string $docTitle = '';

    public string $docUrl = '';

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
        return $this->members->firstWhere('id', $this->selectedMemberId) ?? $this->members->first();
    }

    #[Computed]
    public function notes()
    {
        return MentorNote::query()
            ->where('member_id', $this->selectedMember?->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function selectMember(int $memberId): void
    {
        if (! $this->members->contains('id', $memberId)) {
            return;
        }

        $this->selectedMemberId = $memberId;
        $this->reset('noteTitle', 'noteBody', 'docTitle', 'docUrl');
        $this->loadCommitmentInput();
    }

    public function addNote(): void
    {
        if (! $this->selectedMember) {
            return;
        }

        $this->validate([
            'noteTitle' => ['required', 'string', 'max:255'],
            'noteBody' => ['required', 'string', 'max:2000'],
        ]);

        MentorNote::create([
            'member_id' => $this->selectedMember->id,
            'mentor_id' => Auth::id(),
            'title' => $this->noteTitle,
            'body' => $this->noteBody,
        ]);

        $this->reset('noteTitle', 'noteBody');
    }

    public function sendToVault(): void
    {
        if (! $this->selectedMember) {
            return;
        }

        $this->validate([
            'docTitle' => ['required', 'string', 'max:255'],
            'docUrl' => ['required', 'url', 'max:2048'],
        ]);

        VaultDocument::create([
            'member_id' => $this->selectedMember->id,
            'mentor_id' => Auth::id(),
            'title' => $this->docTitle,
            'file_url' => $this->docUrl,
        ]);

        $this->selectedMember->notify(new VaultDocumentAddedNotification($this->docTitle));

        $this->dispatch('toast', message: "Documento enviado pro cofre de {$this->selectedMember->name}.");

        $this->reset('docTitle', 'docUrl');
    }

    public function saveCommitment(): void
    {
        if (! $this->selectedMember) {
            return;
        }

        $this->validate([
            'commitmentInput' => ['nullable', 'string', 'max:500'],
        ]);

        MentorCommitment::updateOrCreate(
            ['member_id' => $this->selectedMember->id],
            ['text' => trim($this->commitmentInput) ?: null],
        );
    }

    private function loadCommitmentInput(): void
    {
        $this->commitmentInput = MentorCommitment::query()
            ->where('member_id', $this->selectedMember?->id)
            ->value('text') ?? '';
    }

    public function render()
    {
        return view('livewire.membros.dossies');
    }
}
