@props(['encontro', 'isNext' => false])

<div class="flex items-center gap-4 p-5 rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)]">
    <div class="shrink-0 w-14 text-center rounded-xl border border-sand bg-paper py-2">
        <b class="block font-display text-lg leading-none">{{ $encontro->scheduled_at->format('d') }}</b>
        <small class="text-xs text-stone">{{ $encontro->scheduled_month_label }}</small>
    </div>

    <div class="flex-1 min-w-0">
        <b class="font-display text-sm block leading-tight">{{ $encontro->tema }}</b>
        <small class="mt-0.5 block text-xs text-stone">
            {{ $encontro->quem }} · {{ $encontro->scheduled_at->format('H\hi') }}
        </small>
    </div>

    <div class="flex items-center gap-2">
        @if ($encontro->isPast())
            @if ($encontro->recording_lesson_id && $encontro->lesson)
                <a href="{{ route('membros.aulas', ['lesson' => $encontro->recording_lesson_id]) }}" wire:navigate
                   class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold border border-sand text-ink hover:border-black">
                    Ver na biblioteca
                </a>
            @else
                <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-card border border-sand text-stone cursor-not-allowed">
                    Gravação em breve
                </span>
            @endif
        @else
            @if ($isNext)
                <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide bg-brand text-white">
                    Próximo
                </span>
            @endif

            @if ($encontro->access_url)
                <a href="{{ $encontro->access_url }}" target="_blank" rel="noopener"
                   class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-black text-white hover:brightness-110">
                    Entrar
                </a>
            @else
                <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-card border border-sand text-stone cursor-not-allowed">
                    Link em breve
                </span>
            @endif
        @endif
    </div>
</div>
