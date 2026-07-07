<?php
// app/Services/WebhookEvenementPayloadBuilder.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Evenement;

/**
 * Construit le payload webhook pour les événements organisationnels.
 *
 * Distinct de WebhookPayloadBuilder (qui gère les créneaux du planning).
 * Make.com distingue les deux types de payload via le champ `type`.
 *
 * Actions supportées :
 *   - "upsert" : création ou modification d'un événement
 *   - "delete" : suppression d'un événement
 *
 * Structure du payload :
 * {
 *   "type": "evenement",
 *   "action": "upsert" | "delete",
 *   "genere_le": "<ISO8601>",
 *   "evenement": {
 *     "id": 42,
 *     "nom": "Ramadan",
 *     "date_debut": "2025-03-01",
 *     "date_fin": "2025-03-30",
 *     "description": "...",
 *     "calendar_names": ["AMANA - Événements", "AMANA - Communications"],
 *     "taches_bloquees": ["amana_food", "entree"]  // absent pour delete
 *   }
 * }
 */
class WebhookEvenementPayloadBuilder
{
    /**
     * Payload pour une création ou modification d'événement.
     */
    public function buildUpsert(Evenement $evenement): array
    {
        // S'assurer que les relations sont chargées
        if (!$evenement->relationLoaded('tachesBloquees')) {
            $evenement->load('tachesBloquees');
        }
        if (!$evenement->relationLoaded('calendriers')) {
            $evenement->load('calendriers');
        }

        return [
            'type'       => 'evenement',
            'action'     => 'upsert',
            'genere_le'  => now()->toIso8601String(),
            'evenement'  => [
                'id'              => $evenement->id,
                'nom'             => $evenement->nom,
                'date_debut'      => $evenement->date_debut->toDateString(),
                'date_fin'        => $evenement->date_fin->toDateString(),
                'description'     => $evenement->description ?? '',
                'calendar_names'  => $evenement->calendarNames(),
                'taches_bloquees' => $evenement->tachesBloquees->pluck('code')->values()->all(),
                'informatif'      => $evenement->tachesBloquees->isEmpty(),
            ],
        ];
    }

    /**
     * Payload pour une suppression d'événement.
     * On envoie uniquement l'identifiant et le nom pour que Make.com
     * puisse retrouver et supprimer l'événement Google Calendar correspondant.
     */
    public function buildDelete(Evenement $evenement): array
    {
        if (!$evenement->relationLoaded('calendriers')) {
            $evenement->load('calendriers');
        }

        return [
            'type'      => 'evenement',
            'action'    => 'delete',
            'genere_le' => now()->toIso8601String(),
            'evenement' => [
                'id'             => $evenement->id,
                'nom'            => $evenement->nom,
                'date_debut'     => $evenement->date_debut->toDateString(),
                'date_fin'       => $evenement->date_fin->toDateString(),
                'calendar_names' => $evenement->calendarNames(),
            ],
        ];
    }
}
