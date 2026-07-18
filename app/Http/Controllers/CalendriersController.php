<?php
// app/Http/Controllers/CalendriersController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CalendrierGoogle;
use Illuminate\Http\JsonResponse;

/**
 * Sert la liste des calendriers Google Calendar **enregistrés** dans
 * ref_calendriers_google (voir CalendrierGoogleController pour la gestion
 * de ce registre) — alimente le dropdown de sélection de calendrier
 * (SearchableSelect.vue) côté Paramètres et formulaire d'événement.
 *
 * Ne fait AUCUN appel à l'API Google Calendar : contrairement à ce qu'on
 * pourrait attendre, un compte de service n'a pas de "liste de calendriers"
 * interrogeable (calendarList.list() renvoie toujours une liste vide pour un
 * compte de service, même avec des calendriers partagés — voir
 * docs/google_service_account.md et le docblock de la migration
 * 2026_07_17_000001_create_ref_calendriers_google_table.php). Chaque
 * calendrier doit donc être enregistré manuellement une fois (validé via
 * `calendars.get()` au moment de l'ajout), après quoi cette route ne fait
 * qu'une lecture DB — rapide, sans dépendance réseau à chaque affichage de
 * formulaire.
 *
 * Route : GET /api/calendriers (tous rôles connectés)
 */
class CalendriersController extends Controller
{
    public function index(): JsonResponse
    {
        $calendars = CalendrierGoogle::where('actif', true)
            ->orderBy('nom')
            ->get()
            ->map(fn(CalendrierGoogle $c) => [
                'id' => $c->calendar_id,
                'name' => $c->nom,
            ])
            ->values();

        if ($calendars->isEmpty()) {
            return response()->json([
                'calendars' => [],
                'erreur' => 'Aucun calendrier Google Calendar enregistré. Un gestionnaire ou administrateur peut en ajouter depuis /parametres.',
            ]);
        }

        return response()->json(['calendars' => $calendars]);
    }
}
