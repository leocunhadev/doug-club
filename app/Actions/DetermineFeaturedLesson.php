<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;

class DetermineFeaturedLesson
{
    /**
     * The lesson to show in the hero player: the user's most recently
     * watched lesson (if it's still available to their current tier), or
     * the first available lesson of the highest-position course otherwise.
     */
    public function handle(User $user): ?int
    {
        $lastWatched = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereHas('lesson', fn ($query) => $query
                ->when(! $user->hasClubAccess(), fn ($q) => $q->where('tier', 'start')))
            ->latest('last_watched_at')
            ->value('lesson_id');

        if ($lastWatched) {
            return $lastWatched;
        }

        return Lesson::query()
            ->join('courses', 'courses.id', '=', 'lessons.course_id')
            ->when(! $user->hasClubAccess(), fn ($q) => $q->where('lessons.tier', 'start'))
            ->orderByDesc('courses.position')
            ->orderByDesc('lessons.position')
            ->value('lessons.id');
    }
}
