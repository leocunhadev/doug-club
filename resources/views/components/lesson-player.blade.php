@props(['lesson', 'progress', 'hasFeedback' => false])

@if ($lesson && $lesson->isAvailableFor(auth()->user()))
    <div
        wire:key="hero-player-{{ $lesson->id }}"
        x-data="vimeoProgress({
            lessonId: {{ $lesson->id }},
            provider: '{{ $lesson->video_provider }}',
            initialSeconds: {{ $progress?->watched_seconds ?? 0 }},
            hasFeedback: {{ $hasFeedback ? 'true' : 'false' }},
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
            <span
                x-data="lessonWatermark()"
                :style="`top:${top}%;left:${left}%`"
                class="pointer-events-none absolute select-none whitespace-nowrap text-xs font-medium text-white/40 [text-shadow:0_1px_2px_rgba(0,0,0,.6)]"
            >{{ auth()->user()->email }}</span>
        </div>
    </div>

    <div class="mt-4">
        <a href="{{ route('membros.aulas.materiais', $lesson) }}" wire:navigate
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-card border border-sand text-ink hover:bg-paper">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current">
                <path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/>
            </svg>
            Materiais de aula
        </a>
    </div>
@else
    <p class="mt-6 text-stone">Nenhuma aula disponível ainda.</p>
@endif
