<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-12">

        <h1 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Sua central de conteúdos') }}
        </h1>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
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

                @if ($lesson->materials->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($lesson->materials as $material)
                            <a href="{{ $material->file_url }}" target="_blank"
                               class="inline-flex items-center px-3 py-1.5 rounded-md text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
                                📎 {{ $material->title }}
                            </a>
                        @endforeach
                    </div>
                @endif
            @else
                <p class="text-gray-500 dark:text-gray-400">Nenhuma aula disponível ainda.</p>
            @endif
        </div>

        @foreach ($this->courses as $course)
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ $course->label }}: {{ $course->title }}
                </h2>
                @if ($course->description)
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $course->description }}</p>
                @endif

                <div class="mt-4 flex gap-4 overflow-x-auto pb-2">
                    @foreach ($course->lessons as $courseLesson)
                        <button
                            wire:click="watchLesson({{ $courseLesson->id }})"
                            type="button"
                            class="relative shrink-0 w-56 text-left bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 hover:ring-2 hover:ring-orange-500"
                        >
                            @if ($watchingLessonId === $courseLesson->id)
                                <span class="absolute top-2 right-2 text-xs font-semibold px-2 py-0.5 rounded-full bg-orange-500 text-white">
                                    ASSISTINDO
                                </span>
                            @endif
                            <p class="text-xs uppercase tracking-wide text-gray-400">Aula</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $courseLesson->number }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                {{ $courseLesson->published_at->format('d/m/Y') }}
                            </p>
                            <p class="text-sm text-gray-700 dark:text-gray-200 mt-1 line-clamp-2">
                                {{ $courseLesson->title }}
                            </p>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach

    </div>
</div>
