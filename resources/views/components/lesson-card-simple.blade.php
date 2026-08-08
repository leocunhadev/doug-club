@props(['lesson', 'course', 'watching' => false])

<button
    type="button"
    wire:click="watchLesson({{ $lesson->id }})"
    {{ $attributes->class(['group relative shrink-0 w-64 text-left rounded-xl bg-surface ring-1 ring-inset ring-slate-800/60 transition hover:scale-[1.02] hover:ring-brand hover:brightness-110']) }}
>
    <div class="relative aspect-video overflow-hidden rounded-t-xl bg-[#1a1c23]">
        @if ($lesson->thumbnail_url)
            <img src="{{ $lesson->thumbnail_url }}" alt="" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent"></div>
        @else
            <div class="absolute inset-0 bg-brand"></div>
        @endif

        <x-brand-logo icon-only class="absolute top-2 left-2 h-4 w-auto drop-shadow" />

        @if ($watching)
            <span class="absolute top-2 right-2 text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full bg-brand text-white">
                Assistindo
            </span>
        @endif

        @if ($lesson->duration_formatted)
            <span class="absolute bottom-2 right-2 text-xs font-medium text-white bg-black/60 rounded px-1.5 py-0.5">
                {{ $lesson->duration_formatted }}
            </span>
        @endif
    </div>

    <div class="p-3">
        <p class="text-xs text-gray-400">{{ $lesson->published_at->format('d/m/Y') }}</p>
        <p class="mt-1 text-sm font-medium text-white line-clamp-2">{{ $lesson->title }}</p>
    </div>
</button>
