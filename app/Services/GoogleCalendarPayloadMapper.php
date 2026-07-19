<?php
// app/Services/GoogleCalendarPayloadMapper.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tache;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Aplati les payloads produits par WebhookPayloadBuilder /
 * WebhookEvenementPayloadBuilder (structure imbriquée `creneaux[].taches[]`
 * ou `evenement{}`) en une liste plate
 * d'opérations Google Calendar unitaires, consommée par
 * SynchroniserGoogleCalendar.
 *
 * Ce mapper est le SEUL endroit qui connaît la forme du payload — les
 * builders eux-mêmes restent inchangés dans leur logique métier (dates,
 * offsets, assignations), comme convenu : on les enrichit juste de `code` /
 * `id_planning` / `id_evenement` pour que ce mapping soit possible.
 *
 * Une "opération" = un (identifiant DB, calendrier cible) → un événement
 * Google Calendar à créer/mettre à jour/supprimer. `scope` indique quelle
 * table de suivi consulter côté Job (plan_calendrier_evenements pour
 * 'planning', ref_evenements_calendriers pour 'evenement').
 *
 * @phpstan-type GoogleCalendarOperation array{
 *     scope: 'planning'|'evenement',
 *     id_planning?: int,
 *     id_tache?: int,
 *     code?: string,
 *     id_evenement?: int,
 *     calendar_id: string,
 *     summary: string,
 *     description: string|null,
 *     start: string|null,
 *     end: string|null,
 *     date_debut: string|null,
 *     date_fin: string|null,
 * }
 */
class GoogleCalendarPayloadMapper
{
    /** @var Collection<string, int>|null Cache code → id_tache pour la durée d'un mapping. */
    private ?Collection $tacheIdsParCode = null;

    /**
     * Convertit un payload 'planning' (creneaux[]) en liste d'opérations.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mapPlanning(array $payload): array
    {
        $operations = [];

        foreach ($payload['creneaux'] ?? [] as $creneauEntry) {
            $idPlanning = $creneauEntry['id_planning'] ?? null;
            if ($idPlanning === null) {
                continue; // Ligne malformée — rien à rattacher côté DB, on l'ignore.
            }

            foreach (['taches', 'evenements_speciaux', 'evenements_sociaux'] as $groupe) {
                foreach ($creneauEntry[$groupe] ?? [] as $ligne) {
                    $operations = array_merge(
                        $operations,
                        $this->mapPlanningLigne((int) $idPlanning, $creneauEntry['date'] ?? null, $ligne)
                    );
                }
            }
        }

        return $operations;
    }

    /**
     * Convertit un payload 'evenement' ({evenement: {...}}) en liste
     * d'opérations. Un événement organisationnel est "journée entière" —
     * pas d'heure_debut/heure_fin, seulement date_debut/date_fin.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mapEvenement(array $payload): array
    {
        $evenement = $payload['evenement'] ?? null;
        if (!$evenement || !isset($evenement['id_evenement'])) {
            return [];
        }

        $operations = [];

        foreach ($evenement['calendar_ids'] ?? [] as $calendarId) {
            if (!$calendarId) {
                continue;
            }

            $operations[] = [
                'scope' => 'evenement',
                'id_evenement' => (int) $evenement['id_evenement'],
                'calendar_id' => $calendarId,
                'summary' => $evenement['nom'] ?? '',
                'description' => $evenement['description'] ?? null,
                'date_debut' => $evenement['date_debut'] ?? null,
                // Google Calendar : la date de fin d'un événement all-day
                // est EXCLUSIVE — +1 jour par rapport à date_fin (inclusive
                // côté métier) pour que le dernier jour s'affiche bien.
                'date_fin' => isset($evenement['date_fin'])
                    ? Carbon::parse($evenement['date_fin'])->addDay()->toDateString()
                    : null,
            ];
        }

        return $operations;
    }

    // ── Private ───────────────────────────────────────────────────────────

    private function mapPlanningLigne(int $idPlanning, ?string $date, array $ligne): array
    {
        $code = $ligne['code'] ?? null;
        if (!$code || !$date) {
            return [];
        }

        $idTache = $this->resolveTacheId($code);
        if ($idTache === null) {
            return []; // Code inconnu de ref_taches — rien à faire (ne devrait pas arriver).
        }

        $operations = [];

        foreach ($ligne['calendar_ids'] ?? [] as $calendarId) {
            if (!$calendarId) {
                continue;
            }

            $operations[] = [
                'scope' => 'planning',
                'id_planning' => $idPlanning,
                'id_tache' => $idTache,
                'code' => $code,
                'calendar_id' => $calendarId,
                'summary' => $ligne['nom'] ?? $code,
                'description' => $this->buildDescription($ligne),
                'start' => isset($ligne['heure_debut'])
                    ? Carbon::parse("{$date} {$ligne['heure_debut']}", 'Europe/Paris')->toIso8601String()
                    : null,
                'end' => isset($ligne['heure_fin'])
                    ? Carbon::parse("{$date} {$ligne['heure_fin']}", 'Europe/Paris')->toIso8601String()
                    : null,
            ];
        }

        return $operations;
    }

    /**
     * Construit la description de l'événement Google Calendar en combinant
     * le texte de référence (ref_taches.description_calendrier) et la
     * personne assignée, quand disponible (ligneAvecAssignation uniquement
     * — ligneSuppression ne porte pas ces champs).
     */
    private function buildDescription(array $ligne): ?string
    {
        $parts = [];

        if (!empty($ligne['description'])) {
            $parts[] = $ligne['description'];
        }

        if (array_key_exists('assigne', $ligne)) {
            $parts[] = $ligne['assigne'] ? "Assigné(e) : {$ligne['assigne']}" : 'Non assigné(e)';
        }

        return $parts ? implode("\n\n", $parts) : null;
    }

    private function resolveTacheId(string $code): ?int
    {
        if ($this->tacheIdsParCode === null) {
            $this->tacheIdsParCode = Tache::query()->pluck('id', 'code');
        }

        $id = $this->tacheIdsParCode->get($code);
        return $id !== null ? (int) $id : null;
    }
}
