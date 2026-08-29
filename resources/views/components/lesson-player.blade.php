@props(['lesson', 'progress'])

@if ($lesson && $lesson->isAvailableFor(auth()->user()))
    <div
        wire:key="hero-player-{{ $lesson->id }}"
        x-data="vimeoProgress({
            lessonId: {{ $lesson->id }},
            provider: '{{ $lesson->video_provider }}',
            initialSeconds: {{ $progress?->watched_seconds ?? 0 }},
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
