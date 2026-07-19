<?php
// app/Jobs/SynchroniserGoogleCalendar.php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Absence;
use App\Models\CalendrierEvenement;
use App\Models\EvenementCalendrier;
use App\Services\GoogleCalendarPayloadMapper;
use App\Services\GoogleCalendarService;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Synchronise directement avec l'API Google Calendar, avec une signature
 * d'appel (payload, method, cible) volontairement stable dans le temps
 * aux 8 sites d'appel existants.
 *
 * $method ('post'|'patch'|'delete') pilote deux choses :
 *   1. Le mode de dispatch au niveau des sites d'appel (voir chaque
 *      Controller/Service) : 'delete' → ::dispatchSync() (exécution
 *      immédiate, SYNCHRONE), 'post'/'patch' → ::dispatch() (queue).
 *      Raison : les 3 sites d'appel qui envoient un DELETE le font
 *      systématiquement AVANT de supprimer l'entité (Creneau/Evenement)
 *      concernée en base — plan_calendrier_evenements et
 *      ref_evenements_calendriers ont un onDelete('cascade') sur cette
 *      entité. Si le DELETE Google Calendar restait en queue, la ligne de
 *      suivi portant le google_event_id aurait déjà disparu (cascade) au
 *      moment où le Job s'exécuterait réellement — donc plus moyen de
 *      savoir QUEL événement Google Calendar supprimer. En passant par
 *      dispatchSync(), le Job lit la ligne de suivi (et donc l'event_id)
 *      PENDANT qu'elle existe encore, avant que l'appelant ne déclenche la
 *      suppression en cascade.
 *   2. Le comportement métier dans handle() : 'post'/'patch' sont tous
 *      deux traités comme un UPSERT (créer si aucune ligne de suivi/event_id
 *      connu, sinon patcher l'événement existant) — plus robuste que de
 *      suivre le verbe à la lettre, et évite les doublons d'événements en
 *      cas de régénération sur un créneau déjà synchronisé.
 */
class SynchroniserGoogleCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Nombre de tentatives en cas d'échec (appels queue uniquement). */
    public int $tries = 3;

    /** Délai entre les tentatives (secondes). */
    public int $backoff = 60;

    private const METHODES_AUTORISEES = ['post', 'patch', 'delete'];
    private const CIBLES_AUTORISEES = ['planning', 'evenement', 'absence'];

    public function __construct(
        private readonly array $payload,
        private readonly string $method = 'post',
        private readonly string $cible = 'planning',
    ) {
    }

    public function handle(GoogleCalendarService $google, GoogleCalendarPayloadMapper $mapper): void
    {
        if (!$google->isConfigured()) {
            Log::warning('[SynchroniserGoogleCalendar] GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 non configurée — synchronisation ignorée.', [
                'cible' => $this->cible,
            ]);
            return;
        }

        $cible = in_array($this->cible, self::CIBLES_AUTORISEES, true) ? $this->cible : 'planning';
        $methode = in_array($this->method, self::METHODES_AUTORISEES, true) ? $this->method : 'post';

        $operations = match ($cible) {
            'evenement' => $mapper->mapEvenement($this->payload),
            'absence' => $mapper->mapAbsence($this->payload),
            default => $mapper->mapPlanning($this->payload),
        };

        if (empty($operations)) {
            Log::info('[SynchroniserGoogleCalendar] Aucune opération à synchroniser (0 calendrier configuré).', [
                'cible' => $cible,
                'method' => strtoupper($methode),
            ]);
            return;
        }

        $erreurs = 0;

        foreach ($operations as $operation) {
            try {
                $methode === 'delete'
                    ? $this->supprimer($google, $operation)
                    : $this->upsert($google, $operation);
            } catch (\Throwable $e) {
                $erreurs++;
                Log::error('[SynchroniserGoogleCalendar] Échec sur une opération.', [
                    'operation' => $operation,
                    'method' => strtoupper($methode),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $resume = [
            'cible' => $cible,
            'method' => strtoupper($methode),
            'nb_operations' => count($operations),
            'nb_erreurs' => $erreurs,
        ];

        if ($erreurs > 0) {
            Log::error('[SynchroniserGoogleCalendar] Synchronisation terminée avec erreurs.', $resume);

            // On lève directement l'exception plutôt que d'appeler
            // $this->fail() : Laravel intercepte déjà toute exception
            // sortant de handle() pour piloter tries/backoff côté queue
            // (::dispatch), ET la propage normalement à l'appelant côté
            // ::dispatchSync() (utilisé pour tous les DELETE — voir
            // docblock de la classe) — un comportement uniforme dans les
            // deux modes de dispatch, contrairement à $this->fail() dont
            // le comportement dépend de la présence d'un job de queue
            // sous-jacent (absent en dispatchSync()).
            throw new \RuntimeException(
                "Synchronisation Google Calendar échouée sur {$erreurs}/{$resume['nb_operations']} opération(s) (cible={$cible}, method={$methode})."
            );
        }

        Log::info('[SynchroniserGoogleCalendar] Synchronisation réussie.', $resume);

        // Action 'webhook' conservée telle quelle (voir docs/Schema_bdd.md,
        // liste des valeurs `audit_logs.action`) — renommer casserait tout
        // filtrage/dashboard existant basé sur cette valeur.
        audit('webhook', match ($cible) {
            'evenement' => 'evenements',
            'absence' => 'absences',
            default => 'planning',
        }, null, null, $resume);
    }

    // ── Private : upsert (post/patch) ────────────────────────────────────

    private function upsert(GoogleCalendarService $google, array $operation): void
    {
        $body = $this->buildEventBody($operation);

        if ($operation['scope'] === 'evenement') {
            $this->upsertEvenement($google, $operation, $body);
            return;
        }

        if ($operation['scope'] === 'absence') {
            $this->upsertAbsence($google, $operation, $body);
            return;
        }

        $this->upsertPlanning($google, $operation, $body);
    }

    /**
     * Contrairement à upsertEvenement()/upsertPlanning(), pas de table de
     * suivi dédiée à consulter : une absence n'a qu'un seul calendrier
     * cible possible, donc google_calendar_id/google_event_id vivent
     * directement sur plan_absences (voir migration
     * add_google_calendar_tracking_to_plan_absences_table).
     */
    private function upsertAbsence(GoogleCalendarService $google, array $operation, array $body): void
    {
        $absence = Absence::find($operation['id_absence']);
        if (!$absence) {
            Log::warning('[SynchroniserGoogleCalendar] Absence introuvable — opération ignorée.', [
                'operation' => $operation,
            ]);
            return;
        }

        // L'event_id connu n'est réutilisable que s'il a été créé sur le
        // MÊME calendrier — si `calendar_absence` a changé entre-temps, on
        // recrée plutôt que de patcher un event_id qui vit sur l'ancien
        // calendrier (patch échouerait avec un 404 de toute façon, mais
        // autant l'anticiper explicitement).
        $eventIdConnu = $absence->google_calendar_id === $operation['calendar_id']
            ? $absence->google_event_id
            : null;

        $eventId = $this->patchOuCree($google, $operation['calendar_id'], $eventIdConnu, $body);

        $absence->forceFill([
            'google_calendar_id' => $operation['calendar_id'],
            'google_event_id' => $eventId,
        ])->save();
    }

    private function upsertPlanning(GoogleCalendarService $google, array $operation, array $body): void
    {
        $ligne = CalendrierEvenement::where('id_planning', $operation['id_planning'])
            ->where('id_tache', $operation['id_tache'])
            ->where('google_calendar_id', $operation['calendar_id'])
            ->first();

        $eventId = $this->patchOuCree($google, $operation['calendar_id'], $ligne?->google_event_id, $body);

        CalendrierEvenement::updateOrCreate(
            [
                'id_planning' => $operation['id_planning'],
                'id_tache' => $operation['id_tache'],
                'google_calendar_id' => $operation['calendar_id'],
            ],
            ['google_event_id' => $eventId]
        );
    }

    private function upsertEvenement(GoogleCalendarService $google, array $operation, array $body): void
    {
        $ligne = EvenementCalendrier::where('id_evenement', $operation['id_evenement'])
            ->where('google_calendar_id', $operation['calendar_id'])
            ->first();

        if (!$ligne) {
            // La ligne pivot devrait déjà exister (créée par
            // EvenementsController::syncCalendriers() au moment du
            // formulaire, avant même le dispatch de ce Job) — absente ici
            // signifie que l'événement/calendrier a été détaché entre-temps.
            Log::warning('[SynchroniserGoogleCalendar] Aucune ligne ref_evenements_calendriers trouvée pour cet id_evenement/calendar_id — opération ignorée.', [
                'operation' => $operation,
            ]);
            return;
        }

        $eventId = $this->patchOuCree($google, $operation['calendar_id'], $ligne->google_event_id, $body);

        $ligne->update(['google_event_id' => $eventId]);
    }

    /**
     * Patch l'événement existant si on a déjà un event_id connu ; sinon (ou
     * si Google renvoie 404 — événement supprimé manuellement côté Google
     * Calendar entre-temps) en crée un nouveau. Retourne l'event_id à
     * persister.
     */
    private function patchOuCree(GoogleCalendarService $google, string $calendarId, ?string $eventIdConnu, array $body): string
    {
        if ($eventIdConnu) {
            try {
                $google->updateEvent($calendarId, $eventIdConnu, $body);
                return $eventIdConnu;
            } catch (GoogleServiceException $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
                Log::warning('[SynchroniserGoogleCalendar] event_id introuvable côté Google Calendar (404) — recréation.', [
                    'calendar_id' => $calendarId,
                    'event_id' => $eventIdConnu,
                ]);
            }
        }

        return $google->createEvent($calendarId, $body);
    }

    // ── Private : suppression ────────────────────────────────────────────

    private function supprimer(GoogleCalendarService $google, array $operation): void
    {
        if ($operation['scope'] === 'absence') {
            $absence = Absence::find($operation['id_absence']);
            if (!$absence || !$absence->google_event_id) {
                Log::info('[SynchroniserGoogleCalendar] Rien à supprimer (aucun event_id connu).', ['operation' => $operation]);
                return;
            }
            // Pas de mise à jour de la ligne après coup : AbsencesController::destroy()
            // supprime déjà plan_absences juste après avoir construit ce payload
            // (voir WebhookAbsencePayloadBuilder::buildDelete()) — la ligne
            // entière disparaît, pas besoin de nettoyer les colonnes de suivi.
            $google->deleteEvent($operation['calendar_id'], $absence->google_event_id);
            return;
        }

        if ($operation['scope'] === 'evenement') {
            $ligne = EvenementCalendrier::where('id_evenement', $operation['id_evenement'])
                ->where('google_calendar_id', $operation['calendar_id'])
                ->first();
        } else {
            $ligne = CalendrierEvenement::where('id_planning', $operation['id_planning'])
                ->where('id_tache', $operation['id_tache'])
                ->where('google_calendar_id', $operation['calendar_id'])
                ->first();
        }

        if (!$ligne || !$ligne->google_event_id) {
            Log::info('[SynchroniserGoogleCalendar] Rien à supprimer (aucun event_id connu).', ['operation' => $operation]);
            return;
        }

        $google->deleteEvent($operation['calendar_id'], $ligne->google_event_id);

        $operation['scope'] === 'evenement'
            ? $ligne->update(['google_event_id' => null])
            : $ligne->delete();
    }

    // ── Private : construction du corps d'événement ──────────────────────

    private function buildEventBody(array $operation): array
    {
        if ($operation['scope'] === 'evenement' || $operation['scope'] === 'absence') {
            return [
                'summary' => $operation['summary'],
                'description' => $operation['description'],
                'date_debut' => $operation['date_debut'],
                'date_fin' => $operation['date_fin'],
                'color_id' => $operation['color_id'] ?? null,
            ];
        }

        return [
            'summary' => $operation['summary'],
            'description' => $operation['description'],
            'start' => $operation['start'],
            'end' => $operation['end'],
            'color_id' => $operation['color_id'] ?? null,
        ];
    }
}
