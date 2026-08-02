<?php
// app/Console/Commands/TesterGoogleCalendar.php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CalendrierGoogle;
use App\Services\GoogleCalendarService;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Console\Command;

/**
 * Vérifie de bout en bout que le compte de service Google Calendar est
 * correctement configuré, SANS toucher aux données de l'application
 * (aucune écriture en base, sauf --create qui crée/modifie/supprime un
 * événement de test réel côté Google Calendar) :
 *
 *   1. GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 est présente et décodable.
 *   2. Vérifie l'accès (calendars.get()) à chaque calendrier du registre
 *      ref_calendriers_google, et/ou à un ID précis via --calendar-id.
 *   3. (Optionnel, --create) Crée un événement de test de courte durée dans
 *      un calendrier au choix, puis le modifie (PATCH) et le supprime
 *      (DELETE), pour vérifier le cycle complet create/update/delete.
 *
 * IMPORTANT — pas de découverte automatique : un compte de service n'a pas
 * de "Calendar List" comme un utilisateur humain, `calendarList.list()`
 * renvoie systématiquement une liste vide pour un compte de service même
 * quand des calendriers lui sont partagés (documenté par Google —
 * https://developers.google.com/workspace/calendar/api/concepts/sharing).
 * Cette commande ne peut donc que VÉRIFIER l'accès à des ID déjà connus
 * (registre `ref_calendriers_google` et/ou --calendar-id), jamais lister
 * "tout ce qui est partagé" — voir docs/google_service_account.md.
 *
 * Usage :
 *   php artisan amana:tester-google-calendar
 *   php artisan amana:tester-google-calendar --calendar-id=xxxx@group.calendar.google.com
 *   php artisan amana:tester-google-calendar --create
 *   php artisan amana:tester-google-calendar --create --calendar-id=xxxx@group.calendar.google.com
 */
class TesterGoogleCalendar extends Command
{
    protected $signature = 'amana:tester-google-calendar
        {--create : Crée, modifie puis supprime un événement de test réel}
        {--calendar-id= : Vérifie (et utilise pour --create) un ID de calendrier précis, même non enregistré dans ref_calendriers_google}';

    protected $description = "Vérifie la configuration du compte de service Google Calendar (auth, accès aux calendriers enregistrés et/ou à un ID précis, et optionnellement un cycle create/update/delete).";

    public function __construct(
        private readonly GoogleCalendarService $google,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->components->info('Étape 1/3 — Vérification de la configuration…');

        if (!$this->google->isConfigured()) {
            $this->components->error(
                'GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 est vide ou absente du .env. '
                . "Rien à tester tant qu'elle n'est pas renseignée."
            );
            return Command::FAILURE;
        }
        $this->components->info('GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 est présente.');

        $email = $this->google->getServiceAccountEmail();
        if ($email) {
            $this->line("  Compte de service : <fg=cyan>{$email}</>");
            $this->line('  → Comparez cette adresse avec celle effectivement partagée sur vos calendriers Google Calendar.');
        }

        $this->newLine();
        $this->components->info('Étape 2/3 — Vérification de l\'accès aux calendriers connus…');
        $this->line('  (Un compte de service ne peut pas "lister" ses calendriers partagés — seulement vérifier un ID déjà connu.)');
        $this->newLine();

        $idsAVerifier = $this->collecterIdsAVerifier();

        if (empty($idsAVerifier)) {
            $this->components->warn(
                "Aucun calendrier à vérifier : le registre ref_calendriers_google est vide et aucun "
                . "--calendar-id n'a été fourni."
            );
            $this->line('');
            $this->line('Deux façons de continuer :');
            $this->line('  1. Enregistrer un calendrier depuis /parametres (interface web) — recommandé pour un usage normal.');
            $this->line('  2. Tester un ID précis sans l\'enregistrer : --calendar-id=xxxx@group.calendar.google.com');
            return Command::FAILURE;
        }

        $resultats = [];
        $auMoinsUnSucces = false;
        $premierIdValide = null;

        foreach ($idsAVerifier as $calendarId) {
            try {
                $infos = $this->google->getCalendar($calendarId);
                $resultats[] = [$calendarId, "✓ {$infos['name']}", ''];
                $auMoinsUnSucces = true;
                $premierIdValide ??= $calendarId;
            } catch (GoogleServiceException $e) {
                $resultats[] = [$calendarId, '✗ Échec', $this->causeErreur($e)];
            } catch (\Throwable $e) {
                $resultats[] = [$calendarId, '✗ Échec', $e->getMessage()];
            }
        }

        $this->table(['Calendar ID', 'Résultat', 'Détail'], $resultats);

        if (!$auMoinsUnSucces) {
            $this->components->error('Aucun des calendriers vérifiés n\'est accessible.');
            $this->line('');
            $this->line('Causes fréquentes :');
            $this->line('  - ID de calendrier incorrect (copié depuis Google Calendar → Paramètres → Intégrer l\'agenda)');
            $this->line('  - Calendrier non partagé avec l\'adresse ci-dessus, ou partagé avec la mauvaise adresse');
            $this->line('  - Calendar API non activée sur le projet Google Cloud');
            $this->line('  - Restriction de partage externe côté administrateur Google Workspace (domaine ne correspondant pas à *.iam.gserviceaccount.com)');
            return Command::FAILURE;
        }

        if (!$this->option('create')) {
            $this->newLine();
            $this->components->info(
                'Configuration OK pour au moins un calendrier. Relancez avec --create pour tester un cycle complet '
                . 'create/update/delete sur un événement réel (courte durée, supprimé automatiquement).'
            );
            return Command::SUCCESS;
        }

        return $this->testerCycleComplet($this->option('calendar-id') ?: $premierIdValide);
    }

