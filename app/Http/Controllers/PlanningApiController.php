<?php
// app/Http/Controllers/PlanningApiController.php

declare(strict_types=1);

namespace App\Http\Controllers;

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

        $semaines = $creneaux->map(fn($creneauxSemaine, $semaineCle) =>
            $this->serializeSemaine($semaineCle, $creneauxSemaine, $bannièresParSemaine)
        )->values();

        return response()->json([
            'semaines'    => $semaines,
            'historique'  => $historique,
            'peutEditer'  => $peutEditer,
        ]);
    }

    /**
     * Sérialise un bloc semaine — équivalent JSON de _week-block.blade.php.
     */
    private function serializeSemaine(string $semaineCle, $creneauxSemaine, array $bannièresParSemaine): array
    {
        $first      = $creneauxSemaine->first();
        $last       = $creneauxSemaine->last();
        $weekMonday = $first->date->clone()->subDays($first->date->isoWeekday() - 1)->startOfDay();
        $weekSunday = $weekMonday->clone()->addDays(6)->endOfDay();

        $nbTachesActives = $creneauxSemaine->first()?->taches->count() ?? 5;
        $bannièresSemaine = $bannièresParSemaine[$semaineCle] ?? [];

        $evtToutBloque = collect($bannièresSemaine)->first(
            fn($b) => !$b['informatif'] && $b['evenement']->tachesBloquees->count() >= $nbTachesActives
        );

        return [
            'cle'              => $semaineCle,
            'numeroSemaine'    => $first->semaine,
            'anneeAffichage'   => $first->date->year,
            'moisAffichage'    => $first->date->month,
            'libelleSemaine'   => $first->date->locale('fr')->isoFormat('D MMMM')
                . ' — ' . $last->date->locale('fr')->isoFormat('D MMMM YYYY'),
            'lundi'            => $weekMonday->toDateString(),
            'dimanche'         => $weekSunday->toDateString(),
            'datesExistantes'  => $creneauxSemaine->pluck('date')->map(fn($d) => $d->toDateString())->values(),
            'evenementBloquantTotal' => $evtToutBloque ? $evtToutBloque['evenement']->nom : null,
            'bannieres'        => collect($bannièresSemaine)->map(fn($b) => [
                'nom'          => $b['evenement']->nom,
                'dateLabel'    => $this->formatBanniereDate($b),
                'informatif'   => $b['informatif'],
                'tachesBloquees' => $b['informatif']
                    ? []
                    : $b['evenement']->tachesBloquees->map(fn($t) => [
                        'code'    => $t->code,
                        'libelle' => $t->libelle,
                    ])->values(),
            ])->values(),
            'creneaux'         => $creneauxSemaine->map(fn($c) => $this->serializeCreneau($c))->values(),
        ];
    }

    /**
     * Sérialise un créneau — équivalent JSON d'une ligne <tr> de _week-block.
     */
    private function serializeCreneau(Creneau $c): array
    {
        $tachesMap           = $c->taches->keyBy(fn($t) => $t->tache?->code);
        $tachesBloqueesCodes = $c->tachesBloqueesCodes();
        $nomEvtBloquants     = $c->evenements
            ->filter(fn($e) => $e->tachesBloquees->isNotEmpty())
            ->pluck('nom')
            ->implode(', ');
        $nbTaches  = $c->taches->count();
        $toutBloque = $tachesBloqueesCodes->count() >= $nbTaches && $tachesBloqueesCodes->isNotEmpty();

        $taches = collect(['entree', 'mektaba', 'salle', 'amana_food', 'cours'])->map(function ($code) use (
            $tachesMap, $tachesBloqueesCodes, $nomEvtBloquants
        ) {
            $ct       = $tachesMap->get($code);
            $personne = $ct?->personne;

            return [
                'code'       => $code,
                'tacheId'    => $ct?->id_tache,
                'bloquee'    => $tachesBloqueesCodes->contains($code),
                'evenementBloquant' => $tachesBloqueesCodes->contains($code) ? $nomEvtBloquants : null,
                'personne'   => $personne ? [
                    'id'    => $personne->id,
                    'label' => $personne->prenom . ' ' . $personne->nom,
                ] : null,
            ];
        });

        return [
            'id'        => $c->id,
            'date'      => $c->date->toDateString(),
            'dateLabel' => $c->date->locale('fr')->isoFormat('D MMM YYYY'),
            'jour'      => $c->jour,
            'toutBloque' => $toutBloque,
            'partielBloque' => !$toutBloque && $tachesBloqueesCodes->isNotEmpty(),
            'evenements' => $c->evenements->pluck('nom')->implode(', ') ?: null,
            'taches'    => $taches,
        ];
    }

    /**
     * Formate la plage de dates d'une bannière, identique à _week-block.blade.php.
     */
    private function formatBanniereDate(array $bannière): string
    {
        $debutStr  = $bannière['debut_semaine']->locale('fr')->isoFormat('D MMM');
        $finStr    = $bannière['fin_semaine']->locale('fr')->isoFormat('D MMM');
        $mêmeJour  = $bannière['debut_semaine']->isSameDay($bannière['fin_semaine']);

        return $mêmeJour ? $debutStr : "{$debutStr} – {$finStr}";
    }
}
