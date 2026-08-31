<?php

use App\Http\Controllers\Membros\FrameworkPdfDownloadController;
use App\Http\Controllers\Membros\LessonMaterialDownloadController;
use App\Http\Controllers\Membros\PreviewPersonaController;
use App\Http\Controllers\Membros\VaultDocumentOpenController;
use App\Http\Controllers\Webhooks\AbacatePayWebhookController;
use App\Livewire\Membros\Agenda;
use App\Livewire\Membros\AulaMateriais;
use App\Livewire\Membros\Aulas;
use App\Livewire\Membros\Cofre;
use App\Livewire\Membros\Conteudo;
use App\Livewire\Membros\Dashboard;
use App\Livewire\Membros\Disponibilidade;
use App\Livewire\Membros\Dossies;
use App\Livewire\Membros\Encontros;
use App\Livewire\Membros\Frameworks;
use App\Livewire\Membros\Pessoas;
use App\Livewire\Membros\Radar;
use App\Livewire\Membros\Sobre;
use App\Livewire\Membros\Upgrade;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()->isMentor()
        ? redirect()->route('mentor.radar')
        : redirect()->route('dashboard');
});

Route::get('membros', Dashboard::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('dashboard');

Route::get('membros/materiais/{material}/download', LessonMaterialDownloadController::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.materials.download');

Route::get('membros/frameworks/{framework}/download', FrameworkPdfDownloadController::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.frameworks.download');

Route::get('membros/cofre/{document}/abrir', VaultDocumentOpenController::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.cofre.open');

Route::get('membros/sobre', Sobre::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.sobre');

Route::get('membros/aulas', Aulas::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.aulas');

Route::get('membros/aulas/{lesson}/materiais', AulaMateriais::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.aulas.materiais');

Route::get('membros/cofre', Cofre::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.cofre');

Route::get('membros/encontros', Encontros::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.encontros');

Route::get('membros/agenda', Agenda::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.agenda');

Route::get('membros/pessoas', Pessoas::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.pessoas');

Route::get('membros/frameworks', Frameworks::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.frameworks');

Route::get('membros/upgrade', Upgrade::class)
    ->middleware(['auth', 'verified', 'active', 'tier:start'])
    ->name('membros.upgrade');

Route::get('membros/mentor/disponibilidade', Disponibilidade::class)
    ->middleware(['auth', 'verified', 'active', 'tier:mentor'])
    ->name('mentor.disp');

Route::get('membros/mentor/dossies', Dossies::class)
    ->middleware(['auth', 'verified', 'active', 'tier:mentor'])
    ->name('mentor.dossies');

Route::get('membros/mentor/radar', Radar::class)
    ->middleware(['auth', 'verified', 'active', 'tier:mentor'])
    ->name('mentor.radar');

Route::get('membros/mentor/conteudo', Conteudo::class)
    ->middleware(['auth', 'verified', 'active', 'tier:mentor'])
    ->name('mentor.conteudo');

Route::get('membros/preview-persona/{tier}', PreviewPersonaController::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.preview-persona');

Route::view('profile', 'profile')
    ->middleware(['auth', 'verified', 'active'])
    ->name('profile');

Route::post('webhooks/abacatepay', AbacatePayWebhookController::class)
    ->name('webhooks.abacatepay');

require __DIR__.'/auth.php';
require __DIR__.'/prototype.php';
