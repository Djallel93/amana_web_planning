<?php
// app/Services/GoogleCalendarService.php

declare(strict_types=1);

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\AclRule as GoogleAclRule;
use Google\Service\Calendar\AclRuleScope as GoogleAclRuleScope;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\Log;

/**
 * Fine wrapper autour de l'API Google Calendar v3 (client officiel
 * google/apiclient), authentifié via un compte de service.
 *
 * Les événements Google Calendar sont créés/modifiés/supprimés en appel
 * direct, avec l'event_id stocké en base (voir CalendrierEvenement /
 * EvenementCalendrier) plutôt qu'une résolution par nom + date.
 *
 * Auth : GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 (voir config/services.php)
 * contient le fichier de clé JSON du compte de service, encodé en base64.
 * Chaque calendrier cible doit avoir été partagé avec l'email de ce compte
 * de service (droit "Apporter des modifications aux événements").
 */
class GoogleCalendarService
{
    private const SCOPES = [GoogleCalendar::CALENDAR];

    /** Codes d'erreur HTTP Google pour lesquels on retente avec backoff. */
    private const HTTP_CODES_RETRYABLES = [403, 429, 500, 503];

    /** Nombre de tentatives internes (en plus des tentatives du Job appelant). */
    private const MAX_TENTATIVES = 4;

    private ?GoogleCalendar $service = null;

    /**
     * Indique si le compte de service est configuré (JSON présent et
     * décodable) — permet aux appelants de court-circuiter proprement
     * (log + skip) plutôt que de lever une exception en environnement non
     * configuré (ex. développement local sans clé).
     */
    public function isConfigured(): bool
    {
        return !empty(config('services.google.calendar.service_account_json_base64'));
    }

    /**
     * Vérifie l'accès à un calendrier Google Calendar **connu** (ID fourni)
     * via `calendars.get()`, et retourne son nom d'affichage (`summary`).
     *
     * Remplace l'ancienne `listCalendars()` basée sur `calendarList.list()` :
     * un compte de service N'A PAS de "Calendar List" comme un utilisateur
     * humain — `calendarList.list()` renvoie systématiquement une liste
     * vide pour un compte de service, MÊME quand des calendriers lui ont
     * été partagés individuellement et qu'il peut parfaitement les
     * lire/écrire. C'est documenté par Google lui-même :
     * https://developers.google.com/workspace/calendar/api/concepts/sharing
     * ("Sharing a calendar with a user no longer automatically inserts the
     * calendar into their CalendarList.") Il n'existe donc aucun moyen fiable
     * de DÉCOUVRIR automatiquement les calendriers partagés — seulement de
     * VÉRIFIER l'accès à un ID déjà connu, ce que fait cette méthode.
     *
     * Utilisée par `CalendrierGoogleController` au moment de l'enregistrement
     * d'un nouveau calendrier dans le registre `ref_calendriers_google`, et
     * par `amana:tester-google-calendar` pour le diagnostic.
     *
     * @return array{id: string, name: string}
     * @throws GoogleServiceException 404 = calendrier introuvable ou non
     *         partagé avec le compte de service ; 403 = partagé mais avec
     *         des droits insuffisants pour même le lire.
     */
    public function getCalendar(string $calendarId): array
    {
        $calendar = $this->withRetry(
            fn() => $this->client()->calendars->get($calendarId)
        );

        return [
            'id' => $calendar->getId(),
            'name' => $calendar->getSummary() ?: $calendar->getId(),
        ];
    }

    /**
     * Retourne l'email du compte de service (client_email du JSON
     * décodé) — utile pour affichage/diagnostic (amana:tester-google-calendar),
     * afin de comparer visuellement avec l'adresse effectivement partagée
     * sur les calendriers Google Calendar.
     */
    public function getServiceAccountEmail(): ?string
    {
        $encoded = config('services.google.calendar.service_account_json_base64');
        if (empty($encoded)) {
            return null;
        }

        $decoded = base64_decode((string) $encoded, true);
        $credentials = $decoded !== false ? json_decode($decoded, true) : null;

        return is_array($credentials) ? ($credentials['client_email'] ?? null) : null;
    }

