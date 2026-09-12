<?php
// app/Http/Resources/Personnes/PersonneResource.php

declare(strict_types=1);

namespace App\Http\Resources\Personnes;

use App\Models\Personne;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON minimale d'une Personne pour les listes de
 * sélection du planning (ex. le SearchableSelect d'assignation dans
 * PlanningGrid.vue) — voir PlanningEditController::personnes().
 *
 * `label` combine prénom + nom côté serveur plutôt que de laisser le
 * frontend recomposer l'affichage à partir de deux champs séparés.
 *
 * @mixin Personne
 */
class PersonneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->prenom . ' ' . $this->nom,
        ];
    }
}
