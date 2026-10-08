<?php
// app/Services/EvenementRegenerationService.php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\DateHelper;
use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\Creneau;
use App\Models\Evenement;
use App\Notifications\PlanningGenereNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service métier pour la régénération automatique du planning suite à la
 * création, la modification, ou l'import en masse d'un ou plusieurs
 * événements organisationnels.
 *
 * Remplace l'ancienne approche de patch ciblé (EvenementsController::
 * syncCreneauLinks(), retirée) qui mettait à jour un par un les créneaux
 * déjà générés chevauchant la période de l'événement — ce qui imposait un
 * appel Google Calendar SYNCHRONE par tâche désassignée (risque de requête
 * lente/en timeout sur un import de plusieurs événements bloquants).
 *
 * Même mécanisme que AbsenceRegenerationService pour les absences : on
 * régénère le planning depuis la première date déjà générée impactée
 * (SchedulerMain::regenerateFromImpactedDate()). La régénération complète
 * relie NATURELLEMENT chaque événement actif à ses créneaux
 * (SchedulerMain::generateDay() appelle déjà `$creneau->evenements()->
 * syncWithoutDetaching()` pour tout événement actif à la date générée) et
 * applique le véritable algorithme d'équilibrage de RotationEngine sur
 * toute la fenêtre affectée — aussi bien pour un seul événement créé/modifié
 * via le formulaire que pour un lot importé en masse (voir
 * EvenementCsvImporter), un seul appel couvrant tous les événements fournis.
 *
 * Dates PASSÉES (strictement avant aujourd'hui, fuseau Europe/Paris) : jamais de
 * régénération. Les créneaux déjà existants dans ce passé sont seulement RATTACHÉS
 * à l'événement (lierCreneauxPasses() : bannière/lien d'historique), sans toucher
 * à leurs assignations — y compris sur une tâche bloquée par l'événement. Les
 * événements eux-mêmes (calendriers Google) sont synchronisés comme d'habitude
 * par le contrôleur ; aucun créneau passé n'est renvoyé vers Google Calendar.
 *
 * Ne régénère QUE si au moins un créneau déjà généré (date >= aujourd'hui)
 * chevauche la période d'au moins un des événements fournis — que
 * l'événement soit bloquant ou purement informatif : dans les deux cas le
 * créneau existant doit être mis à jour (bannière informative et/ou
 * désassignation des tâches nouvellement bloquées), ce que seule une
 * régénération peut faire correctement (voir docblock de
 * AbsenceRegenerationService::regenererSiNecessaire() pour le détail de
 * pourquoi un patch ciblé ne suffit pas).
 */
class EvenementRegenerationService
{
    public function __construct(
        private readonly SchedulerMain $scheduler,
    ) {}

    /**
     * @param Evenement|iterable<Evenement> $evenements Un événement, ou
     *        plusieurs (import en masse) — la régénération n'est déclenchée
     *        qu'une seule fois pour l'ensemble, à partir de la date la plus
     *        proche impactée parmi tous les événements fournis.
     * @return array{message: string}|null null si aucune régénération n'a été nécessaire
     */
    public function regenererSiNecessaire(Evenement|iterable $evenements): ?array
    {
        $evenements = $evenements instanceof Evenement ? [$evenements] : (is_array($evenements) ? $evenements : iterator_to_array($evenements));

        if (empty($evenements)) {
            return null;
        }

        $nbLies = $this->lierCreneauxPasses($evenements);
        $futur = $this->regenererDatesFutures($evenements);

        if ($nbLies === 0) {
            return $futur;
        }

        $messageLiens = $nbLies === 1
            ? '1 créneau passé rattaché (planning non régénéré : l\'historique est conservé).'
            : "{$nbLies} créneaux passés rattachés (planning non régénéré : l'historique est conservé).";

        return ['message' => $futur === null ? $messageLiens : $messageLiens . ' ' . $futur['message']];
    }

    /**
     * Rattache aux événements fournis les créneaux DÉJÀ EXISTANTS dont la date est
     * strictement passée et comprise dans leur période — et détache ceux qui ne le
     * sont plus (période raccourcie/déplacée à la modification), pour que l'historique
     * reflète toujours la période réelle. Aucune assignation n'est modifiée.
     *
     * @param array<int, Evenement> $evenements
     * @return int Nombre de rattachements créés
     */
    private function lierCreneauxPasses(array $evenements): int
    {
        $veille = DateHelper::aujourdhui()->subDay()->toDateString();
        $nbLies = 0;

        foreach ($evenements as $evenement) {
            $debut = $evenement->date_debut->toDateString();
            $fin = min($evenement->date_fin->toDateString(), $veille);

            if ($fin >= $debut) {
                $ids = Creneau::whereBetween('date', [$debut, $fin])->pluck('id')->all();
                $nbLies += count($evenement->creneaux()->syncWithoutDetaching($ids)['attached']);
            }

            // Créneaux passés encore rattachés alors qu'ils sortent de la période.
            $obsoletes = $evenement->creneaux()
                ->where('plan_creneaux.date', '<=', $veille)
                ->where(fn($q) => $q->where('plan_creneaux.date', '<', $debut)
                    ->orWhere('plan_creneaux.date', '>', $evenement->date_fin->toDateString()))
                ->pluck('plan_creneaux.id')
                ->all();

            if ($obsoletes !== []) {
                $evenement->creneaux()->detach($obsoletes);
            }
        }

        if ($nbLies > 0) {
            Log::info('[EvenementRegenerationService] Créneaux passés rattachés à des événements', [
                'ids_evenements' => array_map(fn(Evenement $e) => $e->id, $evenements),
                'nb' => $nbLies,
            ]);

            audit('update', 'planning', null, null, [
                'declencheur' => 'evenement_passe',
                'ids_evenements' => array_map(fn(Evenement $e) => $e->id, $evenements),
                'nb_creneaux_lies' => $nbLies,
            ]);
        }

        return $nbLies;
    }

    /**
     * @param array<int, Evenement> $evenements
     * @return array{message: string}|null
     */
    private function regenererDatesFutures(array $evenements): ?array
    {
        $premiereDateImpactee = $this->trouverPremiereDateImpactee($evenements);

        if ($premiereDateImpactee === null) {
            return null;
        }

        Log::info('[EvenementRegenerationService] Régénération automatique suite à événement(s)', [
            'nb_evenements' => count($evenements),
            'ids_evenements' => array_map(fn(Evenement $e) => $e->id, $evenements),
            'premiere_date_impactee' => $premiereDateImpactee,
        ]);

        try {
            // Voir SchedulerMain::regenerateFromImpactedDate() pour le détail
            // du recul au vendredi et du calcul du nombre de semaines
            // nécessaires pour ne pas raccourcir l'horizon déjà généré.
            //
            // Sous verrou AVEC attente (VerrouAction) : voir AbsenceRegenerationService.
            $regen = app(VerrouAction::class)->executer(
                VerrouAction::GENERATION_PLANNING,
                fn() => $this->scheduler->regenerateFromImpactedDate(Carbon::parse($premiereDateImpactee)),
                (int) config('planning.verrou.attente_regeneration_auto', 10),
                'une autre génération du planning est en cours',
            );
        } catch (\Throwable $e) {
            Log::error('[EvenementRegenerationService] Échec de la régénération automatique', [
                'error' => $e->getMessage(),
            ]);

            return [
                'message' => "⚠️ La mise à jour automatique du planning a échoué ({$e->getMessage()}) — "
                    . 'veuillez régénérer manuellement depuis Planning > Générer.',
            ];
        }

        audit('generate', 'planning', null, null, array_merge($regen['resultat'], [
            'declencheur' => 'evenement',
            'nb_evenements' => count($evenements),
            'ids_evenements' => array_map(fn(Evenement $e) => $e->id, $evenements),
        ]));

        // Dès la régénération validée et auditée, avant la synchronisation Google : un
        // problème de synchronisation ne doit pas empêcher de prévenir les admins.
        // Ne lève jamais (voir PlanningGenerationNotifier).
        app(PlanningGenerationNotifier::class)->notifierRegeneration(
            PlanningGenereNotification::DECLENCHEUR_EVENEMENT,
            $this->libelleEvenements($evenements),
            $regen,
        );

        $payload = app(WebhookPayloadBuilder::class)->build($regen['dateDebutRegen'], $regen['semaines'], $regen['aPartirDe'] ?? null);
        SynchroniserGoogleCalendar::dispatch($payload, 'post');
        Log::info('[EvenementRegenerationService] Synchronisation Google Calendar dispatchée en queue (POST) suite à régénération automatique.');

        $dateLabel = $regen['regenererDepuis']->locale('fr')->isoFormat('D MMMM YYYY');

        return [
            'message' => "Planning régénéré automatiquement à partir du {$dateLabel} "
                . "({$regen['resultat']['jours_generes']} jours, {$regen['resultat']['non_assignes']} non assigné(s)) "
                . "pour tenir compte de {$this->libelleEvenements($evenements)}.",
        ];
    }

    /**
     * Première date de créneau déjà généré (>= aujourd'hui) qui chevauche
     * la plage d'au moins un des événements fournis — peu importe si
     * l'événement bloque des tâches ou non (voir docblock de classe).
     *
     * @param array<int, Evenement> $evenements
     */
    private function trouverPremiereDateImpactee(array $evenements): ?string
    {
        $aujourdHui = DateHelper::aujourdhui()->toDateString();
        $premiereDateImpactee = null;

        foreach ($evenements as $evenement) {
            $debut = max($evenement->date_debut->toDateString(), $aujourdHui);
            $fin = $evenement->date_fin->toDateString();

            if ($fin < $debut) {
                continue; // Période entièrement passée — jamais régénérée rétroactivement.
            }

            $date = Creneau::whereBetween('date', [$debut, $fin])->min('date');

            if ($date === null) {
                continue;
            }

            if ($premiereDateImpactee === null || $date < $premiereDateImpactee) {
                $premiereDateImpactee = $date;
            }
        }

        return $premiereDateImpactee;
    }

    /**
     * @param array<int, Evenement> $evenements
     */
    private function libelleEvenements(array $evenements): string
    {
        if (count($evenements) === 1) {
            return "l'événement « {$evenements[0]->nom} »";
        }

        return count($evenements) . ' événements importés';
    }
}
