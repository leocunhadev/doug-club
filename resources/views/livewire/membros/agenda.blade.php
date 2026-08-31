<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Minha sessão
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                Escolha um horário dentro do que o Douglas deixou disponível. Sessão 1:1 de 90 minutos.
            </p>
        </div>

        @if ($this->upcomingSession)
            <div class="max-w-md rounded-[18px] border border-sand bg-card p-6">
                <p class="text-xs font-bold uppercase tracking-widest text-stone mb-1">Sua próxima sessão</p>
                <p class="font-display text-lg">{{ $this->upcomingSession->scheduled_at->format('d/m/Y \à\s H:i') }}</p>
                <p class="text-sm text-stone mt-1">Sessão 1:1 · 90 minutos</p>

                @php
                    $cancellable = $this->upcomingSession->scheduled_at->gte(now()->addHours(24));
                @endphp

                @if ($cancellable)
                    <button type="button" wire:click="cancelSession"
                            class="mt-4 px-4 py-2 rounded-full text-sm font-semibold border border-sand text-ink hover:border-black">
                        Cancelar sessão
                    </button>
                @else
                    <p class="mt-4 text-xs text-stone">
                        Faltam menos de 24h — não é mais possível cancelar por aqui.
                    </p>
                    <span class="mt-1 inline-block text-sm font-semibold text-stone">Cancelar sessão</span>
                @endif
            </div>
        @elseif ($this->mentor)
            @if ($this->availableSlots->isEmpty())
                <p class="text-stone">Nenhum horário disponível no momento.</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @php
                        $slotsByDay = $this->availableSlots->groupBy(fn ($slot) => $slot->format('Y-m-d'));
                    @endphp

                    @for ($i = 0; $i < 14; $i++)
                        @php
                            $date = today()->addDays($i);
                            $key = $date->format('Y-m-d');
                            $hasSlots = $slotsByDay->has($key);
                        @endphp

                        @if ($hasSlots)
                            <button type="button" wire:click="selectDate('{{ $key }}')"
                                    class="flex flex-col items-center px-3 py-2 rounded-xl border text-sm {{ $selectedDate === $key ? 'bg-black text-white border-black' : 'bg-card text-ink border-sand hover:border-black' }}">
                                <small class="uppercase text-xs">{{ $date->translatedFormat('D') }}</small>
                                <b>{{ $date->format('d') }}</b>
                            </button>
                        @else
                            <span class="flex flex-col items-center px-3 py-2 rounded-xl border border-sand text-sm text-stone/50 cursor-not-allowed">
                                <small class="uppercase text-xs">{{ $date->translatedFormat('D') }}</small>
                                <b>{{ $date->format('d') }}</b>
                            </span>
                        @endif
                    @endfor
                </div>

                @if ($selectedDate && $slotsByDay->has($selectedDate))
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($slotsByDay->get($selectedDate) as $slot)
                            <button type="button" wire:click="selectSlot('{{ $slot->toIso8601String() }}')"
                                    class="px-3.5 py-1.5 rounded-full text-sm font-medium border {{ $selectedSlot === $slot->toIso8601String() ? 'bg-black text-white border-black' : 'border-sand bg-card text-ink hover:border-black' }}">
                                {{ $slot->format('H:i') }}
                            </button>
                        @endforeach
                    </div>
                @endif

                @if ($selectedSlot)
                    @php $selectedSlotAt = \Carbon\Carbon::parse($selectedSlot); @endphp
                    <div class="mt-4 max-w-md rounded-[18px] border border-sand bg-card p-6">
                        <p class="text-xs font-bold uppercase tracking-widest text-stone mb-1">Confirmar sessão</p>
                        <p class="font-display text-lg">{{ $selectedSlotAt->format('d/m/Y \à\s H:i') }}</p>
                        <p class="text-sm text-stone mt-1">Sessão 1:1 · 90 minutos</p>

                        <div class="mt-4 flex items-center gap-3">
                            <button type="button" wire:click="confirmBooking"
                                    class="px-4 py-2 rounded-full text-sm font-semibold bg-black text-white hover:bg-brand">
                                Confirmar sessão
                            </button>
                            <button type="button" wire:click="clearSelection"
                                    class="text-sm text-stone hover:text-ink underline">
                                Trocar horário
                            </button>
                        </div>
                    </div>
                @endif
            @endif
        @else
            <p class="text-stone">Nenhum mentor disponível no momento.</p>
        @endif
    </div>

    <x-membros.footer />
</div>