    /**
     * Crée un événement dans le calendrier donné. Retourne l'event_id
     * Google Calendar créé, à persister immédiatement par l'appelant.
     *
     * @param array{summary: string, description?: string, start: string, end: string, date?: string} $event
     */
    public function createEvent(string $calendarId, array $event): string
    {
        $body = $this->buildEventBody($event);

        $created = $this->withRetry(
            fn() => $this->client()->events->insert($calendarId, $body)
        );

        return $created->getId();
    }

    /**
     * Met à jour un événement existant via son event_id exact — pas de
     * recherche par nom/date. Si Google renvoie 404 (événement supprimé
     * manuellement côté Google Calendar entre-temps), l'appelant est censé
     * intercepter GoogleServiceException et retomber sur createEvent().
     */
    public function updateEvent(string $calendarId, string $eventId, array $event): void
    {
        $body = $this->buildEventBody($event);

        $this->withRetry(
            fn() => $this->client()->events->patch($calendarId, $eventId, $body)
        );
    }

    /**
     * Supprime un événement via son event_id exact. Un 404/410 (déjà
     * supprimé côté Google Calendar) est traité comme un succès silencieux
     * — l'état désiré (événement absent) est déjà atteint.
     */
    public function deleteEvent(string $calendarId, string $eventId): void
    {
        try {
            $this->withRetry(
                fn() => $this->client()->events->delete($calendarId, $eventId)
            );
        } catch (GoogleServiceException $e) {
            if (in_array($e->getCode(), [404, 410], true)) {
                Log::info('[GoogleCalendarService] Événement déjà absent côté Google Calendar (delete ignoré).', [
                    'calendar_id' => $calendarId,
                    'event_id' => $eventId,
                ]);
                return;
            }
            throw $e;
        }
    }

    /**
     * Partage un calendrier avec l'adresse email d'un utilisateur, via une
     * règle ACL (Acl::insert) — donne accès au calendrier ENTIER depuis le
     * compte Google personnel de cette personne, pas à un événement précis
     * (Google Calendar n'a pas de mécanisme de partage par événement isolé
     * via un compte de service — voir docblock de buildEventBody() : les
     * événements créés ici n'ont ni attendee ni invitation).
     *
     * $role suit la nomenclature des rôles ACL Google Calendar :
     *   - 'reader'       : voir les détails des événements ("See all event details")
     *   - 'writer'       : créer/modifier des événements ("Make changes to events")
     *   - 'owner'        : gérer aussi le partage ("Make changes and manage sharing")
     *     — plusieurs personnes peuvent chacune détenir une règle ACL 'owner'
     *     sur un même calendrier, cela ne désigne pas un propriétaire unique.
     *   - 'freeBusyReader' : uniquement les disponibilités, sans détail
     *
     * Idempotent en pratique : Google Calendar remplace silencieusement une
     * règle ACL existante pour la même adresse plutôt que d'en créer une
     * deuxième, donc appeler cette méthode plusieurs fois pour la même
     * personne/le même calendrier est sans risque.
     *
     * @throws GoogleServiceException propagée telle quelle à l'appelant —
     *         voir CalendarSharingService pour la gestion des échecs partiels.
     */
    public function partagerAvecUtilisateur(string $calendarId, string $email, string $role): void
    {
        $scope = new GoogleAclRuleScope();
        $scope->setType('user');
        $scope->setValue($email);

        $rule = new GoogleAclRule();
        $rule->setScope($scope);
        $rule->setRole($role);

        $this->withRetry(
            fn() => $this->client()->acl->insert($calendarId, $rule)
        );
    }

    // ── Private ───────────────────────────────────────────────────────────

