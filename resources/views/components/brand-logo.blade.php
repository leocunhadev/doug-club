@props(['iconOnly' => false])

<span {{ $attributes->class(['inline-flex items-center gap-2']) }} aria-label="DO.ing Club">
    <svg viewBox="0 0 40 40" fill="none" aria-hidden="true" class="h-full w-auto shrink-0 text-brand">
        <circle cx="20" cy="20" r="2.6" fill="currentColor" />
        <circle cx="20" cy="20" r="8" stroke="currentColor" stroke-width="1.6" opacity=".8" />
        <circle cx="20" cy="20" r="14" stroke="currentColor" stroke-width="1.4" opacity=".45" />
        <circle cx="20" cy="20" r="19" stroke="currentColor" stroke-width="1.2" opacity=".2" />
    </svg>

    @unless ($iconOnly)
        <span class="font-bold leading-none">DO<span class="text-brand">.</span>ing<span class="opacity-60 font-semibold"> Club</span></span>
    @endunless
</span>
