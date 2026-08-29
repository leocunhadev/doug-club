<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Biblioteca de aulas
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                Todos os encontros gravados, aulas de convidados e frameworks em vídeo. Aperte o play e continue de onde parou.
            </p>
        </div>

        <x-lesson-player :lesson="$this->featuredLesson" :progress="$this->featuredProgress" :has-feedback="$this->featuredHasFeedback" />

        <p class="mt-4 text-sm text-stone">
            Você está assistindo agora: <b class="font-semibold text-ink">{{ $this->featuredLesson && $this->featuredLesson->isAvailableFor(auth()->user()) ? $this->featuredLesson->title : '—' }}</b>
            · {{ $this->totalCount }} {{ Str::plural('aula', $this->totalCount) }} na sua biblioteca
        </p>

        <div class="mt-6 flex flex-wrap gap-2">
            @foreach (['Tudo', 'Encontros', 'Convidados', 'Frameworks'] as $cat)
                <button type="button" wire:click="selectCategory('{{ $cat }}')"
                        class="px-3.5 py-1.5 rounded-full text-sm font-medium border {{ $category === $cat ? 'bg-black text-white border-black' : 'bg-card text-stone border-sand hover:text-ink' }}">
                    {{ $cat }}
                </button>
            @endforeach
        </div>

        <div class="mt-6 grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-4">
            @forelse ($this->lessons as $lesson)
                <x-aula-card :lesson="$lesson" :watching="$this->featuredLessonId === $lesson->id" />
            @empty
                <p class="col-span-full text-stone">Nenhuma aula nesta categoria ainda.</p>
            @endforelse
        </div>
    </div>

    <x-membros.footer />
</div>
