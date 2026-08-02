<?php
// app/Services/WebhookEvenementPayloadBuilder.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Evenement;

/**
 * Construit le payload consommé par SynchroniserGoogleCalendar pour les
 * événements organisationnels (cible `evenement`).
 *
 * Structure du payload :
 * {
 *   "evenement": {
 *     "id_evenement": 12,
 *     "nom": "Ramadan",
 *     "date_debut": "2025-03-01",
 *     "date_fin": "2025-03-30",
 *     "description": "...",
 *     "couleur": "10",  // colorId Google Calendar, absent si non renseigné
 *     "calendar_ids": ["abc123@group.calendar.google.com", "..."],
 *     "taches_bloquees": ["amana_food", "entree"]  // absent pour delete
 *   }
 * }
 *
 * `id_evenement` permet à GoogleCalendarPayloadMapper de retrouver/mettre à
 * jour la ligne ref_evenements_calendriers correspondante (event_id exact) —
 * pas de résolution par nom + date.
 * `calendar_ids` contient des identifiants Google Calendar (calendarId), pas
 * des noms — voir Evenement::calendarIds().
 */
class WebhookEvenementPayloadBuilder
{
    /**
     * Payload pour une création ou modification d'événement (POST/PATCH).
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
            'evenement' => $this->sansChampsNull([
                'id_evenement'    => $evenement->id,
                'nom'             => $evenement->nom,
                'date_debut'      => $evenement->date_debut->toDateString(),
                'date_fin'        => $evenement->date_fin->toDateString(),
                'description'     => $evenement->description ?? '',
                'couleur'         => $evenement->couleur,
                'calendar_ids'    => $evenement->calendarIds(),
                'taches_bloquees' => $evenement->tachesBloquees->pluck('code')->values()->all(),
            ]),
        ];
    }

    /**
     * Retire récursivement les champs dont la valeur est `null` (et
     * uniquement `null` — les valeurs "fausses" mais significatives comme
     * `false`, `0`, `''` ou `[]` sont conservées). Évite d'envoyer à
     * l'API Google Calendar des clés dont la valeur ne serait pas exploitable.
     */
    private function sansChampsNull(array $data): array
    {
        return array_map(
            fn($v) => is_array($v) ? $this->sansChampsNull($v) : $v,
            array_filter($data, fn($v) => $v !== null)
        );
    }

    /**
     * Payload pour une suppression d'événement (DELETE). `id_evenement`
     * suffit à GoogleCalendarPayloadMapper pour retrouver les event_id
     * Google Calendar à supprimer — les autres champs sont conservés pour
     * le logging/l'audit uniquement.
     */
    public function buildDelete(Evenement $evenement): array
    {
        if (!$evenement->relationLoaded('calendriers')) {
            $evenement->load('calendriers');
        }

        return [
            'evenement' => [
                'id_evenement'  => $evenement->id,
                'nom'           => $evenement->nom,
                'date_debut'    => $evenement->date_debut->toDateString(),
                'date_fin'      => $evenement->date_fin->toDateString(),
                'calendar_ids'  => $evenement->calendarIds(),
            ],
        ];
    }
}
