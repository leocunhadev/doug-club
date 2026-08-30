<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Gente do CLUB
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                Cada pessoa aqui foi escolhida. Veja o que cada uma ensina e quer aprender, e peça a ponte. O Douglas apresenta com contexto.
            </p>
        </div>

        <div class="people">
            @foreach ($this->members as $member)
                <x-person-card :member="$member" :already-requested="in_array($member->id, $this->requestedTargetIds, true)" />
            @endforeach
        </div>
    </div>

    <x-membros.footer />
</div>
