@props(['document'])

<div class="doc-row">
    <div class="doc-ic">{{ $document->icon_label }}</div>
    <div class="d flex-1 min-w-0">
        <b class="block text-sm">{{ $document->title }}</b>
        @if ($document->description)
            <small class="text-stone text-[12.5px]">{{ $document->description }}</small>
        @endif
    </div>
    @if ($document->isNew())
        <span class="novo-pill">Novo</span>
    @endif
    <a href="{{ route('membros.cofre.open', $document) }}"
       class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold border border-sand text-ink hover:border-black">
        Abrir
    </a>
</div>
