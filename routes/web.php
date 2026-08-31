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

Route::get('/', Dashboard::class)
    ->middleware(['auth', 'verified', 'active', 'redirect-mentor-from-dashboard'])
    ->name('dashboard');

Route::get('materiais/{material}/download', LessonMaterialDownloadController::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.materials.download');

Route::get('frameworks/{framework}/download', FrameworkPdfDownloadController::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.frameworks.download');

Route::get('cofre/{document}/abrir', VaultDocumentOpenController::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.cofre.open');

Route::get('sobre', Sobre::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.sobre');

Route::get('aulas', Aulas::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.aulas');

Route::get('aulas/{lesson}/materiais', AulaMateriais::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.aulas.materiais');

Route::get('cofre', Cofre::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.cofre');

Route::get('encontros', Encontros::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.encontros');

Route::get('agenda', Agenda::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.agenda');

Route::get('pessoas', Pessoas::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.pessoas');

Route::get('frameworks', Frameworks::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.frameworks');

Route::get('upgrade', Upgrade::class)
    ->middleware(['auth', 'verified', 'active', 'tier:start'])
    ->name('membros.upgrade');

Route::get('mentor/disponibilidade', Disponibilidade::class)
    ->middleware(['auth', 'verified', 'active', 'tier:mentor'])
    ->name('mentor.disp');

Route::get('mentor/dossies', Dossies::class)
    ->middleware(['auth', 'verified', 'active', 'tier:mentor'])
    ->name('mentor.dossies');

Route::get('mentor/radar', Radar::class)
    ->middleware(['auth', 'verified', 'active', 'tier:mentor'])
    ->name('mentor.radar');

Route::get('mentor/conteudo', Conteudo::class)
    ->middleware(['auth', 'verified', 'active', 'tier:mentor'])
    ->name('mentor.conteudo');

Route::get('preview-persona/{tier}', PreviewPersonaController::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.preview-persona');

Route::view('profile', 'profile')
    ->middleware(['auth', 'verified', 'active'])
    ->name('profile');

Route::post('webhooks/abacatepay', AbacatePayWebhookController::class)
    ->name('webhooks.abacatepay');

require __DIR__.'/auth.php';
require __DIR__.'/prototype.php';
