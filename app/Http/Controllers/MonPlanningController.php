<?php
// app/Http/Controllers/MonPlanningController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CreneauTache;
use App\Models\Echange;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Vue personnelle du planning pour le membre connecté.
 *
 * Affiche tous les créneaux où la personne est assignée,
 * sur un glissant d'un an + futur (même fenêtre que planning.index).
 * Passe également les échanges en attente pour afficher les badges.
 */
class MonPlanningController extends Controller
{
    public function index(): View
    {
        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        $dateMin = now()->subYear()->toDateString();

        $lignes = CreneauTache::with(['creneau.evenements', 'tache'])
            ->join('plan_creneaux', 'plan_creneaux.id', '=', 'plan_creneaux_taches.id_planning')
            ->where('plan_creneaux_taches.id_personne', $user->id)
            ->where('plan_creneaux.date', '>=', $dateMin)
            ->orderBy('plan_creneaux.date', 'desc')
            ->select('plan_creneaux_taches.*')
            ->get();

        // Grouper par mois (YYYY-MM) pour l'affichage en sections
        $parMois = $lignes->groupBy(fn($l) => $l->creneau->date->format('Y-m'));

        // Statistiques rapides
        $total   = $lignes->count();
        $futures = $lignes->filter(fn($l) => $l->creneau->date->isFuture())->count();
        $parTache = $lignes
            ->groupBy(fn($l) => $l->tache?->code ?? 'inconnu')
            ->map->count();

        // Échanges en attente impliquant ce membre
        // (pour afficher les badges sur les créneaux concernés)
        $echangesEnAttente = Echange::enAttente()
            ->impliquant($user->id)
            ->get();

        return view('planning.mon-planning', compact(
            'parMois',
            'total',
            'futures',
            'parTache',
            'user',
            'echangesEnAttente',
        ));
    }
}
