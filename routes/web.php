<?php

use App\Http\Controllers\Membros\LessonMaterialDownloadController;
use App\Http\Controllers\Webhooks\AbacatePayWebhookController;
use App\Livewire\Membros\Dashboard;
use App\Livewire\Membros\Sobre;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::get('membros', Dashboard::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('dashboard');

Route::get('membros/materiais/{material}/download', LessonMaterialDownloadController::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.materials.download');

Route::get('membros/sobre', Sobre::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.sobre');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::post('webhooks/abacatepay', AbacatePayWebhookController::class)
    ->name('webhooks.abacatepay');

require __DIR__.'/auth.php';
