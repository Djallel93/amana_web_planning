<?php
// app/Services/WebhookEvenementPayloadBuilder.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Evenement;

/**
 * Construit le payload webhook pour les événements organisationnels.
 *
 * Envoyé vers un scénario Make.com DÉDIÉ (services.make.webhook_url_evenements),
 * distinct de celui du planning (WebhookPayloadBuilder /
 * services.make.webhook_url) — voir EnvoyerWebhookMake::$cible. Comme
 * chaque scénario a sa propre URL, le payload n'a plus besoin de porter de
 * champ `type`/`action` pour se distinguer ou s'auto-décrire : le verbe HTTP
 * (POST/PATCH/DELETE, choisi par l'appelant) et l'URL suffisent à Make.com
 * pour savoir quoi faire.
 *
 * Structure du payload :
 * {
 *   "evenement": {
 *     "nom": "Ramadan",
 *     "date_debut": "2025-03-01",
 *     "date_fin": "2025-03-30",
 *     "description": "...",
 *     "calendar_names": ["AMANA - Événements", "AMANA - Communications"],
 *     "taches_bloquees": ["amana_food", "entree"]  // absent pour delete
 *   }
 * }
 *
 * `nom` + `date_debut`/`date_fin` servent d'identifiant pour que Make.com
 * retrouve l'événement Google Calendar correspondant lors d'une modification
 * ou suppression ultérieure (pas d'`id` interne exposé — non utilisé côté
 * Make.com).
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
                'nom'             => $evenement->nom,
                'date_debut'      => $evenement->date_debut->toDateString(),
                'date_fin'        => $evenement->date_fin->toDateString(),
                'description'     => $evenement->description ?? '',
                'calendar_names'  => $evenement->calendarNames(),
                'taches_bloquees' => $evenement->tachesBloquees->pluck('code')->values()->all(),
            ]),
        ];
    }

    /**
     * Retire récursivement les champs dont la valeur est `null` (et
     * uniquement `null` — les valeurs "fausses" mais significatives comme
     * `false`, `0`, `''` ou `[]` sont conservées). Évite d'envoyer à
     * Make.com des clés dont la valeur ne serait pas exploitable.
     */
    private function sansChampsNull(array $data): array
    {
        return array_map(
            fn($v) => is_array($v) ? $this->sansChampsNull($v) : $v,
            array_filter($data, fn($v) => $v !== null)
        );
    }

    /**
     * Payload pour une suppression d'événement (DELETE).
     * On envoie uniquement de quoi localiser l'événement Google Calendar
     * correspondant (nom + dates + calendriers cibles) — pas de champs
     * dépendant de l'assignation des tâches bloquées.
     */
    public function buildDelete(Evenement $evenement): array
    {
        if (!$evenement->relationLoaded('calendriers')) {
            $evenement->load('calendriers');
        }

        return [
            'evenement' => [
                'nom'            => $evenement->nom,
                'date_debut'     => $evenement->date_debut->toDateString(),
                'date_fin'       => $evenement->date_fin->toDateString(),
                'calendar_names' => $evenement->calendarNames(),
            ],
        ];
    }
}
