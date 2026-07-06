<?php
// app/Services/AbsenceRegenerationService.php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\EnvoyerWebhookMake;
use App\Models\Absence;
use App\Models\Creneau;
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
 *   - Dispatcher le webhook Make.com si configuré
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

        // Les créneaux ne sont générés que le Vendredi et le Samedi. Si la
        // date impactée est un Samedi, on régénère depuis le Vendredi de la
        // même semaine pour ne pas laisser ce Samedi hors de la fenêtre
        // régénérée (DateHelper::premierVendredi avance uniquement en avant).
        $regenererDepuis = Carbon::parse($premiereDateImpactee);
        if (!$regenererDepuis->isFriday()) {
            $regenererDepuis = $regenererDepuis->copy()->subDay();
        }

        // ── 2. Calculer le nombre de semaines pour couvrir tout l'horizon
        //       déjà généré, afin de ne pas raccourcir le planning existant ──
        $derniereDateGeneree = Creneau::max('date');
        if ($derniereDateGeneree) {
            $dernierVendredi = Carbon::parse($derniereDateGeneree);
            if (!$dernierVendredi->isFriday()) {
                $dernierVendredi = $dernierVendredi->subDay();
            }
        } else {
            // Ne devrait pas arriver : on vient de trouver un créneau assigné
            // ci-dessus, donc au moins un créneau existe forcément.
            $dernierVendredi = $regenererDepuis;
        }

        $semaines = max(1, (int) floor($regenererDepuis->diffInDays($dernierVendredi) / 7) + 1);
        $dateDebutRegen = $regenererDepuis->toDateString();

        Log::info('[AbsenceRegenerationService] Régénération automatique suite à absence', [
            'id_absence' => $absence->id,
            'id_personne' => $absence->id_personne,
            'date_debut_regen' => $dateDebutRegen,
            'semaines' => $semaines,
        ]);

        try {
            $resultat = $this->scheduler->generateSchedule($dateDebutRegen, $semaines);
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

        // ── 3. Alimenter le même mécanisme de rollback que la génération
        //       manuelle, pour que l'admin garde une porte de sortie ────────
        $lastGenerated = $this->scheduler->buildRollbackSnapshot($dateDebutRegen, $semaines);
        session(['last_generated_creneaux' => $lastGenerated]);

        audit('generate', 'planning', null, null, array_merge($resultat, [
            'declencheur' => 'absence',
            'id_absence' => $absence->id,
            'id_personne' => $absence->id_personne,
        ]));

        if (config('services.make.webhook_url')) {
            $payload = app(WebhookPayloadBuilder::class)->build($dateDebutRegen, $semaines);
            EnvoyerWebhookMake::dispatch($payload, 'post');
            Log::info('[AbsenceRegenerationService] Webhook Make.com dispatché en queue (POST) suite à régénération automatique.');
        }

        $dateLabel = $regenererDepuis->locale('fr')->isoFormat('D MMMM YYYY');

        return [
            'message' => "Planning régénéré automatiquement à partir du {$dateLabel} "
                . "({$resultat['jours_generes']} jours, {$resultat['non_assignes']} non assigné(s)) "
                . 'pour tenir compte de cette absence.',
        ];
    }
}
