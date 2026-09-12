<?php
// app/Services/EvenementRegenerationService.php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\Creneau;
use App\Models\Evenement;
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
    ) {
    }

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
            $regen = $this->scheduler->regenerateFromImpactedDate(Carbon::parse($premiereDateImpactee));
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

        $payload = app(WebhookPayloadBuilder::class)->build($regen['dateDebutRegen'], $regen['semaines']);
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
        $aujourdHui = now()->toDateString();
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