    /**
     * Rassemble les IDs à vérifier : --calendar-id s'il est fourni, sinon
     * (ou en complément) tous les calendriers actifs du registre.
     *
     * @return array<int, string>
     */
    private function collecterIdsAVerifier(): array
    {
        $ids = [];

        if ($optionId = $this->option('calendar-id')) {
            $ids[] = $optionId;
        }

        $enregistres = CalendrierGoogle::where('actif', true)->pluck('calendar_id')->all();

        return array_values(array_unique(array_merge($ids, $enregistres)));
    }

    private function causeErreur(GoogleServiceException $e): string
    {
        return match ($e->getCode()) {
            404 => 'Introuvable ou non partagé avec le compte de service',
            403 => 'Accès refusé (droits insuffisants ou API non activée)',
            default => "HTTP {$e->getCode()}",
        };
    }

    private function testerCycleComplet(?string $calendarId): int
    {
        if (!$calendarId) {
            $this->components->error(
                'Aucun calendar-id disponible pour --create. Fournissez --calendar-id=xxxx@group.calendar.google.com.'
            );
            return Command::FAILURE;
        }

        $this->newLine();
        $this->components->info("Étape 3/3 — Cycle create/update/delete sur « {$calendarId} »…");

        $debut = now()->addMinutes(5);
        $fin = $debut->clone()->addMinutes(15);

        try {
            $this->line('  → Création…');
            $eventId = $this->google->createEvent($calendarId, [
                'summary' => '[TEST AMANA] Vérification compte de service — à ignorer',
                'description' => "Événement créé automatiquement par `php artisan amana:tester-google-calendar --create`.\nSera supprimé dans quelques secondes. Si vous le voyez encore, la suppression a échoué.",
                'start' => $debut->toIso8601String(),
                'end' => $fin->toIso8601String(),
            ]);
            $this->components->info("  Événement créé (event_id = {$eventId}).");

            $this->line('  → Modification (PATCH)…');
            $this->google->updateEvent($calendarId, $eventId, [
                'summary' => '[TEST AMANA] Vérification compte de service — modifié',
                'description' => 'Modification confirmée.',
                'start' => $debut->toIso8601String(),
                'end' => $fin->toIso8601String(),
            ]);
            $this->components->info('  Événement modifié.');

            $this->line('  → Suppression (DELETE)…');
            $this->google->deleteEvent($calendarId, $eventId);
            $this->components->info('  Événement supprimé.');
        } catch (\Throwable $e) {
            $this->components->error('Échec pendant le cycle de test : ' . $e->getMessage());
            $this->line('  ⚠️  Si la création a réussi mais pas la suite, un événement de test peut être resté dans le calendrier — pensez à vérifier/nettoyer manuellement.');
            return Command::FAILURE;
        }

        $this->newLine();
        $this->components->info('✅ Tout fonctionne : authentification, création, modification et suppression confirmées.');

        return Command::SUCCESS;
    }
}
