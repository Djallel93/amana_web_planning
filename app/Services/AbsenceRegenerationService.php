<?php
// app/Services/AbsenceRegenerationService.php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\Absence;
use App\Models\CreneauTache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service métier pour la régénération automatique du planning suite à une absence.
 *
 * Responsabilités :
 *   - Détecter si une absence chevauche une assignation future existante
 *   - Régénérer le planning depuis la première date impactée (SchedulerMain)
 *   - Alimenter le mécanisme de rollback (comme une génération manuelle)
 *   - Journaliser l'opération (audit)
 *   - Dispatcher la synchronisation Google Calendar si configurée
 *
 * Extrait de AbsencesController pour séparer l'orchestration (contrôleur)
 * de la logique métier de régénération (service).
 */
class AbsenceRegenerationService
{
    public function __construct(
        private readonly SchedulerMain $scheduler,
    ) {
    }

    /**
     * Régénère automatiquement le planning si l'absence sauvegardée chevauche
     * une date future (>= aujourd'hui) pour laquelle la personne est déjà
     * assignée à une tâche.
     *
     * Pourquoi une régénération complète plutôt qu'une réassignation ciblée
     * du seul créneau concerné : l'équilibrage de RotationEngine (rotation
     * stricte amana_food + score adaptatif pour les autres tâches) est
     * cumulatif et séquentiel — chaque jour assigné met à jour les compteurs
     * utilisés pour départager le jour suivant. Patcher un seul créneau après
     * coup ne répercute pas ce changement sur les créneaux déjà générés
     * après cette date, et ne garantit donc pas une répartition équitable.
     * Régénérer depuis la première date impactée jusqu'à la fin de
     * l'horizon déjà généré applique le véritable algorithme d'équilibrage
     * sur toute la fenêtre affectée.
     *
     * Ne régénère QUE si un créneau est réellement impacté (personne
     * effectivement assignée sur une date de l'absence, future) — une
     * absence qui ne chevauche aucune assignation existante n'a aucun effet.
     *
     * @return array{message: string}|null null si aucune régénération n'a été nécessaire
     */
    public function regenererSiNecessaire(Absence $absence): ?array
    {
        $aujourdHui = now()->toDateString();
        $dateDebutAbsence = $absence->date_debut->toDateString();
        $dateFinAbsence = $absence->date_fin->toDateString();

        // ── 1. Trouver la première date future déjà assignée à cette personne
        //       et couverte par l'absence ─────────────────────────────────
        $premiereDateImpactee = CreneauTache::where('id_personne', $absence->id_personne)
            ->whereHas('creneau', function ($q) use ($dateDebutAbsence, $dateFinAbsence, $aujourdHui) {
                $q->whereBetween('date', [$dateDebutAbsence, $dateFinAbsence])
                    ->where('date', '>=', $aujourdHui);
            })
            ->with('creneau')
            ->get()
            ->min(fn(CreneauTache $ct) => $ct->creneau->date->toDateString());

        if ($premiereDateImpactee === null) {
            return null; // Aucune assignation existante n'est concernée
        }

        Log::info('[AbsenceRegenerationService] Régénération automatique suite à absence', [
            'id_absence' => $absence->id,
            'id_personne' => $absence->id_personne,
            'premiere_date_impactee' => $premiereDateImpactee,
        ]);

        try {
            // Voir SchedulerMain::regenerateFromImpactedDate() pour le détail
            // du recul au vendredi et du calcul du nombre de semaines
            // nécessaires pour ne pas raccourcir l'horizon déjà généré.
            $regen = $this->scheduler->regenerateFromImpactedDate(Carbon::parse($premiereDateImpactee));
        } catch (\Throwable $e) {
            Log::error('[AbsenceRegenerationService] Échec de la régénération automatique', [
                'id_absence' => $absence->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'message' => "⚠️ La réassignation automatique du planning a échoué ({$e->getMessage()}) — "
                    . 'veuillez régénérer manuellement depuis Planning > Générer.',
            ];
        }

        audit('generate', 'planning', null, null, array_merge($regen['resultat'], [
            'declencheur' => 'absence',
            'id_absence' => $absence->id,
            'id_personne' => $absence->id_personne,
        ]));

        $payload = app(WebhookPayloadBuilder::class)->build($regen['dateDebutRegen'], $regen['semaines']);
        SynchroniserGoogleCalendar::dispatch($payload, 'post');
        Log::info('[AbsenceRegenerationService] Synchronisation Google Calendar dispatchée en queue (POST) suite à régénération automatique.');

        $dateLabel = $regen['regenererDepuis']->locale('fr')->isoFormat('D MMMM YYYY');

        return [
            'message' => "Planning régénéré automatiquement à partir du {$dateLabel} "
                . "({$regen['resultat']['jours_generes']} jours, {$regen['resultat']['non_assignes']} non assigné(s)) "
                . 'pour tenir compte de cette absence.',
        ];
    }
}
