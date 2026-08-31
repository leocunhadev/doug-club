<?php

use App\Http\Controllers\Prototype\PrototypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('prototype')->name('prototype.')->group(function () {
    Route::get('/', fn () => redirect()->route('prototype.login'));

    Route::get('login', [PrototypeController::class, 'login'])->name('login');
    Route::get('home', [PrototypeController::class, 'home'])->name('home');
    Route::get('aulas', [PrototypeController::class, 'aulas'])->name('aulas');
    Route::get('frameworks', [PrototypeController::class, 'frameworks'])->name('frameworks');
    Route::get('upgrade', [PrototypeController::class, 'upgrade'])->name('upgrade');
    Route::get('cofre', [PrototypeController::class, 'cofre'])->name('cofre');
    Route::get('agenda', [PrototypeController::class, 'agenda'])->name('agenda');
    Route::get('pessoas', [PrototypeController::class, 'pessoas'])->name('pessoas');
    Route::get('encontros', [PrototypeController::class, 'encontros'])->name('encontros');
    Route::get('radar', [PrototypeController::class, 'radar'])->name('radar');
    Route::get('dossies', [PrototypeController::class, 'dossies'])->name('dossies');
    Route::get('conteudo', [PrototypeController::class, 'conteudo'])->name('conteudo');
    Route::get('disp', [PrototypeController::class, 'disp'])->name('disp');
});
