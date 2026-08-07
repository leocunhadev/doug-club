<div>
    <h1 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        {{ __('Sua central de conteúdos') }}
    </h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
        Acompanhe as transmissões ao vivo e os conteúdos gravados. Tudo em um lugar só, exclusivo para quem decidiu agir.
    </p>

    <div class="mt-4 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
        @if ($lesson = $this->featuredLesson)
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                {{ $lesson->course->label }}: {{ $lesson->course->title }}
            </p>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Aula {{ $lesson->number }} — {{ $lesson->title }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ ucfirst($lesson->video_provider) }} · {{ $lesson->video_id }}
            </p>

            <button
                wire:click="toggleMaterials"
                type="button"
                class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600"
            >
                📁 Materiais de aula
            </button>

            @if ($showMaterials)
                <div class="mt-3">
                    @if ($lesson->materials->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum material disponível para esta aula.</p>
                    @else
                        <div class="flex flex-wrap gap-2">
                            @foreach ($lesson->materials as $material)
                                <a href="{{ $material->file_url }}" target="_blank"
                                   class="inline-flex items-center px-3 py-1.5 rounded-md text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
                                    📎 {{ $material->title }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        @else
            <p class="text-gray-500 dark:text-gray-400">Nenhuma aula disponível ainda.</p>
        @endif
    </div>
</div>
