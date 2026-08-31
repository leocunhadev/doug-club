@props(['initials'])

<header class="border-b border-sand bg-paper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <a href="{{ route('dashboard') }}" wire:navigate class="wordmark" id="wmark">
            DO.ing <span>CLUB</span>@if (auth()->user()->viewingTier() === 'start')<em class="start-tag">start</em>@endif
        </a>

        <div style="display:flex;align-items:center;gap:10px">
            @if (auth()->user()->is_admin)
                <div class="planswitch" role="tablist" aria-label="Trocar plano de visualização">
                    @foreach (['start' => 'Start', 'club' => 'CLUB', 'mentor' => 'Mentor'] as $previewTier => $label)
                        <a href="{{ route('membros.preview-persona', ['tier' => $previewTier]) }}"
                           class="{{ auth()->user()->viewingTier() === $previewTier ? 'on' : '' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            @endif

            <x-dropdown align="right" width="48" contentClasses="py-1 bg-card border border-sand">
                <x-slot name="trigger">
                    @if (auth()->user()->photo_url)
                        <button type="button" class="h-9 w-9 rounded-full overflow-hidden">
                            <img src="{{ auth()->user()->photo_url }}" alt="" class="h-9 w-9 rounded-full object-cover">
                        </button>
                    @else
                        <button type="button" class="h-9 w-9 rounded-full bg-brand text-sm font-semibold text-white flex items-center justify-center">
                            {{ $initials }}
                        </button>
                    @endif
                </x-slot>

                <x-slot name="content">
                    <a href="{{ route('profile') }}" wire:navigate class="block px-4 py-2 text-sm text-ink hover:bg-paper">
                        Meu perfil
                    </a>

                    <a href="{{ route('membros.sobre') }}" wire:navigate class="block px-4 py-2 text-sm text-ink hover:bg-paper">
                        Sobre
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
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="marquee" aria-hidden="true"><div class="track" id="mqTrack"><span>Tudo é gente ·</span><span>Decisão Orientada ·</span><span>Dado · Padrão · Decisão ·</span><span>DOR: Direção, Orientação, Resultado ·</span><span>Consumidor 4S ·</span><span>Tudo é gente ·</span><span>Decisão Orientada ·</span><span>Dado · Padrão · Decisão ·</span><span>DOR: Direção, Orientação, Resultado ·</span><span>Consumidor 4S ·</span></div></div>
    </div>

        <x-auth-session-status class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-3" :status="session('status')" />

    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-3 flex gap-1 overflow-x-auto" aria-label="Navegação principal">
        @foreach ((new \App\Support\PersonaNavigation)->tabs(auth()->user()->viewingTier()) as $tab)
            <a
                href="{{ route($tab['route']) }}"
                wire:navigate
                @if ($tab['available'] && (request()->routeIs($tab['route']) || request()->routeIs($tab['route'].'.*'))) aria-current="page" @endif
                class="shrink-0 px-3 py-1.5 rounded-full text-sm font-medium {{ ! $tab['available'] ? 'text-stone/50' : (request()->routeIs($tab['route']) || request()->routeIs($tab['route'].'.*') ? 'bg-black text-white' : 'text-stone hover:text-ink') }}"
            >
                {{ $tab['label'] }}{{ $tab['available'] ? '' : ' 🔒' }}
            </a>
        @endforeach
    </nav>
</header>
