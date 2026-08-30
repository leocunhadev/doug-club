<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Sua disponibilidade
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                Blocos recorrentes por dia da semana. O que estiver aqui aparece pros mentorados marcarem.
            </p>
        </div>

        <form wire:submit="addBlock" class="flex flex-wrap items-end gap-3 mb-8 max-w-xl">
            <div>
                <label class="block text-xs font-semibold text-stone mb-1">Dia da semana</label>
                <select wire:model="dayOfWeek" class="rounded-lg border border-sand bg-card px-3 py-2 text-sm">
                    <option value="0">Domingo</option>
                    <option value="1">Segunda</option>
                    <option value="2">Terça</option>
                    <option value="3">Quarta</option>
                    <option value="4">Quinta</option>
                    <option value="5">Sexta</option>
                    <option value="6">Sábado</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-stone mb-1">Início</label>
                <input type="time" wire:model="startTime" class="rounded-lg border border-sand bg-card px-3 py-2 text-sm">
                @error('startTime') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-stone mb-1">Fim</label>
                <input type="time" wire:model="endTime" class="rounded-lg border border-sand bg-card px-3 py-2 text-sm">
                @error('endTime') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="px-4 py-2 rounded-full text-sm font-semibold bg-black text-white hover:brightness-110">
                Adicionar
            </button>
        </form>

        <div class="flex flex-col gap-2 max-w-xl">
            @forelse ($this->blocks as $block)
                <div class="flex items-center justify-between px-4 py-3 rounded-xl border border-sand bg-card">
                    <span class="text-sm">
                        {{ ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][$block->day_of_week] }}
                        · {{ $block->start_time->format('H:i') }} às {{ $block->end_time->format('H:i') }}
                    </span>
                    <button type="button" wire:click="removeBlock({{ $block->id }})"
                            class="text-xs font-semibold text-stone hover:text-ink">
                        Remover
                    </button>
                </div>
            @empty
                <p class="text-stone">Nenhum bloco de disponibilidade ainda.</p>
            @endforelse
        </div>
    </div>

    <x-membros.footer />
</div>
