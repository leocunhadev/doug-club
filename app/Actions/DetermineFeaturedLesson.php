<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\LessonProgress;

class DetermineFeaturedLesson
{
    /**
     * The lesson to show in the hero player: the user's most recently
     * watched lesson, or the first lesson of the highest-position course
     * when the user has no progress yet.
     */
    public function handle(int $userId): ?int
    {
        $lastWatched = LessonProgress::query()
            ->where('user_id', $userId)
            ->latest('last_watched_at')
            ->value('lesson_id');

        if ($lastWatched) {
            return $lastWatched;
        }

        return Lesson::query()
            ->join('courses', 'courses.id', '=', 'lessons.course_id')
            ->orderByDesc('courses.position')
            ->orderByDesc('lessons.position')
            ->value('lessons.id');
    }
}
