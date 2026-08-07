@props(['lesson', 'course', 'watching' => false])

<button
    type="button"
    wire:click="watchLesson({{ $lesson->id }})"
    {{ $attributes->class(['group relative shrink-0 w-64 text-left rounded-xl overflow-hidden bg-[#12141a] border border-slate-800/60 transition hover:scale-[1.02] hover:brightness-110']) }}
>
    <div class="relative aspect-video bg-[radial-gradient(circle_at_top_left,rgba(249,115,22,0.35),transparent_60%)]">
        <div class="absolute top-2 left-2 right-2 flex items-start justify-between gap-2">
            <span class="min-w-0">
                <x-application-logo class="h-4 w-auto fill-current text-orange-500" />
                <span class="mt-1 block truncate text-[10px] font-semibold uppercase tracking-widest text-orange-400">
                    Curso
                </span>
                <span class="block truncate text-xs font-semibold uppercase tracking-wide text-white">
                    {{ $course->title ?: $course->label }}
                </span>
            </span>

            @if ($watching)
                <span class="shrink-0 text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full bg-gradient-to-r from-orange-500 to-red-600 text-white">
                    Assistindo
                </span>
            @endif
        </div>

        <span class="absolute inset-x-3 bottom-3 flex items-baseline gap-1.5">
            <span class="text-xs font-semibold uppercase tracking-widest text-white/70">Aula</span>
            <span class="text-3xl font-extrabold text-orange-500">{{ sprintf('%02d', $lesson->number) }}</span>
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
