<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    @if ($this->totalCount > 0 || $this->canSeeEmptyCatalog)
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <div class="mb-8">
                <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                    Biblioteca de aulas
                </h1>
                <p class="mt-2 max-w-xl text-stone">
                    Todos os encontros gravados, aulas de convidados e frameworks em vídeo. Aperte o play e continue de onde parou.
                </p>
            </div>

            <x-lesson-player :lesson="$this->featuredLesson" :progress="$this->featuredProgress" :has-feedback="$this->featuredHasFeedback" :total-count="$this->totalCount" />
            <x-nps-modal />

            <div class="mt-6 flex flex-wrap items-center gap-2">
                @foreach (['Tudo', 'Encontros', 'Convidados', 'Frameworks'] as $cat)
                    <button type="button" wire:click="selectCategory('{{ $cat }}')"
                            class="px-3.5 py-1.5 rounded-full text-sm font-medium border {{ $category === $cat ? 'bg-black text-white border-black' : 'bg-card text-stone border-sand hover:text-ink' }}">
                        {{ $cat }}
                    </button>
                @endforeach

                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar aula..."
                       class="ms-auto px-3.5 py-1.5 rounded-full text-sm border border-sand bg-card text-ink placeholder:text-stone focus:outline-none focus:border-black">
            </div>

            <div class="mt-6 grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-4">
                @forelse ($this->lessons as $lesson)
                    <x-aula-card :lesson="$lesson" :watching="$this->featuredLessonId === $lesson->id" />
                @empty
                    @if ($search !== '')
                        <p class="col-span-full text-stone">Nenhuma aula encontrada para "{{ $search }}".</p>
                    @else
                        <p class="col-span-full text-stone">Nenhuma aula nesta categoria ainda.</p>
                    @endif
                @endforelse
            </div>
        </div>
    @else
        <x-catalog-empty-lock
            title="Sua biblioteca de aulas está sendo preparada."
            message="Em breve os primeiros conteúdos chegam por aqui." />
    @endif

    <x-membros.footer />
</div>
