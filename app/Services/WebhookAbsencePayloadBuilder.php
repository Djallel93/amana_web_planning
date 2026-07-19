<?php
// app/Services/WebhookAbsencePayloadBuilder.php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\GoogleCalendarColors;
use App\Models\Absence;
use App\Models\Setting;

/**
 * Construit le payload consommé par SynchroniserGoogleCalendar pour les
 * absences (cible `absence`).
 *
 * Une absence se synchronise comme un événement Google Calendar "journée
 * entière" (comme les événements organisationnels — voir
 * WebhookEvenementPayloadBuilder), mais avec deux différences volontaires :
 *   - un SEUL calendrier cible possible (paramètre `calendar_absence`),
 *     pas une liste — une absence individuelle n'a pas vocation à être
 *     diffusée sur plusieurs calendriers comme un événement organisationnel ;
 *   - une couleur FIXE (GoogleCalendarColors::ABSENCE, gris), non
 *     configurable — voir point 4 du ticket.
 *
 * Structure du payload :
 * {
 *   "absence": {
 *     "id_absence": 42,
 *     "nom": "Absence — Prénom Nom",
 *     "date_debut": "2026-08-01",
 *     "date_fin": "2026-08-10",
 *     "description": "Raison éventuelle...",
 *     "couleur": "8",
 *     "calendar_id": "xxxx@group.calendar.google.com"  // absent si calendar_absence vide
 *   }
 * }
 *
 * `id_absence` permet à GoogleCalendarPayloadMapper/SynchroniserGoogleCalendar
 * de retrouver/mettre à jour la ligne plan_absences correspondante
 * (google_calendar_id/google_event_id), sans résolution par nom + date.
 */
class WebhookAbsencePayloadBuilder
{
    /**
     * Payload pour une création ou modification d'absence (POST/PATCH).
     * Retourne un payload avec `calendar_id` absent si aucun calendrier
     * n'est configuré (`calendar_absence` vide) — l'appelant doit vérifier
     * hasCalendarSync() avant de dispatcher, comme pour les événements.
     */
    public function buildUpsert(Absence $absence): array
    {
        if (!$absence->relationLoaded('personne')) {
            $absence->load('personne');
        }

        $personne = $absence->personne;
        $nomPersonne = $personne ? trim($personne->prenom . ' ' . $personne->nom) : 'Personne inconnue';

        return [
            'absence' => $this->sansChampsNull([
                'id_absence'  => $absence->id,
                'nom'         => "Absence — {$nomPersonne}",
                'date_debut'  => $absence->date_debut->toDateString(),
                'date_fin'    => $absence->date_fin->toDateString(),
                'description' => $absence->raison ?: null,
                'couleur'     => GoogleCalendarColors::ABSENCE,
                'calendar_id' => $this->calendarId(),
            ]),
        ];
    }

    /**
     * Payload pour une suppression d'absence (DELETE). `id_absence` suffit
     * à GoogleCalendarPayloadMapper pour retrouver l'event_id Google
     * Calendar à supprimer (via plan_absences.google_event_id).
     *
     * ⚠️ À appeler AVANT la suppression effective en base (voir
     * AbsencesController::destroy()) — plan_absences doit encore exister
     * pour connaître son google_calendar_id/google_event_id.
     */
    public function buildDelete(Absence $absence): array
    {
        return [
            'absence' => $this->sansChampsNull([
                'id_absence'  => $absence->id,
                'calendar_id' => $absence->google_calendar_id,
            ]),
        ];
    }

    /**
     * Retourne true si un calendrier cible est configuré pour les absences
     * — même rôle que Evenement::hasCalendarSync(), mais un seul calendrier
     * possible ici (paramètre unique, pas de table pivot).
     */
    public function hasCalendarSync(): bool
    {
        return !empty($this->calendarId());
    }

    // ── Private ───────────────────────────────────────────────────────────

    private function calendarId(): ?string
    {
        $valeur = Setting::get('calendar_absence', 'planning');
        return $valeur !== null && $valeur !== '' ? (string) $valeur : null;
    }

    private function sansChampsNull(array $data): array
    {
        return array_filter($data, fn($v) => $v !== null);
    }
}
