<?php

namespace App\Actions;

use App\Models\LessonProgress;

class MarkLessonAsWatching
{
    public function handle(int $userId, int $lessonId): void
    {
        LessonProgress::query()->updateOrCreate(
            ['user_id' => $userId, 'lesson_id' => $lessonId],
            ['status' => 'watching', 'last_watched_at' => now()],
        );
    }
}
