<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Dossiês
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                A memória viva de cada mentorado. O fio laranja é a história de vocês dois.
            </p>
        </div>

        @if ($this->members->isEmpty())
            <p class="text-stone">Nenhum mentorado ainda.</p>
        @else
            <div class="dossie-wrap">
                <div class="dlist rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)]">
                    @foreach ($this->members as $member)
                        <button type="button" wire:click="selectMember({{ $member->id }})"
                                class="{{ $member->id === $this->selectedMemberId ? 'on' : '' }}">
                            @if ($member->photo_url)
                                <img src="{{ $member->photo_url }}" alt="{{ $member->name }}"
                                     class="avatar {{ $member->id === $this->selectedMemberId ? 'o' : '' }}"
                                     style="width:38px;height:38px;font-size:13px">
                            @else
                                <div class="avatar {{ $member->id === $this->selectedMemberId ? 'o' : '' }}"
                                     style="width:38px;height:38px;font-size:13px">{{ $member->initials }}</div>
                            @endif
                            <div class="d">
                                <b>{{ $member->name }}</b>
                                <small>{{ $member->company ?: '—' }}</small>
                            </div>
                        </button>
                    @endforeach
                </div>

                <div class="dossie rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)]">
                    <div class="head">
                        @if ($this->selectedMember->photo_url)
                            <img src="{{ $this->selectedMember->photo_url }}" alt="{{ $this->selectedMember->name }}"
                                 class="avatar o" style="width:54px;height:54px;font-size:18px">
                        @else
                            <div class="avatar o" style="width:54px;height:54px;font-size:18px">{{ $this->selectedMember->initials }}</div>
                        @endif
                        <div>
                            <h3>{{ $this->selectedMember->name }}</h3>
                            <small>{{ $this->selectedMember->company ?: '—' }}</small>
                        </div>
                    </div>

                    <div class="compromisso">
                        <b>Compromisso ativo:</b>
                        <div class="mt-3 flex gap-2">
                            <input type="text" wire:model="commitmentInput" class="inp" placeholder="Sem compromisso ativo.">
                            <button type="button" wire:click="saveCommitment"
                                    class="shrink-0 rounded-full bg-brand text-white text-xs font-semibold px-4 py-2 hover:brightness-110">
                                Salvar
                            </button>
                        </div>
                    </div>

                    <p class="eyebrow laranja">O fio da mentoria</p>
                    <div class="fio">
                        @forelse ($this->notes as $note)
                            <div class="no">
                                <small>{{ $note->created_at->format('d/m/Y') }}</small>
                                <b>{{ $note->title }}</b>
                                <p>{{ $note->body }}</p>
                            </div>
                        @empty
                            <p class="text-stone">Nenhuma nota ainda.</p>
                        @endforelse
                    </div>

                    <form wire:submit="addNote" class="nota-add">
                        <input type="text" wire:model="noteTitle" class="inp" placeholder="Título curto da nota...">
                        @error('noteTitle') <p class="text-xs text-brand">{{ $message }}</p> @enderror
                        <input type="text" wire:model="noteBody"
                               class="inp" placeholder="Anotar algo sobre {{ explode(' ', $this->selectedMember->name)[0] }}...">
                        @error('noteBody') <p class="text-xs text-brand">{{ $message }}</p> @enderror
                        <button type="submit"
                                class="self-start rounded-full bg-black text-white text-xs font-semibold px-4 py-2 hover:brightness-110">
                            Guardar
                        </button>
                    </form>

                    <form wire:submit="sendToVault" class="nota-add">
                        <input type="text" wire:model="docTitle" class="inp" placeholder="Título do documento...">
                        @error('docTitle') <p class="text-xs text-brand">{{ $message }}</p> @enderror
                        <input type="url" wire:model="docUrl"
                               class="inp" placeholder="Enviar insight ou documento para o cofre de {{ explode(' ', $this->selectedMember->name)[0] }}...">
                        @error('docUrl') <p class="text-xs text-brand">{{ $message }}</p> @enderror
                        <button type="submit"
                                class="self-start rounded-full bg-brand text-white text-xs font-semibold px-4 py-2 hover:brightness-110">
                            Enviar ao cofre
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>

    <x-membros.footer />
</div>
