<?php
// database/seeders/PlanningSettingsSeeder.php
//
// Anciennement une migration (2026_07_19_000002_add_couleur_and_calendar_
// absence_settings.php) — convertie en seeder car elle écrit dans
// ref_settings, qui vit maintenant dans amana_commun. Une migration
// normale de planning ne doit plus jamais toucher cette base (voir
// amana/shared — SÉCURITÉ CRITIQUE dans AmanaSharedServiceProvider).
//
// À exécuter manuellement une fois, après `php artisan amana:migrate-shared`
// et après que ce planning se soit enregistré dans ref_applications :
//
//   php artisan db:seed --class=Database\\Seeders\\PlanningSettingsSeeder
//
// Idempotent (updateOrInsert) — peut être relancé sans risque.

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanningSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $commun = DB::connection(config('amana-shared.connection', 'commun'));

        $idApp = $commun->table('ref_applications')->where('code', 'planning')->value('id');

        if (!$idApp) {
            $this->command?->error("Application 'planning' introuvable dans ref_applications — enregistrez-la d'abord.");
            return;
        }

        $couleurs = [
            ['cle' => 'couleur_entree', 'valeur' => '7', 'libelle' => 'Entrée'],
            ['cle' => 'couleur_mektaba', 'valeur' => '10', 'libelle' => 'Mektaba'],
            ['cle' => 'couleur_salle', 'valeur' => '5', 'libelle' => 'Salle'],
            ['cle' => 'couleur_amana_food', 'valeur' => '11', 'libelle' => 'Amana Food'],
            ['cle' => 'couleur_cours', 'valeur' => '3', 'libelle' => 'Cours'],
            ['cle' => 'couleur_rappel_sandwich', 'valeur' => '6', 'libelle' => 'Rappel Sandwich'],
            ['cle' => 'couleur_assistance_amana_food', 'valeur' => '9', 'libelle' => 'Assistance Amana Food'],
            ['cle' => 'couleur_annonce_cours', 'valeur' => '8', 'libelle' => 'Annonce Cours'],
            ['cle' => 'couleur_message_bot', 'valeur' => '1', 'libelle' => 'Message Bot'],
            ['cle' => 'couleur_annulation_cours', 'valeur' => '4', 'libelle' => 'Annulation Cours'],
        ];

        foreach ($couleurs as $c) {
            $commun->table('ref_settings')->updateOrInsert(
                ['id_application' => $idApp, 'cle' => $c['cle']],
                [
                    'valeur' => $c['valeur'],
                    'type' => 'string',
                    'libelle' => $c['libelle'],
                    'description' => 'Couleur Google Calendar (colorId 1-11) utilisée pour synchroniser cette tâche/cet événement.',
                ]
            );
        }

        $commun->table('ref_settings')->updateOrInsert(
            ['id_application' => $idApp, 'cle' => 'calendar_absence'],
            [
                'valeur' => '',
                'type' => 'string',
                'libelle' => 'Absences',
                'description' => 'Calendrier Google Calendar dans lequel les absences sont synchronisées (journée entière, couleur grise fixe). Laisser vide pour ne pas synchroniser les absences.',
            ]
        );

        $this->command?->info('Paramètres couleurs + calendar_absence seedés dans amana_commun.');
    }
}
