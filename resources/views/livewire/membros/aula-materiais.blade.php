<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    @if ($this->catalogIsEmpty && ! $this->canSeeEmptyCatalog)
        <x-catalog-empty-lock
            title="Os materiais de aula estão sendo preparados."
            message="Em breve o Douglas vai adicionar os primeiros arquivos por aqui." />
    @else
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <a href="{{ route('membros.aulas') }}" wire:navigate class="text-sm text-stone hover:text-ink">
                ← Voltar pra Aulas
            </a>

            <div class="mt-4 mb-8">
                <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                    Materiais · {{ $this->lesson->title }}
                </h1>
            </div>

            <div class="rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)] overflow-hidden">
                @forelse ($this->materials as $material)
                    <div class="doc-row">
                        <div class="doc-ic">{{ $material->icon_label }}</div>
                        <div class="flex-1 min-w-0">
                            <b class="block text-sm">{{ $material->title }}</b>
                        </div>
                        @if ($material->hasUploadedFile())
                            <a href="{{ route('membros.materials.download', $material) }}"
                               class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold border border-sand text-ink hover:border-black">
                                Baixar
                            </a>
                        @else
                            <a href="{{ $material->file_url }}" target="_blank" rel="noopener"
                               class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold border border-sand text-ink hover:border-black">
                                Abrir
                            </a>
                        @endif
                    </div>
                @empty
                    <p class="text-stone p-6">Nenhum material para esta aula ainda.</p>
                @endforelse
            </div>
        </div>
    @endif

    <x-membros.footer />
</div>
