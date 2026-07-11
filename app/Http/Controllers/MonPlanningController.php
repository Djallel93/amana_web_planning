<?php
// app/Http/Controllers/MonPlanningController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CreneauTache;
use App\Models\Echange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Vue personnelle du planning pour le membre connecté.
 *
 * Par défaut, affiche un glissant d'un an + futur (même fenêtre par défaut
 * que planning.index / PlanningApiController::data()). Le paramètre
 * ?historique=1 lève cette limite pour afficher tout l'historique — même
 * règle et même nom de paramètre que côté Planning, pour rester cohérent.
 * Passe également les échanges en attente pour afficher les badges.
 */
class MonPlanningController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        $historique = $request->boolean('historique');

        $query = CreneauTache::with(['creneau.evenements', 'tache'])
            ->join('plan_creneaux', 'plan_creneaux.id', '=', 'plan_creneaux_taches.id_planning')
            ->where('plan_creneaux_taches.id_personne', $user->id)
            ->orderBy('plan_creneaux.date', 'desc')
            ->select('plan_creneaux_taches.*');

        if (!$historique) {
            $dateMin = now()->subYear()->toDateString();
            $query->where('plan_creneaux.date', '>=', $dateMin);
        }

        $lignes = $query->get();

        // Grouper par mois (YYYY-MM) pour l'affichage en sections
        $parMois = $lignes->groupBy(fn($l) => $l->creneau->date->format('Y-m'));

        // Statistiques rapides
        $total = $lignes->count();
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
            'historique',
        ));
    }
}

