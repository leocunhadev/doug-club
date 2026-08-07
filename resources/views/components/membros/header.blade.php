@props(['initials'])

<header class="border-b border-slate-800/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
            <x-application-logo class="h-8 w-auto shrink-0 fill-current text-orange-500" />
            <span class="leading-tight">
                <span class="block text-sm font-bold uppercase tracking-wide text-white">Estabilidade</span>
                <span class="block text-[11px] font-medium uppercase tracking-widest text-gray-400">Não existe</span>
            </span>
        </a>

        <x-dropdown align="right" width="48" contentClasses="py-1 bg-surface border border-slate-800/60">
            <x-slot name="trigger">
                <button type="button" class="h-9 w-9 rounded-full bg-gradient-to-br from-orange-500 to-red-600 text-sm font-semibold text-white flex items-center justify-center">
                    {{ $initials }}
                </button>
            </x-slot>

            <x-slot name="content">
                <button wire:click="logout" type="button" class="w-full text-start px-4 py-2 text-sm text-gray-300 hover:bg-slate-800/60">
                    Sair
                </button>
            </x-slot>
        </x-dropdown>
    </div>
</header>
