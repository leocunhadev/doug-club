<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-16 sm:space-y-20">
        <section>
            <p class="text-xs font-bold uppercase tracking-widest text-brand mb-2.5">
                {{ auth()->user()->hasClubAccess() ? 'DO.ing Club · Mentoria' : 'DO.ing Club start · Sua base' }}
            </p>
            <h1 class="text-[clamp(34px,6vw,58px)] leading-[1.02] font-display font-extrabold tracking-[-0.015em] text-black">
                Olá, {{ auth()->user()->name }}.<br>
                @if (auth()->user()->hasClubAccess())
                    Vamos <span class="text-brand">continuar de onde paramos?</span>
                @else
                    Sua próxima <span class="text-brand">decisão</span> começa aqui.
                @endif
            </h1>
            @if (auth()->user()->hasClubAccess())
                <p class="mt-3 max-w-2xl text-stone">
                    Acompanhe as transmissões ao vivo e os conteúdos gravados de Douglas Oliveira. Tudo em um lugar só,
                    exclusivo para quem decidiu agir.
                </p>
            @else
                <p class="mt-3 max-w-2xl text-stone">
                    Os conteúdos gravados de Douglas Oliveira, organizados pra você assistir no seu ritmo.
                </p>
            @endif

            <div class="mt-8 grid grid-cols-1 min-[860px]:grid-cols-[1.5fr_0.8fr] gap-[18px]">
            <div>
            <p class="text-xs font-bold uppercase tracking-widest text-stone mb-2.5">Continuar assistindo</p>
            @if ($lesson = $this->featuredLesson)
                <div
                    wire:key="hero-player-{{ $lesson->id }}"
                    x-data="vimeoProgress({
                        lessonId: {{ $lesson->id }},
                        provider: '{{ $lesson->video_provider }}',
                        initialSeconds: {{ $this->featuredProgress?->watched_seconds ?? 0 }},
                    })"
                    class="mt-6 rounded-2xl border border-sand bg-card p-3 sm:p-4"
                >
                    <div class="relative aspect-video overflow-hidden rounded-xl">
                        <iframe
                            x-ref="iframe"
                            src="{{ $lesson->embed_url }}"
                            class="h-full w-full"
                            allow="autoplay; fullscreen; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                        <x-brand-logo icon-only class="pointer-events-none absolute top-3 right-3 h-6 w-auto drop-shadow" />
                    </div>
                </div>

                <div class="mt-4">
                    @if ($lesson->materials->isNotEmpty())
                        <div x-data="{ open: false }" class="relative inline-block">
                            <button type="button" @click="open = !open" @click.outside="open = false"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-card border border-sand text-ink hover:bg-paper">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current">
                                    <path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/>
                                </svg>
                                Materiais de aula
                            </button>

                            <div x-show="open" x-cloak x-transition
                                 class="absolute left-0 z-10 mt-2 min-w-[14rem] rounded-lg border border-sand bg-card py-1 shadow-lg">
                                @foreach ($lesson->materials as $material)
                                    @if ($material->hasUploadedFile())
                                        <a href="{{ route('membros.materials.download', $material) }}"
                                           class="block px-4 py-2 text-sm text-ink hover:bg-paper">
                                            {{ $material->title }}
                                        </a>
                                    @else
                                        <a href="{{ $material->file_url }}" target="_blank" rel="noopener"
                                           class="block px-4 py-2 text-sm text-ink hover:bg-paper">
                                            {{ $material->title }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @else
                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-card border border-sand text-stone cursor-not-allowed">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current">
                                <path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/>
                            </svg>
                            Materiais de aula
                        </span>
                    @endif
                </div>
            @else
                <p class="mt-6 text-stone">Nenhuma aula disponível ainda.</p>
            @endif
            </div>

            <div class="flex flex-col gap-4">
                <div class="rounded-2xl bg-black text-white p-6 flex flex-col gap-2.5">
                    @if (! auth()->user()->hasClubAccess())
                        @if ($newest = $this->newestLesson)
                            <p class="text-xs font-bold uppercase tracking-widest text-white/50">Novidade na biblioteca</p>
                            <p class="font-display text-xl leading-tight">{{ $newest->title }}</p>
                            <p class="text-sm text-white/70">Aula nova na sua biblioteca, já disponível para você.</p>
                            <button type="button" wire:click="watchLesson({{ $newest->id }})"
                                    class="mt-1 self-start rounded-full bg-brand px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                                Assistir agora
                            </button>
                        @endif
                    @else
                        <p class="text-xs font-bold uppercase tracking-widest text-white/50">Sua próxima sessão 1:1</p>
                        <p class="font-display text-xl leading-tight">Agenda chega em breve</p>
                        <p class="text-sm text-white/70">Em breve você vai poder marcar sua sessão com o Douglas direto por aqui.</p>
                        <span class="mt-1 self-start rounded-full bg-white/10 px-4 py-2 text-sm font-semibold text-white/50 cursor-not-allowed">
                            Em breve
                        </span>
                    @endif
                </div>

                <div class="rounded-2xl bg-card border border-sand p-5">
                    <h3 class="font-display text-sm mb-2.5">Atalhos</h3>
                    @foreach ($this->quickLinks as $link)
                        @if ($link['available'])
                            <a href="{{ route($link['route']) }}" wire:navigate
                               class="flex items-center gap-2.5 w-full text-left py-2.5 border-t border-sand first:border-t-0 text-sm font-medium text-ink">
                                {{ $link['label'] }}
                                <span class="ml-auto text-brand font-bold">→</span>
                            </a>
                        @else
                            <span class="flex items-center gap-2.5 w-full text-left py-2.5 border-t border-sand first:border-t-0 text-sm font-medium text-stone/60 cursor-not-allowed">
                                {{ $link['label'] }}
                                <span class="ml-auto text-xs">🔒</span>
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
            </div>
        </section>

        @foreach ($this->courses as $course)
            @if ($course->lessons->isNotEmpty())
                <section
                    x-data="{
                        canScrollLeft: false,
                        canScrollRight: false,
                        update() {
                            this.canScrollLeft = this.$refs.track.scrollLeft > 0;
                            this.canScrollRight = this.$refs.track.scrollLeft + this.$refs.track.clientWidth < this.$refs.track.scrollWidth - 1;
                        },
                    }"
                    x-init="update()"
                    @resize.window.debounce.100ms="update()"
                >
                    <div>
                        <h2 class="text-lg font-semibold font-display text-ink">
                            {{ $course->label }}@if($course->title): {{ $course->title }}@endif
                        </h2>
                        @if ($course->description)
                            <p class="mt-2 text-sm text-stone">{{ $course->description }}</p>
                        @endif
                    </div>

                    <div class="relative">
                        <div x-ref="track" @scroll.debounce.100ms="update()" class="mt-4 flex gap-4 overflow-x-auto scrollbar-none pb-2 scroll-smooth snap-x">
                            @foreach ($course->lessons as $courseLesson)
                                <div class="snap-start">
                                    @if ($course->lessons->count() === 1)
                                        <x-lesson-card-simple :lesson="$courseLesson" :course="$course" :watching="$watchingLessonId === $courseLesson->id" />
                                    @else
                                        <x-lesson-card :lesson="$courseLesson" :course="$course" :watching="$watchingLessonId === $courseLesson->id" />
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <button type="button" x-show="canScrollLeft" x-cloak
                                @click="$refs.track.scrollBy({ left: -300, behavior: 'smooth' })"
                                class="hidden md:flex absolute left-2 top-1/2 -translate-y-1/2 h-10 w-10 items-center justify-center rounded-full bg-brand text-white shadow-lg hover:brightness-110">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5 fill-current">
                                <path d="M14.71 6.71a1 1 0 0 1 0 1.42L10.41 12l4.3 4.29a1 1 0 0 1-1.42 1.42l-5-5a1 1 0 0 1 0-1.42l5-5a1 1 0 0 1 1.42 0Z"/>
                            </svg>
                        </button>

                        <button type="button" x-show="canScrollRight" x-cloak
                                @click="$refs.track.scrollBy({ left: 300, behavior: 'smooth' })"
                                class="hidden md:flex absolute right-2 top-1/2 -translate-y-1/2 h-10 w-10 items-center justify-center rounded-full bg-brand text-white shadow-lg hover:brightness-110">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5 fill-current">
                                <path d="M9.29 6.71a1 1 0 0 1 1.42 0l5 5a1 1 0 0 1 0 1.42l-5 5a1 1 0 0 1-1.42-1.42L13.59 12 9.29 7.71a1 1 0 0 1 0-1.42Z"/>
                            </svg>
                        </button>
                    </div>
                </section>
            @endif
        @endforeach
    </div>

    <x-membros.footer />
</div>
