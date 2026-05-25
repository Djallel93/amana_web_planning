<?php
// routes/web.php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\PersonnesController;
use App\Http\Controllers\RestrictionsController;
use App\Http\Controllers\AbsencesController;
use App\Http\Controllers\EvenementsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes publiques (non protégées)
|--------------------------------------------------------------------------
*/

// Redirection racine → planning (ou login si non connecté)
Route::get('/', fn() => redirect()->route('planning.index'));

// Authentification
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Routes protégées (nécessitent d'être connecté)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // ── Planning ──────────────────────────────────────────────────────────
    Route::prefix('planning')->name('planning.')->group(function () {
        Route::get('/',          [PlanningController::class, 'index'])
             ->name('index');
        Route::get('/generer',   [PlanningController::class, 'showGenerateForm'])
             ->name('generate.form');
        Route::post('/generer',  [PlanningController::class, 'generate'])
             ->name('generate');
        Route::get('/stats',     [PlanningController::class, 'statistics'])
             ->name('statistics');
    });

    // ── Personnes ─────────────────────────────────────────────────────────
    Route::prefix('personnes')->name('personnes.')->group(function () {
        Route::get('/',            [PersonnesController::class, 'index'])
             ->name('index');
        Route::get('/creer',       [PersonnesController::class, 'create'])
             ->name('create');
        Route::post('/',           [PersonnesController::class, 'store'])
             ->name('store');
        Route::get('/{id}/editer', [PersonnesController::class, 'edit'])
             ->name('edit')->where('id', '[0-9]+');
        Route::put('/{id}',        [PersonnesController::class, 'update'])
             ->name('update')->where('id', '[0-9]+');
        Route::delete('/{id}',     [PersonnesController::class, 'destroy'])
             ->name('destroy')->where('id', '[0-9]+');
    });

    // ── Restrictions ──────────────────────────────────────────────────────
    Route::prefix('restrictions')->name('restrictions.')->group(function () {
        Route::get('/',            [RestrictionsController::class, 'index'])
             ->name('index');
        Route::post('/update',     [RestrictionsController::class, 'update'])
             ->name('update');
    });

    // ── Absences ──────────────────────────────────────────────────────────
    Route::prefix('absences')->name('absences.')->group(function () {
        Route::get('/',        [AbsencesController::class, 'index'])
             ->name('index');
        Route::post('/',       [AbsencesController::class, 'store'])
             ->name('store');
        Route::delete('/{id}', [AbsencesController::class, 'destroy'])
             ->name('destroy')->where('id', '[0-9]+');
    });

    // ── Événements ────────────────────────────────────────────────────────
    Route::prefix('evenements')->name('evenements.')->group(function () {
        Route::get('/',            [EvenementsController::class, 'index'])
             ->name('index');
        Route::get('/creer',       [EvenementsController::class, 'create'])
             ->name('create');
        Route::post('/',           [EvenementsController::class, 'store'])
             ->name('store');
        Route::get('/{id}/editer', [EvenementsController::class, 'edit'])
             ->name('edit')->where('id', '[0-9]+');
        Route::put('/{id}',        [EvenementsController::class, 'update'])
             ->name('update')->where('id', '[0-9]+');
        Route::delete('/{id}',     [EvenementsController::class, 'destroy'])
             ->name('destroy')->where('id', '[0-9]+');
    });
});
