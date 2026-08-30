<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Meu cofre
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                Tudo que construímos juntos, sessão a sessão: insights, planos e materiais que o Douglas preparou para você. Só você e ele veem isso.
            </p>
        </div>

        <div class="cofre-note max-w-3xl">
            <span aria-hidden="true">🔒</span>
            Documentos com seu nome gravado em cada página. Este espaço é individual e intransferível.
        </div>

        <div class="max-w-3xl rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)] overflow-hidden">
            @forelse ($this->documents as $document)
                <x-vault-document-row :document="$document" />
            @empty
                <p class="text-stone p-6">Nenhum documento no seu cofre ainda.</p>
            @endforelse
        </div>
    </div>

    <x-membros.footer />
</div>
