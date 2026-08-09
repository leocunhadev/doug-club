<?php

namespace App\Actions;

use App\Models\LessonProgress;

class UpdateLessonWatchedSeconds
{
    public function handle(int $userId, int $lessonId, int $seconds): void
    {
        $progress = LessonProgress::query()->firstOrNew([
            'user_id' => $userId,
            'lesson_id' => $lessonId,
        ]);

        $progress->watched_seconds = $seconds;
        $progress->last_watched_at = now();

        if ($progress->status !== 'completed') {
            $progress->status = 'watching';
        }

        $progress->save();
    }
}
