<?php
// app/Http/Controllers/GuideController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contrôleur du guide d'utilisation.
 *
 * Une seule page, dont les sections sont conditionnées par le rôle de
 * l'utilisateur connecté (membre / gestionnaire / admin), à l'identique
 * du gating utilisé dans layouts/partials/sidebar.blade.php. Un membre
 * ne reçoit tout simplement pas le HTML des sections Gestion/Administration
 * (pas juste masqué en CSS).
 *
 * 12/09/2026 — spike Inertia (voir resources/js/Pages/Guide/Index.vue) :
 * cette page a été choisie comme premier candidat car elle n'a ni
 * formulaire ni îlot Vue. L'utilisateur connecté n'est plus passé en prop
 * ici — il est déjà partagé globalement par HandleInertiaRequests::share(),
 * la page Vue lit le rôle depuis usePage().props.auth.user.
 */
class GuideController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Guide/Index', [
            // Route::has('settings.index') est toujours vrai en pratique
            // aujourd'hui (la route est enregistrée sans condition dans
            // routes/web.php), mais le Blade d'origine faisait bien ce test
            // à l'affichage plutôt que de supposer la route présente —
            // Vue ne pouvant pas appeler Route::has() côté client, on
            // reproduit fidèlement ce même calcul ici, côté serveur.
            'hasSettingsRoute' => Route::has('settings.index'),
        ]);
    }
}
