@props(['framework'])

<div class="flex flex-col gap-2.5 p-6 rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)] overflow-hidden">
    <div class="framework-card-code">{{ $framework->code }}</div>
    <h3 class="font-display text-base">{{ $framework->title }}</h3>
    <p class="text-sm text-ink/80 flex-1">{{ $framework->description }}</p>
    <div class="flex gap-2 mt-1.5">
        @if ($framework->hasUploadedFile())
            <a href="{{ route('membros.frameworks.download', $framework) }}"
               class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-black text-white hover:brightness-110">
                Baixar PDF
            </a>
        @elseif ($framework->pdf_url)
            <a href="{{ $framework->pdf_url }}" target="_blank" rel="noopener"
               class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-black text-white hover:brightness-110">
                Baixar PDF
            </a>
        @else
            <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-card border border-sand text-stone cursor-not-allowed">
                PDF em breve
            </span>
        @endif

        @if ($framework->lesson_id && $framework->lesson)
            @if ($framework->lesson->isAvailableFor(auth()->user()))
                <a href="{{ route('membros.aulas', ['lesson' => $framework->lesson_id]) }}" wire:navigate
                   class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold border border-sand text-ink hover:border-black">
                    Ver aula
                </a>
            @else
                <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-card border border-sand text-stone cursor-not-allowed">
                    Exclusivo CLUB
                </span>
            @endif
        @endif
    </div>
</div>
