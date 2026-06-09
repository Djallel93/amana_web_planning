<?php
// routes/web.php

declare(strict_types=1);

use App\Http\Controllers\Admin\CandidaturesController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\PlanningEditController;
use App\Http\Controllers\PersonnesController;
use App\Http\Controllers\RestrictionsController;
use App\Http\Controllers\AbsencesController;
use App\Http\Controllers\EvenementsController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes publiques — accessibles sans connexion
|--------------------------------------------------------------------------
*/

Route::get('/', fn() => redirect()->route('planning.index'));

// ── Authentification ──────────────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ── Mot de passe oublié ───────────────────────────────────────────────────
Route::get('/mot-de-passe-oublie', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/mot-de-passe-oublie', [AuthController::class, 'sendResetLink'])->name('password.email');

// ── Réinitialisation / création du mot de passe ───────────────────────────
Route::get('/nouveau-mot-de-passe/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/nouveau-mot-de-passe', [AuthController::class, 'resetPassword'])->name('password.update');

// ── Inscription publique ──────────────────────────────────────────────────
Route::get('/inscription', [AuthController::class, 'showInscription'])->name('inscription');
Route::post('/inscription', [AuthController::class, 'inscription'])->name('inscription.submit');

/*
|--------------------------------------------------------------------------
| Routes protégées — nécessitent une connexion
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    // ── Planning ───────────────────────────────────────────────────────────
    Route::prefix('planning')->name('planning.')->group(function () {

        // Lecture et export : tous les utilisateurs connectés
        Route::get('/', [PlanningController::class, 'index'])->name('index');
        Route::get('/stats', [PlanningController::class, 'statistics'])->name('statistics');
        Route::get('/export', [PlanningController::class, 'showExportForm'])->name('export.form');
        Route::post('/export/pdf', [PlanningController::class, 'exportPdf'])->name('export.pdf');

        // Génération et rollback : gestionnaire + admin
        Route::middleware('role:gestionnaire')->group(function () {
            Route::get('/generer', [PlanningController::class, 'showGenerateForm'])->name('generate.form');
            Route::post('/generer', [PlanningController::class, 'generate'])->name('generate');
            Route::post('/rollback', [PlanningController::class, 'rollback'])->name('rollback');
            Route::post('/rollback/dismiss', [PlanningController::class, 'rollbackDismiss'])->name('rollback.dismiss');
        });

        // ── Édition manuelle AJAX — gestionnaire + admin ──────────────────
        Route::middleware('role:gestionnaire')->group(function () {

            Route::get('/personnes-actives', [PlanningEditController::class, 'personnes'])
                ->name('edit.personnes');

            Route::patch('/creneau/{creneauId}/tache/{tacheId}', [PlanningEditController::class, 'patchAssignation'])
                ->name('edit.assignation')
                ->where(['creneauId' => '[0-9]+', 'tacheId' => '[0-9]+']);

            Route::delete('/creneau/{creneauId}/tache/{tacheId}', [PlanningEditController::class, 'unassignTache'])
                ->name('edit.unassign')
                ->where(['creneauId' => '[0-9]+', 'tacheId' => '[0-9]+']);

            Route::delete('/creneau/{id}', [PlanningEditController::class, 'deleteCreneau'])
                ->name('edit.delete-creneau')
                ->where('id', '[0-9]+');

            // ── NEW: create a créneau manually ────────────────────────────
            Route::post('/creneau', [PlanningEditController::class, 'createCreneau'])
                ->name('edit.create-creneau');
        });
    });

    // ── Paramètres — gestionnaire + admin ─────────────────────────────────
    Route::middleware('role:gestionnaire')->group(function () {
        Route::get('/parametres', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/parametres', [SettingsController::class, 'update'])->name('settings.update');
    });

    // ── Restrictions ───────────────────────────────────────────────────────
    Route::prefix('restrictions')->name('restrictions.')->group(function () {
        Route::get('/', [RestrictionsController::class, 'index'])->name('index');
        Route::post('/update', [RestrictionsController::class, 'update'])->name('update');
    });

    // ── Absences ───────────────────────────────────────────────────────────
    Route::prefix('absences')->name('absences.')->group(function () {
        Route::get('/', [AbsencesController::class, 'index'])->name('index');
        Route::post('/', [AbsencesController::class, 'store'])->name('store');
        Route::delete('/{id}', [AbsencesController::class, 'destroy'])
            ->name('destroy')
            ->where('id', '[0-9]+');
    });

    // ── Événements ─────────────────────────────────────────────────────────
    Route::prefix('evenements')->name('evenements.')->group(function () {
        Route::get('/', [EvenementsController::class, 'index'])->name('index');

        Route::middleware('role:gestionnaire')->group(function () {
            Route::get('/creer', [EvenementsController::class, 'create'])->name('create');
            Route::post('/', [EvenementsController::class, 'store'])->name('store');
            Route::get('/{id}/editer', [EvenementsController::class, 'edit'])->name('edit')->where('id', '[0-9]+');
            Route::put('/{id}', [EvenementsController::class, 'update'])->name('update')->where('id', '[0-9]+');
            Route::delete('/{id}', [EvenementsController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
        });
    });

    // ── Personnes — admin uniquement ──────────────────────────────────────
    Route::middleware('role:admin')->prefix('personnes')->name('personnes.')->group(function () {
        Route::get('/', [PersonnesController::class, 'index'])->name('index');
        Route::get('/creer', [PersonnesController::class, 'create'])->name('create');
        Route::post('/', [PersonnesController::class, 'store'])->name('store');
        Route::get('/{id}/editer', [PersonnesController::class, 'edit'])->name('edit')->where('id', '[0-9]+');
        Route::put('/{id}', [PersonnesController::class, 'update'])->name('update')->where('id', '[0-9]+');
        Route::delete('/{id}', [PersonnesController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
    });

    // ── Administration — admin uniquement ─────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::prefix('candidatures')->name('candidatures.')->group(function () {
            Route::get('/', [CandidaturesController::class, 'index'])->name('index');
            Route::post('/{id}/valider', [CandidaturesController::class, 'valider'])->name('valider')->where('id', '[0-9]+');
            Route::post('/{id}/refuser', [CandidaturesController::class, 'refuser'])->name('refuser')->where('id', '[0-9]+');
            Route::post('/{id}/renvoyer-invitation', [CandidaturesController::class, 'renvoyerInvitation'])->name('renvoyer-invitation')->where('id', '[0-9]+');
        });
    });
});