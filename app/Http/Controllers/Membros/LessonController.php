<?php

namespace App\Http\Controllers\Membros;

use App\Actions\MarkLessonAsWatching;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function markAsWatching(Lesson $lesson, MarkLessonAsWatching $action): RedirectResponse
    {
        $action->handle(Auth::id(), $lesson->id);

        return redirect()->route('dashboard');
    }

    public function materials(Lesson $lesson): View
    {
        return view('membros.materiais', [
            'lesson' => $lesson->load('materials'),
        ]);
    }
}