    /**
     * Volontairement AUCUN attendee/invitation sur les événements créés ici.
     * Un compte de service ne peut pas inviter d'attendees sans Domain-Wide
     * Delegation (erreur Google `forbiddenForServiceAccounts`, HTTP 403) —
     * restriction de l'API elle-même, indépendante de la validité de
     * l'adresse email fournie. La personne assignée reste visible : son nom
     * est déjà inclus dans la description de l'événement (voir
     * GoogleCalendarPayloadMapper::buildDescription()). Les rappels
     * personnels et ciblés sont gérés indépendamment de Google Calendar, via
     * une commande planifiée (cron IONOS → scheduler Laravel → Notification
     * email), pas via ce mécanisme.
     */
    private function buildEventBody(array $event): GoogleEvent
    {
        $googleEvent = new GoogleEvent();
        $googleEvent->setSummary($event['summary']);

        if (!empty($event['description'])) {
            $googleEvent->setDescription($event['description']);
        }

        // Événement sur créneau horaire précis (heure_debut/heure_fin).
        if (isset($event['start'], $event['end'])) {
            $start = new EventDateTime();
            $start->setDateTime($event['start']);
            $start->setTimeZone('Europe/Paris');

            $end = new EventDateTime();
            $end->setDateTime($event['end']);
            $end->setTimeZone('Europe/Paris');

            $googleEvent->setStart($start);
            $googleEvent->setEnd($end);
        }

        // Événement organisationnel "journée entière" (ref_evenements).
        if (isset($event['date_debut'], $event['date_fin'])) {
            $start = new EventDateTime();
            $start->setDate($event['date_debut']);

            $end = new EventDateTime();
            // Google Calendar : la date de fin d'un événement "all-day" est
            // exclusive — il faut ajouter un jour, ce que fait l'appelant
            // (GoogleCalendarPayloadMapper) avant de nous transmettre la
            // valeur, pour garder ce wrapper agnostique du métier.
            $end->setDate($event['date_fin']);

            $googleEvent->setStart($start);
            $googleEvent->setEnd($end);
        }

        return $googleEvent;
    }

    /**
     * Exécute $callback avec retry + backoff exponentiel sur les erreurs
     * transitoires Google (403 userRateLimitExceeded/rateLimitExceeded, 429,
     * 5xx) — pratique standard recommandée par Google, pas une nécessité
     * liée au volume actuel (largement sous les quotas).
     */
    private function withRetry(callable $callback): mixed
    {
        $tentative = 0;

        while (true) {
            try {
                return $callback();
            } catch (GoogleServiceException $e) {
                $tentative++;
                $retryable = in_array($e->getCode(), self::HTTP_CODES_RETRYABLES, true);

                if (!$retryable || $tentative >= self::MAX_TENTATIVES) {
                    throw $e;
                }

                $delaiMs = (int) (200 * (2 ** $tentative)) + random_int(0, 250);
                Log::warning('[GoogleCalendarService] Erreur transitoire, nouvelle tentative.', [
                    'code' => $e->getCode(),
                    'tentative' => $tentative,
                    'delai_ms' => $delaiMs,
                ]);
                usleep($delaiMs * 1000);
            }
        }
    }

    private function client(): GoogleCalendar
    {
        if ($this->service !== null) {
            return $this->service;
        }

        $encoded = config('services.google.calendar.service_account_json_base64');

        if (empty($encoded)) {
            throw new \RuntimeException(
                'GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 non configurée — impossible de contacter Google Calendar.'
            );
        }

        $decoded = base64_decode((string) $encoded, true);
        $credentials = $decoded !== false ? json_decode($decoded, true) : null;

        if (!is_array($credentials)) {
            throw new \RuntimeException(
                'GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 invalide (base64 ou JSON non décodable).'
            );
        }

        $client = new GoogleClient();
        $client->setApplicationName('AMANA Planning');
        $client->setAuthConfig($credentials);
        $client->setScopes(self::SCOPES);

        $this->service = new GoogleCalendar($client);

        return $this->service;
    }
}
