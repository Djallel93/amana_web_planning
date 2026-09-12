<?php
// app/Http/Controllers/PlanningApiController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\Planning\BanniereResource;
use App\Http\Resources\Planning\CreneauResource;
use App\Models\Creneau;
use App\Models\Evenement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fournit le planning au format JSON, consommé par PlanningGrid.vue.
 *
 * Hérite de PlanningController pour réutiliser buildBannièresInformatives()
 * sans dupliquer la logique de calcul des bannières d'événements — la même
 * règle métier (chevauchement créneau/événement, "informatif" vs "bloquant")
 * doit produire le même résultat ici et dans l'export PDF / la vue Blade
 * historique (qui restent inchangés pour l'instant).
 *
 * Route : GET /planning/data?historique=0|1
 *
 * Cette route est volontairement dans routes/web.php (pas routes/api.php) :
 * elle est protégée par la session Laravel comme le reste de l'app, pas par
 * un token. La bascule vers une API token-authentifiée (Sanctum) pour des
 * consommateurs externes est une phase ultérieure du plan, pas ce ticket.
 */
class PlanningApiController extends PlanningController
{
    /**
     * Retourne les créneaux groupés par semaine, avec bannières et permissions,
     * au même format que ce que Blade calculait côté serveur.
     */
    public function data(Request $request): JsonResponse
    {
        $historique = $request->boolean('historique');

        $query = Creneau::with(['taches.tache', 'taches.personne', 'evenements.tachesBloquees'])
            ->orderBy('date', 'desc');

        if (!$historique) {
            $dateMin = now()->subYear()->toDateString();
            $query->where('date', '>=', $dateMin);
        }

        $creneaux = $query->get()
            ->groupBy(fn($c) => $c->date->isoWeek() . '-' . $c->date->year);

        $evenementsQuery = Evenement::with('tachesBloquees')->orderBy('date_debut');
        if (!$historique) {
            $evenementsQuery->where('date_fin', '>=', now()->subYear()->toDateString());
        }
        $tousEvenements = $evenementsQuery->get();

        // Méthode héritée de PlanningController (visibilité protected).
        $bannièresParSemaine = $this->buildBannièresInformatives($tousEvenements, $creneaux);

        $user = $request->user();
        $peutEditer = $user && ($user->isAdmin() || $user->isGestionnaire());

        $semaines = $creneaux->map(
            fn($creneauxSemaine, $semaineCle) =>
            $this->serializeSemaine($semaineCle, $creneauxSemaine, $bannièresParSemaine)
        )->values();

        return response()->json([
            'semaines' => $semaines,
            'historique' => $historique,
            'peutEditer' => $peutEditer,
        ]);
    }

    /**
     * Sérialise un bloc semaine — équivalent JSON de _week-block.blade.php.
     */
    private function serializeSemaine(string $semaineCle, $creneauxSemaine, array $bannièresParSemaine): array
    {
        $first = $creneauxSemaine->first();
        $last = $creneauxSemaine->last();
        $weekMonday = $first->date->clone()->subDays($first->date->isoWeekday() - 1)->startOfDay();
        $weekSunday = $weekMonday->clone()->addDays(6)->endOfDay();

        // Les créneaux arrivent triés par date DESC (voir data()) : $first
        // est donc la date la PLUS RÉCENTE de la semaine et $last la plus
        // ancienne. Pour le libellé "début — fin", on a besoin de l'ordre
        // chronologique réel, indépendamment du tri de la requête.
        $dateDebutSemaine = $creneauxSemaine->min('date');
        $dateFinSemaine = $creneauxSemaine->max('date');

        $nbTachesActives = $creneauxSemaine->first()?->taches->count() ?? 5;
        $bannièresSemaine = $bannièresParSemaine[$semaineCle] ?? [];

        // Un événement ne "bloque toute la semaine" (badge dans l'en-tête) que
        // s'il est réellement lié à CHAQUE créneau existant de la semaine —
        // pas seulement si sa plage de dates chevauche quelque part la semaine.
        // Sans cette vérification, un événement d'un seul jour bloquant toutes
        // les tâches actives (ex. "Cours annulé" via le bouton du planning)
        // ferait apparaître à tort ce badge pour toute la semaine, alors qu'il
        // ne s'applique qu'à l'un des créneaux (vendredi OU samedi).
        $evtToutBloque = collect($bannièresSemaine)->first(function ($b) use ($creneauxSemaine, $nbTachesActives) {
            if ($b['informatif'] || $b['evenement']->tachesBloquees->count() < $nbTachesActives) {
                return false;
            }
            return $creneauxSemaine->every(
                fn($c) => $c->evenements->contains('id', $b['evenement']->id)
            );
        });

        return [
            'cle' => $semaineCle,
            'numeroSemaine' => $first->semaine,
            'anneeAffichage' => $first->date->year,
            'moisAffichage' => $first->date->month,
            'libelleSemaine' => $dateDebutSemaine->locale('fr')->isoFormat('D MMMM')
                . ' — ' . $dateFinSemaine->locale('fr')->isoFormat('D MMMM YYYY'),
            'lundi' => $weekMonday->toDateString(),
            'dimanche' => $weekSunday->toDateString(),
            'datesExistantes' => $creneauxSemaine->pluck('date')->map(fn($d) => $d->toDateString())->values(),
            'evenementBloquantTotal' => $evtToutBloque ? $evtToutBloque['evenement']->nom : null,
            'bannieres' => collect($bannièresSemaine)->map(fn($b) => BanniereResource::make($b))->values(),
            'creneaux' => $creneauxSemaine->map(fn($c) => CreneauResource::make($c))->values(),
        ];
    }
}