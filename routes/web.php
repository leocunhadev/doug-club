<?php

use App\Http\Controllers\Membros\LessonMaterialDownloadController;
use App\Http\Controllers\Membros\PreviewPersonaController;
use App\Http\Controllers\Webhooks\AbacatePayWebhookController;
use App\Livewire\Membros\Aulas;
use App\Livewire\Membros\Dashboard;
use App\Livewire\Membros\MentorPlaceholder;
use App\Livewire\Membros\Sobre;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()->isMentor()
        ? redirect()->route('mentor.placeholder')
        : redirect()->route('dashboard');
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

Route::get('membros/aulas', Aulas::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.aulas');

Route::get('membros/mentor', MentorPlaceholder::class)
    ->middleware(['auth', 'verified', 'active', 'tier:mentor'])
    ->name('mentor.placeholder');

Route::get('membros/preview-persona/{tier}', PreviewPersonaController::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.preview-persona');

Route::view('profile', 'profile')
    ->middleware(['auth', 'verified', 'active'])
    ->name('profile');

Route::post('webhooks/abacatepay', AbacatePayWebhookController::class)
    ->name('webhooks.abacatepay');

require __DIR__.'/auth.php';
