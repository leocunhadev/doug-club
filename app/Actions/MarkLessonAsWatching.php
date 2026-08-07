<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\LessonProgress;

class MarkLessonAsWatching
{
    public function handle(int $userId, int $lessonId): void
    {
        Lesson::query()->findOrFail($lessonId);

        LessonProgress::query()->updateOrCreate(
            ['user_id' => $userId, 'lesson_id' => $lessonId],
            ['status' => 'watching', 'last_watched_at' => now()],
        );
    }
}
