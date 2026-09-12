<?php
// app/Http/Resources/CalendrierResource.php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CalendrierGoogle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un CalendrierGoogle pour le dropdown de sélection
 * de calendrier (SearchableSelect.vue) — voir CalendriersController.
 *
 * Ne renvoie que {id, name} : le frontend n'a besoin ni du statut actif
 * (déjà filtré par la requête), ni des colonnes de suivi interne
 * (derniere_verification_at, etc.).
 *
 * @mixin CalendrierGoogle
 */
class CalendrierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->calendar_id,
            'name' => $this->nom,
        ];
    }
}
