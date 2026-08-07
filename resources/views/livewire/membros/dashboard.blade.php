<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-12">

        <livewire:membros.hero-player />

        {{-- Cursos e aulas --}}
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
