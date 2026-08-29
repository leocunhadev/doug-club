@props(['lesson', 'watching' => false])

<button
    type="button"
    wire:click="watchLesson({{ $lesson->id }})"
    class="text-left overflow-hidden rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)] transition hover:-translate-y-0.5"
>
    <div class="aula-card-thumb">
        @if ($watching)
            <span class="absolute top-2.5 left-2.5 text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full bg-brand text-white">
                Assistindo
            </span>
        @endif
        <span class="aula-card-number">{{ sprintf('%02d', $lesson->number) }}</span>
        <span class="aula-card-play"></span>
        @if ($lesson->duration_formatted)
            <span class="aula-card-duration absolute bottom-2 right-2 text-xs font-medium text-white bg-black/60 rounded px-1.5 py-0.5">
                {{ $lesson->duration_formatted }}
            </span>
        @endif
    </div>
    <div class="p-3.5">
        <b class="font-display text-sm block leading-tight">{{ $lesson->title }}</b>
        <small class="mt-1 block text-xs text-stone">
            {{ $lesson->course->label }}@if ($lesson->course->title): {{ $lesson->course->title }}@endif
            @if ($lesson->tier === 'club') · Exclusivo CLUB @endif
        </small>
    </div>
</button>
