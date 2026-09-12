<?php
// app/Http/Resources/Bilan/BilanSerieResource.php

declare(strict_types=1);

namespace App\Http\Resources\Bilan;

use App\Models\Bilan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un point de la série statistiques (un jour), pour
 * les graphiques de BilanStatistiques.vue — voir
 * BilanController::statistiquesData().
 *
 * Forme distincte de BilanResource : c'est un point de série temporelle
 * (agrège les deux groupes en totaux + porte le/la responsable de chaque
 * tâche ce jour-là), pas le même objet que le formulaire d'un jour. D'où
 * une Resource séparée plutôt qu'une réutilisation forcée de BilanResource.
 *
 * $responsable est calculé par le contrôleur (BilanController::
 * responsablesParDate(), qui joint plusieurs tables sur toute la période
 * demandée en une seule fois) — le recalculer par instance serait une
 * requête N+1, donc il est injecté ici plutôt que résolu dans toArray().
 */
class BilanSerieResource extends JsonResource
{
    /**
     * @param array{amana_food?: string, mektaba?: string} $responsable
     */
    public function __construct(Bilan $resource, private readonly array $responsable = [])
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Bilan $bilan */
        $bilan = $this->resource;

        $montantCarte = $bilan->montant_carte !== null ? (float) $bilan->montant_carte : null;
        $montantEspece = $bilan->montant_espece !== null ? (float) $bilan->montant_espece : null;

        return [
            'date' => $bilan->date->toDateString(),
            'totalPresence' => $bilan->nb_presents !== null ? $bilan->nb_presents + ($bilan->nb_en_ligne ?? 0) : null,
            'totalMontant' => $montantCarte !== null ? $montantCarte + ($montantEspece ?? 0) : null,
            'nbPresents' => $bilan->nb_presents,
            'nbEnLigne' => $bilan->nb_en_ligne,
            'montantCarte' => $montantCarte,
            'montantEspece' => $montantEspece,
            'responsableAmanaFood' => $this->responsable['amana_food'] ?? null,
            'responsableMektaba' => $this->responsable['mektaba'] ?? null,
        ];
    }
}
