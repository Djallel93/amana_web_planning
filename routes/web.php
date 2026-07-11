<?php
// routes/web.php

declare(strict_types=1);

use App\Http\Controllers\Admin\CandidaturesController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ActiviteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BilanController;
use App\Http\Controllers\CalendriersController;
use App\Http\Controllers\DiagnosticController;
use App\Http\Controllers\EchangeController;
use App\Http\Controllers\EmergencyController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\MonPlanningController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\PlanningApiController;
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

// ── Outil d'urgence post-déploiement — génère un hash bcrypt (désactivé si APP_EMERGENCY_KEY vide) ──
Route::get('/urgence-hash', [EmergencyController::class, 'show'])->name('emergency.hash.show');
Route::post('/urgence-hash', [EmergencyController::class, 'generate'])->name('emergency.hash.generate');

// ── Authentification ──────────────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])
    ->name('login.submit')
    ->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ── Mot de passe oublié ───────────────────────────────────────────────────
Route::get('/mot-de-passe-oublie', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/mot-de-passe-oublie', [AuthController::class, 'sendResetLink'])->name('password.email');

// ── Réinitialisation / création du mot de passe ───────────────────────────
Route::get('/nouveau-mot-de-passe/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/nouveau-mot-de-passe', [AuthController::class, 'resetPassword'])->name('password.update');

// ── Inscription publique ──────────────────────────────────────────────────
Route::get('/inscription', [AuthController::class, 'showInscription'])
    ->name('inscription')
    ->middleware('throttle:20,1');

Route::post('/inscription', [AuthController::class, 'inscription'])
    ->name('inscription.submit')
    ->middleware('throttle:5,1');

// ── Tokens échanges (liens email, pas de connexion requise) ───────────────
// Ces routes DOIVENT être hors du middleware auth car B clique sur le lien
// depuis son email sans forcément être connecté.
Route::get('/echanges/{token}/accepter', [EchangeController::class, 'accepter'])
    ->name('echanges.accepter')
    ->middleware('throttle:10,1')
    ->where('token', '[a-zA-Z0-9]{64}');

Route::get('/echanges/{token}/refuser', [EchangeController::class, 'refuser'])
    ->name('echanges.refuser')
    ->middleware('throttle:10,1')
    ->where('token', '[a-zA-Z0-9]{64}');

/*
|--------------------------------------------------------------------------
| Routes protégées — nécessitent une connexion
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    // ── Mon planning (vue personnelle) — tous les membres connectés ────────
    Route::get('/mon-planning', [MonPlanningController::class, 'index'])->name('mon-planning');

    // ── Guide d'utilisation — tous les membres connectés (contenu adapté au rôle) ──
    Route::get('/guide', [GuideController::class, 'index'])->name('guide.index');

    // ── Bilan quotidien (Amana food + Présences) — tous les membres connectés ──
    Route::prefix('bilan')->name('bilan.')->group(function () {
        Route::get('/', [BilanController::class, 'index'])->name('index');
        Route::get('/data', [BilanController::class, 'show'])->name('data.show');
        Route::post('/data/amana-food', [BilanController::class, 'storeAmanaFood'])->name('data.store.amana-food');
        Route::post('/data/presence', [BilanController::class, 'storePresence'])->name('data.store.presence');
        Route::get('/statistiques', [BilanController::class, 'statistiques'])->name('statistiques');
        Route::get('/statistiques/data', [BilanController::class, 'statistiquesData'])->name('statistiques.data');
    });

    // ── API interne — liste des calendriers Make.com (tous rôles) ─────────
    Route::get('/api/calendriers', [CalendriersController::class, 'index'])->name('calendriers.index');

    // ── Diagnostic SMTP — admin uniquement ────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('/diagnostic-mail', [DiagnosticController::class, 'index'])->name('diagnostic.mail.index');
        Route::post('/diagnostic-mail', [DiagnosticController::class, 'tester'])->name('diagnostic.mail.tester');
    });

    // ── Échanges — tous les membres connectés ─────────────────────────────
    Route::prefix('echanges')->name('echanges.')->group(function () {
        Route::get('/', [EchangeController::class, 'index'])->name('index');
        Route::get('/slots-disponibles', [EchangeController::class, 'slotsDisponibles'])->name('slots');
        Route::post('/', [EchangeController::class, 'store'])->name('store');
        Route::delete('/{id}', [EchangeController::class, 'destroy'])
            ->name('destroy')
            ->where('id', '[0-9]+');
    });

    // ── Planning ───────────────────────────────────────────────────────────
    Route::prefix('planning')->name('planning.')->group(function () {

        // Lecture et export : tous les utilisateurs connectés
        Route::get('/', [PlanningController::class, 'index'])->name('index');
        Route::get('/data', [PlanningApiController::class, 'data'])->name('data');
        Route::get('/stats', [PlanningController::class, 'statistics'])->name('statistics');
        Route::get('/export', [PlanningController::class, 'showExportForm'])->name('export.form');
        Route::post('/export/pdf', [PlanningController::class, 'exportPdf'])->name('export.pdf');

        // Génération, rollback, preview : gestionnaire + admin
        Route::middleware('role:gestionnaire')->group(function () {
            Route::get('/generer', [PlanningController::class, 'showGenerateForm'])->name('generate.form');
            Route::post('/generer', [PlanningController::class, 'generate'])->name('generate');
            Route::post('/generer/apercu', [PlanningController::class, 'preview'])->name('preview');
            Route::post('/rollback', [PlanningController::class, 'rollback'])->name('rollback');
            Route::post('/rollback/dismiss', [PlanningController::class, 'rollbackDismiss'])->name('rollback.dismiss');
            Route::post('/overlap/cancel', function () {
                session()->forget('pending_generation');
                return redirect()->route('planning.generate.form');
            })->name('overlap.cancel');
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
            Route::post('/creneau', [PlanningEditController::class, 'createCreneau'])
                ->name('edit.create-creneau');
            Route::post('/annulation-cours', [PlanningEditController::class, 'annulerCours'])
                ->name('annulation-cours');
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
        Route::put('/{id}', [AbsencesController::class, 'update'])
            ->name('update')
            ->where('id', '[0-9]+');
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

        Route::prefix('journal')->name('journal.')->group(function () {
            Route::get('/', [AuditLogController::class, 'index'])->name('index');
            Route::get('/data', [AuditLogController::class, 'data'])->name('data');
        });

        Route::prefix('activite')->name('activite.')->group(function () {
            Route::get('/', [ActiviteController::class, 'index'])->name('index');
            Route::get('/data', [ActiviteController::class, 'data'])->name('data');
        });
    });

    // ── Échanges admin/gestionnaire ────────────────────────────────────────
    Route::middleware('role:gestionnaire')
        ->prefix('admin/echanges')
        ->name('admin.echanges.')
        ->group(function () {
            Route::get('/', [EchangeController::class, 'adminIndex'])->name('index');
            Route::post('/{id}/approuver', [EchangeController::class, 'adminApprouver'])
                ->name('approuver')
                ->where('id', '[0-9]+');
            Route::post('/{id}/refuser', [EchangeController::class, 'adminRefuser'])
                ->name('refuser')
                ->where('id', '[0-9]+');
        });
});