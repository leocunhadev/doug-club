<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Materiais — Aula {{ $lesson->number }}: {{ $lesson->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-orange-600 hover:underline">
                ← Voltar para a central de conteúdos
            </a>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if ($lesson->materials->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400">Esta aula não tem materiais disponíveis.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($lesson->materials as $material)
                            <li>
                                <a href="{{ $material->file_url }}" target="_blank"
                                   class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200 hover:text-orange-600">
                                    📎 {{ $material->title }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
