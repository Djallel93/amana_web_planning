<?php
// database/seeders/CalendriersGoogleSeeder.php
//
// Seede le registre ref_calendriers_google (voir CalendrierGoogleController
// et la migration 2026_07_17_000001_create_ref_calendriers_google_table.php
// pour le pourquoi de ce registre) avec les deux calendriers Google Calendar
// connus de l'environnement cible, à partir de config('services.google.calendar.preseed')
// (lui-même alimenté par GOOGLE_CALENDAR_ID_1/2 — voir config/services.php,
// .env.example et .github/deploy/.env.production.template).
//
// Volontairement une écriture DB simple (firstOrCreate), SANS appel à
// GoogleCalendarService::getCalendar() : contrairement à l'ajout manuel
// depuis /parametres (CalendrierGoogleController::store()), ce seeder
// tourne en environnement de déploiement où l'accès réseau à l'API Google
// n'est pas garanti disponible au bon moment du pipeline. `actif` reste
// true par défaut — un gestionnaire peut vérifier l'accès a posteriori
// via le bouton "Vérifier" de /parametres, qui appelle bien l'API.
//
// firstOrCreate (pas updateOrCreate) : une fois le calendrier créé, ce
// seeder n'écrase plus jamais son nom/statut — un gestionnaire peut le
// renommer/désactiver depuis /parametres sans qu'un redéploiement ne
// revienne dessus. Idempotent : peut être relancé sans risque, mais n'a
// d'effet réel qu'une seule fois par calendar_id (comme
// PlanningSettingsSeeder, appelé uniquement via le flag
// RUN_SHARED_MIGRATION du déploiement — voir deploy.yaml).
//
//   php artisan db:seed --class=Database\\Seeders\\CalendriersGoogleSeeder

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CalendrierGoogle;
use Illuminate\Database\Seeder;

class CalendriersGoogleSeeder extends Seeder
{
    public function run(): void
    {
        $calendriers = config('services.google.calendar.preseed', []);

        foreach ($calendriers as $c) {
            $calendarId = $c['id'] ?? null;
            $nom = $c['nom'] ?? $calendarId;

            if (empty($calendarId)) {
                $this->command?->warn("Variable d'environnement manquante pour « {$nom} » — calendrier ignoré.");
                continue;
            }

            CalendrierGoogle::firstOrCreate(
                ['calendar_id' => $calendarId],
                [
                    'nom' => $nom,
                    'actif' => true,
                ]
            );
        }

        $this->command?->info('✅ Registre ref_calendriers_google seedé depuis GOOGLE_CALENDAR_ID_1/2.');
    }
}
