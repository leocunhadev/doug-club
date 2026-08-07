<div class="min-h-screen text-white">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-16 sm:space-y-20">
        <section>
            <h1 class="text-2xl font-bold">Sua central de conteúdos</h1>
            <p class="mt-1 text-gray-400">Continue de onde parou ou explore os módulos abaixo.</p>

            @if ($lesson = $this->featuredLesson)
                <div class="mt-6 rounded-xl overflow-hidden border border-slate-800/60 bg-[#12141a]">
                    <div class="aspect-video">
                        <iframe
                            src="{{ $lesson->embed_url }}"
                            class="h-full w-full"
                            allow="autoplay; fullscreen; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                    </div>

                    <div class="p-4 sm:p-6">
                        <p class="text-xs uppercase tracking-widest text-gray-400">
                            {{ $lesson->course->label }}@if($lesson->course->title): {{ $lesson->course->title }}@endif
                        </p>
                        <h2 class="mt-1 text-lg font-semibold">Aula {{ $lesson->number }} — {{ $lesson->title }}</h2>

                        @if ($lesson->materials->isNotEmpty())
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <span class="text-xs uppercase tracking-widest text-gray-400">Materiais de aula:</span>
                                @foreach ($lesson->materials as $material)
                                    <a href="{{ $material->file_url }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center px-3 py-1.5 rounded-md text-sm bg-slate-800/60 text-gray-200 hover:bg-slate-700">
                                        {{ $material->title }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
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
                >
                    <div class="flex items-end justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">
                                {{ $course->label }}@if($course->title): {{ $course->title }}@endif
                            </h2>
                            @if ($course->description)
                                <p class="mt-2 text-sm text-gray-400">{{ $course->description }}</p>
                            @endif
                        </div>

                        <div class="hidden md:flex gap-2">
                            <button type="button" x-show="canScrollLeft" @click="$refs.track.scrollBy({ left: -300, behavior: 'smooth' })"
                                    class="h-8 w-8 rounded-full border border-slate-700 text-gray-300 hover:bg-slate-800/60">‹</button>
                            <button type="button" x-show="canScrollRight" @click="$refs.track.scrollBy({ left: 300, behavior: 'smooth' })"
                                    class="h-8 w-8 rounded-full border border-slate-700 text-gray-300 hover:bg-slate-800/60">›</button>
                        </div>
                    </div>

                    <div x-ref="track" @scroll.debounce.100ms="update()" class="mt-4 flex gap-4 overflow-x-auto pb-2 scroll-smooth snap-x">
                        @foreach ($course->lessons as $courseLesson)
                            <div class="snap-start">
                                <x-lesson-card :lesson="$courseLesson" :course="$course" :watching="$watchingLessonId === $courseLesson->id" />
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>

    <footer class="border-t border-slate-800/60 mt-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-400">
            <div class="flex gap-4">
                <a href="#" class="hover:text-white">Política de Privacidade</a>
                <a href="#" class="hover:text-white">Sobre</a>
            </div>
            <p>&copy; {{ now()->year }} {{ config('app.name') }}. Todos os direitos reservados.</p>
        </div>
    </footer>

    <a href="https://wa.me/{{ config('services.whatsapp.number') }}" target="_blank" rel="noopener"
       class="fixed bottom-4 right-4 h-14 w-14 rounded-full bg-gradient-to-br from-orange-500 to-red-600 flex items-center justify-center shadow-lg hover:brightness-110">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-7 w-7 fill-white">
            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.79.47 3.47 1.29 4.93L2 22l5.28-1.38a9.9 9.9 0 0 0 4.76 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2Zm5.8 14.08c-.24.68-1.4 1.3-1.93 1.38-.5.08-1.11.11-1.79-.11-.41-.13-.94-.3-1.62-.6-2.85-1.23-4.7-4.1-4.84-4.29-.14-.19-1.16-1.54-1.16-2.94s.73-2.09 1-2.38c.24-.26.53-.32.71-.32h.5c.16 0 .38-.03.58.44.24.57.81 1.98.88 2.12.07.15.11.31.02.5-.09.19-.14.31-.28.47-.14.16-.29.36-.42.48-.14.13-.28.28-.12.55.16.27.71 1.17 1.53 1.9 1.05.93 1.94 1.22 2.21 1.36.27.13.43.11.59-.07.16-.19.68-.79.86-1.06.18-.27.36-.22.6-.13.24.09 1.55.73 1.82.86.27.14.44.2.51.32.07.13.07.72-.17 1.4Z"/>
        </svg>
    </a>
</div>
