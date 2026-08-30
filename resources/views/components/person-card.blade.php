@props(['member', 'alreadyRequested'])

<div class="person rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)]">
    <div class="top">
        @if ($member->photo_url)
            <img src="{{ $member->photo_url }}" alt="{{ $member->name }}" class="avatar">
        @else
            <div class="avatar">{{ $member->initials }}</div>
        @endif
        <div>
            <b>{{ $member->name }}</b>
            @if ($member->company)
                <small>{{ $member->company }}</small>
            @endif
        </div>
    </div>

    <p class="bio">{{ $member->bio ?: 'Ainda não contou nada sobre si.' }}</p>

    <div>
        <div class="lbl">Pode ensinar</div>
        @if (filled($member->teach_tags))
            @foreach ($member->teach_tags as $tag)
                <span class="tag ensina">{{ $tag }}</span>
            @endforeach
        @else
            <p class="text-stone text-[12.5px]">Ainda não preencheu.</p>
        @endif
    </div>

    <div>
        <div class="lbl">Quer aprender</div>
        @if (filled($member->learn_tags))
            @foreach ($member->learn_tags as $tag)
                <span class="tag">{{ $tag }}</span>
            @endforeach
        @else
            <p class="text-stone text-[12.5px]">Ainda não preencheu.</p>
        @endif
    </div>

    <div class="foot">
        @if ($alreadyRequested)
            <button type="button" disabled
                class="flex-1 rounded-full bg-black text-white text-xs font-semibold px-3.5 py-1.5 disabled:opacity-40 disabled:cursor-not-allowed">
                Pedido enviado
            </button>
        @else
            <button type="button" wire:click="requestBridge({{ $member->id }})"
                class="flex-1 rounded-full bg-black text-white text-xs font-semibold px-3.5 py-1.5 hover:brightness-110">
                Pedir a ponte
            </button>
        @endif
    </div>
</div>
