<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Encontros do grupo
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                Aulas ao vivo com o Douglas e convidados. As gravações vão direto para a biblioteca.
            </p>
        </div>

        @php
            $next = $this->encontros->first(fn ($encontro) => ! $encontro->isPast());
        @endphp

        <div class="flex flex-col gap-3 max-w-3xl">
            @forelse ($this->encontros as $encontro)
                <x-encontro-card :encontro="$encontro" :is-next="$next !== null && $encontro->is($next)" />
            @empty
                <p class="text-stone">Nenhum encontro agendado ainda.</p>
            @endforelse
        </div>
    </div>

    <x-membros.footer />
</div>
