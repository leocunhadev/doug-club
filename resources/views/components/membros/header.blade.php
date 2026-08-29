@props(['initials'])

<header class="border-b border-slate-800/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
            <x-brand-logo class="h-8 w-auto text-white" />
        </a>

        <x-dropdown align="right" width="48" contentClasses="py-1 bg-surface border border-slate-800/60">
            <x-slot name="trigger">
                <button type="button" class="h-9 w-9 rounded-full bg-gradient-to-br from-orange-500 to-red-600 text-sm font-semibold text-white flex items-center justify-center">
                    {{ $initials }}
                </button>
            </x-slot>

            <x-slot name="content">
                <a href="{{ route('profile') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-300 hover:bg-slate-800/60">
                    Meu perfil
                </a>

                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full text-start px-4 py-2 text-sm text-gray-300 hover:bg-slate-800/60">
                        Sair
                    </button>
                </form>
            </x-slot>
        </x-dropdown>
    </div>

    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-3 flex gap-1 overflow-x-auto" aria-label="Navegação principal">
        @foreach ((new \App\Support\PersonaNavigation)->tabs(auth()->user()->tier) as $tab)
            @if ($tab['available'])
                <a
                    href="{{ route($tab['route']) }}"
                    wire:navigate
                    @if (request()->routeIs($tab['route'])) aria-current="page" @endif
                    class="shrink-0 px-3 py-1.5 rounded-full text-sm font-medium {{ request()->routeIs($tab['route']) ? 'bg-white text-black' : 'text-gray-400 hover:text-white' }}"
                >
                    {{ $tab['label'] }}
                </a>
            @else
                <span class="shrink-0 px-3 py-1.5 rounded-full text-sm font-medium text-gray-600 cursor-not-allowed" title="Em breve">
                    {{ $tab['label'] }} 🔒
                </span>
            @endif
        @endforeach
    </nav>
</header>
