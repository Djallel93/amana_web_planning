<?php
// database/migrations/2026_07_19_000002_add_couleur_and_calendar_absence_settings.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute les paramètres ref_settings pour :
 *   - `couleur_<code>` (10 lignes) : colorId Google Calendar par tâche/
 *     événement spécial, éditable dans Paramètres → Couleurs. Valeur par
 *     défaut = mapping actuel de GoogleCalendarColors::TACHES, pour ne rien
 *     changer visuellement tant que personne ne touche à cette section.
 *   - `calendar_absence` : calendrier Google Calendar cible pour la
 *     synchronisation des absences (événements journée entière, couleur
 *     Graphite fixe — voir WebhookAbsencePayloadBuilder).
 *
 * SettingsController::update() ne met à jour QUE des clés déjà présentes en
 * base (voir sa garde `if (!$existe) continue;`) — ces lignes doivent donc
 * exister AVANT que la section Couleurs / le sélecteur de calendrier
 * d'absences ne soient utilisables, d'où cette migration plutôt qu'une
 * simple mise à jour du seeder (qui ne retourne pas en production).
 */
return new class extends Migration {
    public function up(): void
    {
        $idApp = DB::table('ref_applications')->where('code', 'planning')->value('id');

        if (!$idApp) {
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
            DB::table('ref_settings')->updateOrInsert(
                ['id_application' => $idApp, 'cle' => $c['cle']],
                [
                    'valeur' => $c['valeur'],
                    'type' => 'string',
                    'libelle' => $c['libelle'],
                    'description' => 'Couleur Google Calendar (colorId 1-11) utilisée pour synchroniser cette tâche/cet événement.',
                ]
            );
        }

        DB::table('ref_settings')->updateOrInsert(
            ['id_application' => $idApp, 'cle' => 'calendar_absence'],
            [
                'valeur' => '',
                'type' => 'string',
                'libelle' => 'Absences',
                'description' => 'Calendrier Google Calendar dans lequel les absences sont synchronisées (journée entière, couleur grise fixe). Laisser vide pour ne pas synchroniser les absences.',
            ]
        );
    }

    public function down(): void
    {
        $idApp = DB::table('ref_applications')->where('code', 'planning')->value('id');

        if (!$idApp) {
            return;
        }

        $cles = [
            'couleur_entree',
            'couleur_mektaba',
            'couleur_salle',
            'couleur_amana_food',
            'couleur_cours',
            'couleur_rappel_sandwich',
            'couleur_assistance_amana_food',
            'couleur_annonce_cours',
            'couleur_message_bot',
            'couleur_annulation_cours',
            'calendar_absence',
        ];

        DB::table('ref_settings')
            ->where('id_application', $idApp)
            ->whereIn('cle', $cles)
            ->delete();
    }
};
