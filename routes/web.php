<?php

use App\Http\Controllers\Membros\LessonController;
use App\Livewire\Membros\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('membros', Dashboard::class)->name('dashboard');

    Route::post('membros/aulas/{lesson}/assistir', [LessonController::class, 'markAsWatching'])
        ->name('membros.aulas.assistir');

    Route::get('membros/materiais/{lesson}', [LessonController::class, 'materials'])
        ->name('membros.materiais');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
