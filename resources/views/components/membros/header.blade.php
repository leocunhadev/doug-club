@props(['initials'])

<header class="border-b border-sand bg-paper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
            <x-brand-logo class="h-8 w-auto text-black" />
        </a>

        <x-dropdown align="right" width="48" contentClasses="py-1 bg-card border border-sand">
            <x-slot name="trigger">
                <button type="button" class="h-9 w-9 rounded-full bg-brand text-sm font-semibold text-white flex items-center justify-center">
                    {{ $initials }}
                </button>
            </x-slot>

            <x-slot name="content">
                <a href="{{ route('profile') }}" wire:navigate class="block px-4 py-2 text-sm text-ink hover:bg-paper">
                    Meu perfil
                </a>

                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full text-start px-4 py-2 text-sm text-ink hover:bg-paper">
                        Sair
                    </button>
                </form>
            </x-slot>
        </x-dropdown>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="marquee" aria-hidden="true"><div class="track" id="mqTrack"><span>Tudo é gente ·</span><span>Decisão Orientada ·</span><span>Dado · Padrão · Decisão ·</span><span>DOR: Direção, Orientação, Resultado ·</span><span>Consumidor 4S ·</span><span>Tudo é gente ·</span><span>Decisão Orientada ·</span><span>Dado · Padrão · Decisão ·</span><span>DOR: Direção, Orientação, Resultado ·</span><span>Consumidor 4S ·</span></div></div>
    </div>

        <x-auth-session-status class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-3" :status="session('status')" />

    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-3 flex gap-1 overflow-x-auto" aria-label="Navegação principal">
        @foreach ((new \App\Support\PersonaNavigation)->tabs(auth()->user()->tier) as $tab)
            @if ($tab['available'])
                <a
                    href="{{ route($tab['route']) }}"
                    wire:navigate
                    @if (request()->routeIs($tab['route'])) aria-current="page" @endif
                    class="shrink-0 px-3 py-1.5 rounded-full text-sm font-medium {{ request()->routeIs($tab['route']) ? 'bg-black text-white' : 'text-stone hover:text-ink' }}"
                >
                    {{ $tab['label'] }}
                </a>
            @else
                <span class="shrink-0 px-3 py-1.5 rounded-full text-sm font-medium text-stone/50 cursor-not-allowed" title="Em breve">
                    {{ $tab['label'] }} 🔒
                </span>
            @endif
        @endforeach
    </nav>
</header>
