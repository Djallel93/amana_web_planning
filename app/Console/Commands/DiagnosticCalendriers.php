<?php
// app/Console/Commands/DiagnosticCalendriers.php

declare(strict_types=1);

namespace App\Console\Commands;

use Amana\Shared\Models\Setting;
use App\Models\CalendrierGoogle;
use App\Services\GoogleCalendarService;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Console\Command;

/**
 * Vérifie, pour CHAQUE code de tâche/événement, le paramètre
 * `ref_settings.calendar_<code>` : valeur renseignée ou non, présence dans
 * le registre ref_calendriers_google, et accès réel du compte de service au
 * calendrier visé.
 *
 * Il n'existe pas de « calendrier Planning » global : chaque code a son
 * propre paramètre. Un calendrier qui fonctionne pour les absences
 * (calendar_absence) ou les annonces (calendar_annonce_cours) ne dit donc
 * rien des cinq tâches principales — un `calendar_entree` vide fait
 * disparaître ses événements sans le moindre message d'erreur.
 *
 *   php artisan amana:diagnostic-calendriers
 *   php artisan amana:diagnostic-calendriers --api   (teste aussi l'accès Google)
 */
class DiagnosticCalendriers extends Command
{
    protected $signature = 'amana:diagnostic-calendriers {--api : Vérifier aussi l\'accès du compte de service à chaque calendrier}';

    protected $description = 'Contrôle les paramètres calendar_<code> utilisés pour la synchronisation Google Calendar';

    /** Codes produisant un événement Google Calendar (voir WebhookPayloadBuilder). */
    private const CODES = [
        'entree' => 'Entrée',
        'mektaba' => 'Mektaba',
        'salle' => 'Salle',
        'amana_food' => 'Amana Food',
        'cours' => 'Cours',
        'rappel_sandwich' => 'Rappel Sandwich',
        'assistance_amana_food' => 'Assistance Amana Food',
        'annonce_cours' => 'Annonce Cours',
        'message_bot' => 'Message Bot',
        'annulation_cours' => 'Annulation Cours',
        'absence' => 'Absences',
    ];

    public function handle(GoogleCalendarService $google): int
    {
        $registre = CalendrierGoogle::pluck('nom', 'calendar_id');

        $this->newLine();
        $this->line('── Registre ref_calendriers_google ──────────────');

        if ($registre->isEmpty()) {
            $this->warn('  (vide)');
        }

        foreach ($registre as $id => $nom) {
            $this->line("  {$nom} : {$id}");
        }

        $this->newLine();
        $this->line('── Paramètres calendar_<code> ───────────────────');

        $lignes = [];
        $manquants = [];

        foreach (self::CODES as $code => $libelle) {
            $valeur = (string) (Setting::get("calendar_{$code}", 'planning') ?? '');

            if ($valeur === '') {
                $manquants[] = $libelle;
                $lignes[] = [$libelle, "calendar_{$code}", '(vide)', '❌ aucun événement créé'];
                continue;
            }

            $etat = $registre->has($valeur)
                ? '✅ ' . $registre->get($valeur)
                : '⚠️  absent du registre';

            if ($this->option('api')) {
                $etat .= ' | ' . $this->testerAcces($google, $valeur);
            }

            $lignes[] = [$libelle, "calendar_{$code}", $valeur, $etat];
        }

        $this->table(['Tâche', 'Clé', 'Valeur enregistrée', 'État'], $lignes);

        if ($manquants === []) {
            $this->info('Tous les codes ont un calendrier configuré.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('Sans calendrier configuré : ' . implode(', ', $manquants));
        $this->line('→ Paramètres → Calendriers & couleurs, puis relancer la génération du planning.');

        return self::FAILURE;
    }

    private function testerAcces(GoogleCalendarService $google, string $calendarId): string
    {
        if (!$google->isConfigured()) {
            return 'API non configurée';
        }

        try {
            $google->getCalendar($calendarId);
            return 'accès OK';
        } catch (GoogleServiceException $e) {
            return match ($e->getCode()) {
                404 => 'introuvable ou non partagé avec le compte de service',
                403 => 'partagé mais droits insuffisants',
                default => 'erreur Google ' . $e->getCode(),
            };
        } catch (\Throwable $e) {
            return 'erreur : ' . $e->getMessage();
        }
    }
}
