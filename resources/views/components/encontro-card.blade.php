@props(['encontro', 'isNext' => false])

<div class="enc-item {{ $isNext ? 'next' : '' }} pb-5">
    <div class="flex items-center gap-4 flex-wrap px-[22px] py-[18px] rounded-[18px] border {{ $isNext ? 'border-brand' : 'border-sand' }} bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)]">
        <div class="shrink-0 w-[46px] h-[50px] rounded-xl border border-sand bg-paper flex flex-col items-center justify-center">
            <b class="font-display text-[17px] leading-none">{{ $encontro->scheduled_at->format('d') }}</b>
            <small class="text-[10px] uppercase tracking-wide text-stone">{{ $encontro->scheduled_month_label }}</small>
        </div>

        <div class="flex-1 min-w-[200px]">
            <b class="font-display text-base block">{{ $encontro->tema }}</b>
            <small class="text-stone">
                {{ $encontro->quem }} · {{ $encontro->scheduled_at->format('H\hi') }}
            </small>
        </div>

        <div class="flex items-center gap-2">
            @if ($encontro->isPast())
                @if ($encontro->recording_lesson_id && $encontro->lesson)
                    <a href="{{ route('membros.aulas', ['lesson' => $encontro->recording_lesson_id]) }}" wire:navigate
                       class="inline-flex items-center px-[11px] py-[5px] rounded-full text-[11px] font-bold uppercase tracking-[.1em] bg-paper text-stone border border-sand hover:border-black hover:text-ink">
                        Ver na biblioteca
                    </a>
                @else
                    <span class="inline-flex items-center px-[11px] py-[5px] rounded-full text-[11px] font-bold uppercase tracking-[.1em] bg-paper text-stone border border-sand cursor-not-allowed">
                        Gravação em breve
                    </span>
                @endif
            @else
                @if ($isNext)
                    <span class="inline-flex items-center px-[11px] py-[5px] rounded-full text-[11px] font-bold uppercase tracking-[.1em] bg-brand text-white">
                        Próximo
                    </span>
                @endif

                @if ($encontro->access_url)
                    <a href="{{ $encontro->access_url }}" target="_blank" rel="noopener"
                       class="inline-flex items-center px-[11px] py-[5px] rounded-full text-[11px] font-bold uppercase tracking-[.1em] bg-black text-white hover:brightness-110">
                        Entrar
                    </a>
                @else
                    <span class="inline-flex items-center px-[11px] py-[5px] rounded-full text-[11px] font-bold uppercase tracking-[.1em] bg-paper text-stone border border-sand cursor-not-allowed">
                        Link em breve
                    </span>
                @endif
            @endif
        </div>
    </div>
</div>
