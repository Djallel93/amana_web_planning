<?php
// app/Http/Resources/Planning/CreneauResource.php

declare(strict_types=1);

namespace App\Http\Resources\Planning;

use App\Http\Resources\Personnes\PersonneResource;
use App\Models\Creneau;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un créneau — équivalent JSON d'une ligne <tr> de
 * _week-block.blade.php, consommée par PlanningGrid.vue (voir
 * resources/js/types/planning.ts::CreneauData). Utilisée par
 * PlanningApiController::data().
 *
 * Se calcule entièrement à partir du créneau lui-même (relations
 * taches.tache / taches.personne / evenements.tachesBloquees, déjà
 * eager-loadées par PlanningApiController::data() — voir le `with()` sur
 * la requête Creneau) : contrairement au bloc "semaine" qui l'englobe
 * (voir PlanningApiController::serializeSemaine()), aucun état ne doit
 * être injecté depuis l'extérieur, ce qui en fait un bon candidat pour
 * une Resource "pure".
 *
 * @mixin Creneau
 */
class CreneauResource extends JsonResource
{
    /** Ordre d'affichage fixe des colonnes de tâches — voir TacheCode côté TS. */
    private const CODES_TACHES = ['entree', 'mektaba', 'salle', 'amana_food', 'cours'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Creneau $creneau */
        $creneau = $this->resource;

        $tachesMap = $creneau->taches->keyBy(fn($t) => $t->tache?->code);
        $tachesBloqueesCodes = $creneau->tachesBloqueesCodes();
        $nomEvtBloquants = $creneau->evenements
            ->filter(fn($e) => $e->tachesBloquees->isNotEmpty())
            ->pluck('nom')
            ->implode(', ');
        $nbTaches = $creneau->taches->count();
        $toutBloque = $tachesBloqueesCodes->count() >= $nbTaches && $tachesBloqueesCodes->isNotEmpty();

        $taches = collect(self::CODES_TACHES)->map(function ($code) use ($tachesMap, $tachesBloqueesCodes, $nomEvtBloquants) {
            $ct = $tachesMap->get($code);
            $personne = $ct?->personne;

            return [
                'code' => $code,
                'tacheId' => $ct?->id_tache,
                'bloquee' => $tachesBloqueesCodes->contains($code),
                'evenementBloquant' => $tachesBloqueesCodes->contains($code) ? $nomEvtBloquants : null,
                'personne' => $personne ? PersonneResource::make($personne) : null,
            ];
        });

        return [
            'id' => $creneau->id,
            'date' => $creneau->date->toDateString(),
            'dateLabel' => $creneau->date->locale('fr')->isoFormat('D MMM YYYY'),
            'jour' => $creneau->jour,
            'toutBloque' => $toutBloque,
            'partielBloque' => !$toutBloque && $tachesBloqueesCodes->isNotEmpty(),
            'evenements' => $creneau->evenements->pluck('nom')->implode(', ') ?: null,
            'taches' => $taches,
        ];
    }
}
