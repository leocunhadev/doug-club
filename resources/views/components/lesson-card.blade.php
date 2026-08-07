@props(['lesson', 'course', 'watching' => false])

<button
    type="button"
    wire:click="watchLesson({{ $lesson->id }})"
    {{ $attributes->class(['group relative shrink-0 w-64 text-left rounded-xl overflow-hidden bg-[#12141a] border border-slate-800/60 transition hover:scale-[1.02] hover:brightness-110']) }}
>
    <div class="relative aspect-video bg-gradient-to-br from-orange-500 to-red-600">
        @if ($lesson->thumbnail_url)
            <img src="{{ $lesson->thumbnail_url }}" alt="" class="absolute inset-0 h-full w-full object-cover">
        @endif
        <div class="absolute inset-0 bg-gradient-to-tr from-black/80 via-black/20 to-orange-600/40"></div>

        <span class="absolute top-2 left-2 text-[10px] font-semibold uppercase tracking-widest text-white/90 bg-black/40 rounded px-2 py-0.5">
            Curso — {{ $course->title ?: $course->label }}
        </span>

        @if ($watching)
            <span class="absolute top-2 right-2 text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full bg-gradient-to-r from-orange-500 to-red-600 text-white">
                Assistindo
            </span>
        @endif

        <span class="absolute inset-x-3 bottom-3 text-3xl font-extrabold text-white drop-shadow">
            Aula {{ $lesson->number }}
        </span>

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
