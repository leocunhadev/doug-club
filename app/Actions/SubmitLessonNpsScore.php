<?php

namespace App\Actions;

use App\Models\LessonFeedback;

class SubmitLessonNpsScore
{
    public function handle(int $userId, int $lessonId, int $score): void
    {
        LessonFeedback::query()->updateOrCreate(
            ['user_id' => $userId, 'lesson_id' => $lessonId],
            ['score' => max(0, min(10, $score))],
        );
    }
}
