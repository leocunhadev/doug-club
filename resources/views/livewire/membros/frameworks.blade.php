<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Frameworks DO
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                As ferramentas proprietárias do método Decisão Orientada. Cada uma tem o material para baixar e a aula que ensina a aplicar.
            </p>
        </div>

        <div class="grid grid-cols-[repeat(auto-fill,minmax(250px,1fr))] gap-4">
            @forelse ($this->frameworks as $framework)
                <x-framework-card :framework="$framework" />
            @empty
                <p class="col-span-full text-stone">Nenhum framework publicado ainda.</p>
            @endforelse
        </div>
    </div>

    <x-membros.footer />
</div>
