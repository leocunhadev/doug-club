<?php

namespace App\Actions;

use App\Models\LessonProgress;

class MarkLessonAsCompleted
{
    public function handle(int $userId, int $lessonId): void
    {
        LessonProgress::query()->updateOrCreate(
            ['user_id' => $userId, 'lesson_id' => $lessonId],
            ['status' => 'completed', 'last_watched_at' => now()],
        );
    }
}
