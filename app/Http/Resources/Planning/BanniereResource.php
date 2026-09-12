<?php
// app/Http/Resources/Planning/BanniereResource.php

declare(strict_types=1);

namespace App\Http\Resources\Planning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une bannière d'événement affichée en en-tête de
 * semaine (voir PlanningApiController::serializeSemaine() /
 * resources/js/types/planning.ts::BanniereEvenement).
 *
 * Ne wrappe pas un modèle Eloquent : chaque bannière est un tableau
 * ['evenement' => Evenement, 'informatif' => bool, 'debut_semaine' =>
 * Carbon, 'fin_semaine' => Carbon] produit par
 * PlanningController::buildBannièresInformatives() — cette structure
 * n'est pas propre à un seul créneau (une bannière peut couvrir toute une
 * semaine indépendamment des créneaux existants), donc pas un bon candidat
 * pour vivre sur le modèle Evenement lui-même.
 */
class BanniereResource extends JsonResource
{
    /**
     * @param array{evenement: \App\Models\Evenement, informatif: bool, debut_semaine: \Carbon\CarbonInterface, fin_semaine: \Carbon\CarbonInterface} $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $banniere = $this->resource;

        return [
            'nom' => $banniere['evenement']->nom,
            'dateLabel' => $this->formatDateLabel($banniere),
            'informatif' => $banniere['informatif'],
            'tachesBloquees' => $banniere['informatif']
                ? []
                : $banniere['evenement']->tachesBloquees->map(fn($t) => [
                    'code' => $t->code,
                    'libelle' => $t->libelle,
                ])->values(),
        ];
    }

    /**
     * Formate la plage de dates d'une bannière — identique à
     * l'ancien PlanningApiController::formatBanniereDate() /
     * _week-block.blade.php.
     *
     * @param array{debut_semaine: \Carbon\CarbonInterface, fin_semaine: \Carbon\CarbonInterface} $banniere
     */
    private function formatDateLabel(array $banniere): string
    {
        $debutStr = $banniere['debut_semaine']->locale('fr')->isoFormat('D MMM');
        $finStr = $banniere['fin_semaine']->locale('fr')->isoFormat('D MMM');
        $mêmeJour = $banniere['debut_semaine']->isSameDay($banniere['fin_semaine']);

        return $mêmeJour ? $debutStr : "{$debutStr} – {$finStr}";
    }
}
