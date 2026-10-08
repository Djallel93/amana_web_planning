<?php
// app/Services/AbsenceRegenerationService.php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\DateHelper;
use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\Absence;
use App\Models\CreneauTache;
use App\Notifications\PlanningGenereNotification;
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
 * Dates passées (strictement avant aujourd'hui, fuseau Europe/Paris) : jamais de
 * régénération — la personne est seulement retirée des tâches qu'elle avait ces
 * jours-là (desassignerDatesPassees()), sans synchronisation Google Calendar : le
 * passé ne génère aucun trafic vers les calendriers.
 *
 * Extrait de AbsencesController pour séparer l'orchestration (contrôleur)
 * de la logique métier de régénération (service).
 */
class AbsenceRegenerationService
{
    public function __construct(
        private readonly SchedulerMain $scheduler,
    ) {}

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
     * @return array{message: string, regenere: bool}|null null si aucune régénération n'a été nécessaire
     */
    private function regenererDatesFutures(Absence $absence): ?array
    {
        $aujourdHui = DateHelper::aujourdhui()->toDateString();
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
            //
            // Sous verrou AVEC attente (VerrouAction) : l'absence est déjà enregistrée,
            // on laisse donc une génération en cours se terminer plutôt que de
            // renoncer ou de régénérer en parallèle. Au-delà du délai, l'échec est
            // rattrapé ci-dessous (message « régénérez manuellement »).
            $regen = app(VerrouAction::class)->executer(
                VerrouAction::GENERATION_PLANNING,
                fn() => $this->scheduler->regenerateFromImpactedDate(Carbon::parse($premiereDateImpactee)),
                (int) config('planning.verrou.attente_regeneration_auto', 10),
                'une autre génération du planning est en cours',
            );
        } catch (\Throwable $e) {
            Log::error('[AbsenceRegenerationService] Échec de la régénération automatique', [
                'id_absence' => $absence->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'message' => "⚠️ La réassignation automatique du planning a échoué ({$e->getMessage()}) — "
                    . 'veuillez régénérer manuellement depuis Planning > Générer.',
                'regenere' => false,
            ];
        }

        audit('generate', 'planning', null, null, array_merge($regen['resultat'], [
            'declencheur' => 'absence',
            'id_absence' => $absence->id,
            'id_personne' => $absence->id_personne,
        ]));

        // Dès la régénération validée et auditée, avant la synchronisation Google : un
        // problème de synchronisation ne doit pas empêcher de prévenir les admins.
        // Ne lève jamais (voir PlanningGenerationNotifier).
        app(PlanningGenerationNotifier::class)->notifierRegeneration(
            PlanningGenereNotification::DECLENCHEUR_ABSENCE,
            $this->libelleAbsence($absence),
            $regen,
        );

        $payload = app(WebhookPayloadBuilder::class)->build($regen['dateDebutRegen'], $regen['semaines'], $regen['aPartirDe'] ?? null);
        SynchroniserGoogleCalendar::dispatch($payload, 'post');
        Log::info('[AbsenceRegenerationService] Synchronisation Google Calendar dispatchée en queue (POST) suite à régénération automatique.');

        $dateLabel = $regen['regenererDepuis']->locale('fr')->isoFormat('D MMMM YYYY');

        return [
            'message' => "Planning régénéré automatiquement à partir du {$dateLabel} "
                . "({$regen['resultat']['jours_generes']} jours, {$regen['resultat']['non_assignes']} non assigné(s)) "
                . 'pour tenir compte de cette absence.',
            'regenere' => true,
        ];
    }

    /** Complète « suite à … » dans l'email de génération. N'y met jamais la raison de l'absence (donnée sensible). */
    private function libelleAbsence(Absence $absence): string
    {
        $personne = $absence->personne;
        $periode = 'du ' . $absence->date_debut->copy()->locale('fr')->isoFormat('D MMM YYYY')
            . ' au ' . $absence->date_fin->copy()->locale('fr')->isoFormat('D MMM YYYY');

        return $personne
            ? "l'absence de {$personne->prenom} {$personne->nom} ({$periode})"
            : "une absence ({$periode})";
    }

    /**
     * Point d'entrée unique pour le contrôleur : retire d'abord la personne des
     * tâches PASSÉES couvertes par l'absence, puis régénère si une assignation
     * future est impactée. Les deux effets sont indépendants (une absence à cheval
     * sur aujourd'hui produit les deux).
     *
     * @return array{message: string, regenere: bool}|null null si l'absence n'a eu aucun effet sur le planning
     */
    public function regenererSiNecessaire(Absence $absence): ?array
    {
        $nbPassees = $this->desassignerDatesPassees($absence);
        $futur = $this->regenererDatesFutures($absence);

        if ($nbPassees === 0) {
            return $futur;
        }

        $messagePasse = $nbPassees === 1
            ? '1 affectation passée retirée (planning non régénéré : l\'historique est conservé).'
            : "{$nbPassees} affectations passées retirées (planning non régénéré : l'historique est conservé).";

        return [
            'message' => $futur === null ? $messagePasse : $messagePasse . ' ' . $futur['message'],
            'regenere' => $futur['regenere'] ?? false,
        ];
    }

    /**
     * Retire la personne absente des tâches qu'elle avait sur des dates strictement
     * antérieures à aujourd'hui (fuseau Europe/Paris) couvertes par l'absence. Ne
     * régénère rien, ne touche à aucun autre créneau/tâche et ne synchronise pas
     * Google Calendar. Journalisé (un enregistrement d'audit par appel).
     *
     * Mise à jour par clé composite (id_planning, id_tache) : jamais ->save() sur
     * une instance de CreneauTache (clé primaire composite, $primaryKey = null).
     *
     * @return int Nombre d'assignations passées retirées
     */
    public function desassignerDatesPassees(Absence $absence): int
    {
        $veille = DateHelper::aujourdhui()->subDay()->toDateString();
        $debut = $absence->date_debut->toDateString();
        $fin = min($absence->date_fin->toDateString(), $veille);

        if ($fin < $debut) {
            return 0; // Aucune date de l'absence n'est passée.
        }

        $lignes = CreneauTache::where('id_personne', $absence->id_personne)
            ->whereHas('creneau', fn($q) => $q->whereBetween('date', [$debut, $fin]))
            ->with(['creneau', 'tache'])
            ->get();

        if ($lignes->isEmpty()) {
            return 0;
        }

        $retirees = [];
        foreach ($lignes as $ligne) {
            CreneauTache::where('id_planning', $ligne->id_planning)
                ->where('id_tache', $ligne->id_tache)
                ->where('id_personne', $absence->id_personne)
                ->update(['id_personne' => null]);

            $retirees[] = [
                'date' => $ligne->creneau->date->toDateString(),
                'tache' => $ligne->tache?->code,
            ];
        }

        Log::info('[AbsenceRegenerationService] Assignations passées retirées suite à absence', [
            'id_absence' => $absence->id,
            'id_personne' => $absence->id_personne,
            'nb' => count($retirees),
        ]);

        audit('update', 'planning', null, ['assignations_retirees' => $retirees], [
            'declencheur' => 'absence_passee',
            'id_absence' => $absence->id,
            'id_personne' => $absence->id_personne,
            'nb_retirees' => count($retirees),
        ]);

        return count($retirees);
    }
}
