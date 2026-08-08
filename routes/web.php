<?php

use App\Http\Controllers\Membros\LessonMaterialDownloadController;
use App\Livewire\Membros\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::get('membros', Dashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('membros/materiais/{material}/download', LessonMaterialDownloadController::class)
    ->middleware(['auth', 'verified'])
    ->name('membros.materials.download');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
