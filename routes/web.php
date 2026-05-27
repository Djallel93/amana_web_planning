<?php
// routes/web.php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\PlanningEditController;
use App\Http\Controllers\PersonnesController;
use App\Http\Controllers\RestrictionsController;
use App\Http\Controllers\AbsencesController;
use App\Http\Controllers\EvenementsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes publiques
|--------------------------------------------------------------------------
*/
Route::get('/', fn() => redirect()->route('planning.index'));

Route::get('/login',   [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',  [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Routes protégées
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // ── Planning — affichage, génération, stats ───────────────────────────
    Route::prefix('planning')->name('planning.')->group(function () {

        Route::get('/',         [PlanningController::class, 'index'])->name('index');
        Route::get('/generer',  [PlanningController::class, 'showGenerateForm'])->name('generate.form');
        Route::post('/generer', [PlanningController::class, 'generate'])->name('generate');
        Route::get('/stats',    [PlanningController::class, 'statistics'])->name('statistics');

        // Rollback
        Route::post('/rollback',         [PlanningController::class, 'rollback'])->name('rollback');
        Route::post('/rollback/dismiss', [PlanningController::class, 'rollbackDismiss'])->name('rollback.dismiss');

        // Export PDF
        Route::get('/export',      [PlanningController::class, 'showExportForm'])->name('export.form');
        Route::post('/export/pdf', [PlanningController::class, 'exportPdf'])->name('export.pdf');

        // ── Édition manuelle (AJAX) ───────────────────────────────────────
        // Personnes disponibles pour la modale
        Route::get('/personnes-actives', [PlanningEditController::class, 'personnes'])
            ->name('edit.personnes');

        // Modifier une assignation : PATCH /planning/creneau/{creneauId}/tache/{tacheId}F
        Route::patch('/creneau/{creneauId}/tache/{tacheId}', [PlanningEditController::class, 'patchAssignation'])
            ->name('edit.assignation')
            ->where(['creneauId' => '[0-9]+', 'tacheId' => '[0-9]+']);

        // Désassigner une tâche : DELETE /planning/creneau/{creneauId}/tache/{tacheId}
        Route::delete('/creneau/{creneauId}/tache/{tacheId}', [PlanningEditController::class, 'unassignTache'])
            ->name('edit.unassign')
            ->where(['creneauId' => '[0-9]+', 'tacheId' => '[0-9]+']);

        // Supprimer un créneau entier : DELETE /planning/creneau/{id}
        Route::delete('/creneau/{id}', [PlanningEditController::class, 'deleteCreneau'])
            ->name('edit.delete-creneau')
            ->where('id', '[0-9]+');
    });

    // ── Personnes ─────────────────────────────────────────────────────────
    Route::prefix('personnes')->name('personnes.')->group(function () {
        Route::get('/',            [PersonnesController::class, 'index'])->name('index');
        Route::get('/creer',       [PersonnesController::class, 'create'])->name('create');
        Route::post('/',           [PersonnesController::class, 'store'])->name('store');
        Route::get('/{id}/editer', [PersonnesController::class, 'edit'])->name('edit')->where('id', '[0-9]+');
        Route::put('/{id}',        [PersonnesController::class, 'update'])->name('update')->where('id', '[0-9]+');
        Route::delete('/{id}',     [PersonnesController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
    });

    // ── Restrictions ──────────────────────────────────────────────────────
    Route::prefix('restrictions')->name('restrictions.')->group(function () {
        Route::get('/',        [RestrictionsController::class, 'index'])->name('index');
        Route::post('/update', [RestrictionsController::class, 'update'])->name('update');
    });

    // ── Absences ──────────────────────────────────────────────────────────
    Route::prefix('absences')->name('absences.')->group(function () {
        Route::get('/',        [AbsencesController::class, 'index'])->name('index');
        Route::post('/',       [AbsencesController::class, 'store'])->name('store');
        Route::delete('/{id}', [AbsencesController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
    });

    // ── Événements ────────────────────────────────────────────────────────
    Route::prefix('evenements')->name('evenements.')->group(function () {
        Route::get('/',            [EvenementsController::class, 'index'])->name('index');
        Route::get('/creer',       [EvenementsController::class, 'create'])->name('create');
        Route::post('/',           [EvenementsController::class, 'store'])->name('store');
        Route::get('/{id}/editer', [EvenementsController::class, 'edit'])->name('edit')->where('id', '[0-9]+');
        Route::put('/{id}',        [EvenementsController::class, 'update'])->name('update')->where('id', '[0-9]+');
        Route::delete('/{id}',     [EvenementsController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
    });
});
