<div class="min-h-screen text-white">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-16 sm:space-y-20">
        <section>
            <h1 class="text-2xl font-bold">Sua central de conteúdos</h1>
            <p class="mt-1 max-w-2xl text-gray-400">
                Acompanhe as transmissões ao vivo e os conteúdos gravados de Douglas Oliveira. Tudo em um lugar só,
                exclusivo para quem decidiu agir.
            </p>

            @if ($lesson = $this->featuredLesson)
                <div class="mt-6 rounded-2xl border border-slate-800/60 bg-surface p-3 sm:p-4">
                    <div class="relative aspect-video overflow-hidden rounded-xl">
                        <iframe
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
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-surface border border-slate-800/60 text-gray-200 hover:bg-slate-800/60">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current">
                                    <path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/>
                                </svg>
                                Materiais de aula
                            </button>

                            <div x-show="open" x-cloak x-transition
                                 class="absolute left-0 z-10 mt-2 min-w-[14rem] rounded-lg border border-slate-800/60 bg-surface py-1 shadow-lg">
                                @foreach ($lesson->materials as $material)
                                    @if ($material->hasUploadedFile())
                                        <a href="{{ route('membros.materials.download', $material) }}"
                                           class="block px-4 py-2 text-sm text-gray-200 hover:bg-slate-800/60">
                                            {{ $material->title }}
                                        </a>
                                    @else
                                        <a href="{{ $material->file_url }}" target="_blank" rel="noopener"
                                           class="block px-4 py-2 text-sm text-gray-200 hover:bg-slate-800/60">
                                            {{ $material->title }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @else
                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-surface border border-slate-800/60 text-gray-500 cursor-not-allowed">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current">
                                <path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/>
                            </svg>
                            Materiais de aula
                        </span>
                    @endif
                </div>
            @else
                <p class="mt-6 text-gray-400">Nenhuma aula disponível ainda.</p>
            @endif
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
                        <h2 class="text-lg font-semibold">
                            {{ $course->label }}@if($course->title): {{ $course->title }}@endif
                        </h2>
                        @if ($course->description)
                            <p class="mt-2 text-sm text-gray-400">{{ $course->description }}</p>
                        @endif
                    </div>

                    <div class="relative">
                        <div x-ref="track" @scroll.debounce.100ms="update()" class="mt-4 flex gap-4 overflow-x-auto pb-2 scroll-smooth snap-x">
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
                                class="hidden md:flex absolute left-2 top-1/2 -translate-y-1/2 h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-orange-500 to-red-600 text-white shadow-lg hover:brightness-110">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5 fill-current">
                                <path d="M14.71 6.71a1 1 0 0 1 0 1.42L10.41 12l4.3 4.29a1 1 0 0 1-1.42 1.42l-5-5a1 1 0 0 1 0-1.42l5-5a1 1 0 0 1 1.42 0Z"/>
                            </svg>
                        </button>

                        <button type="button" x-show="canScrollRight" x-cloak
                                @click="$refs.track.scrollBy({ left: 300, behavior: 'smooth' })"
                                class="hidden md:flex absolute right-2 top-1/2 -translate-y-1/2 h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-orange-500 to-red-600 text-white shadow-lg hover:brightness-110">
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
