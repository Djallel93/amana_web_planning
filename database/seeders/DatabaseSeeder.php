<?php
// database/seeders/DatabaseSeeder.php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Personne;
use App\Models\Role;
use App\Models\Tache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder principal — données de référence indispensables au module planning.
 * Idempotent : peut être relancé plusieurs fois sans créer de doublons.
 *
 * Ordre :
 *   1. Application 'planning'
 *   3. Tâches planifiables
 *   4. Rôles planning
 *   5. Compte administrateur
 *   6. Attribution du rôle admin
 *   7. Paramètres ref_settings
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Application planning ────────────────────────────────────────
        $planning = Application::firstOrCreate(
            ['code' => 'planning'],
            ['libelle' => 'AMANA Planning', 'actif' => true]
        );
        $this->command->info('✅ Application planning : OK');

        // ── 3. Tâches planifiables ─────────────────────────────────────────
        $taches = [
            // Tâches actives (rotation du scheduler)
            ['code' => 'entree', 'libelle' => 'Entrée', 'actif' => true, 'description' => ''],
            ['code' => 'mektaba', 'libelle' => 'Mektaba', 'actif' => true, 'description' => ''],
            ['code' => 'salle', 'libelle' => 'Salle', 'actif' => true, 'description' => ''],
            ['code' => 'amana_food', 'libelle' => 'Amana Food', 'actif' => true, 'description' => ''],
            ['code' => 'cours', 'libelle' => 'Cours', 'actif' => true, 'description' => 'Animation du cours'],

            // Tâches inactives (webhook uniquement)
            ['code' => 'rappel_sandwich', 'libelle' => 'Rappel Sandwich', 'actif' => false, 'description' => ''],
            ['code' => 'assistance_amana_food', 'libelle' => 'Assistance Amana Food', 'actif' => false, 'description' => ''],
            ['code' => 'annonce_cours', 'libelle' => 'Annonce Cours', 'actif' => false, 'description' => ''],
            ['code' => 'message_general', 'libelle' => 'Message Général', 'actif' => false, 'description' => ''],
        ];

        foreach ($taches as $t) {
            Tache::updateOrCreate(
                ['code' => $t['code']],
                ['libelle' => $t['libelle'], 'actif' => $t['actif'], 'description' => $t['description']]
            );
        }
        $this->command->info('✅ Tâches insérées/mises à jour : ' . count($taches));

        // ── 4. Rôles planning ──────────────────────────────────────────────
        $roles = [
            ['code' => 'admin', 'libelle' => 'Administrateur'],
            ['code' => 'gestionnaire', 'libelle' => 'Gestionnaire'],
            ['code' => 'membre', 'libelle' => 'Membre officiel'],
            ['code' => 'benevole', 'libelle' => 'Bénévole'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['code' => $role['code'], 'id_application' => $planning->id],
                ['libelle' => $role['libelle']]
            );
        }
        $this->command->info('✅ Rôles insérés : ' . count($roles));

        // ── 5. Compte administrateur ───────────────────────────────────────
        $admin = Personne::updateOrCreate(
            ['email' => 'admin@amana.fr'],
            [
                'nom' => 'Admin',
                'prenom' => 'AMANA',
                'password' => Hash::make('changeme123!'),
                'email_verified_at' => now(),
                'statut' => 'Validé',
                'date_debut_planning' => now()->toDateString(),
            ]
        );
        $this->command->info('✅ Administrateur créé/mis à jour : admin@amana.fr');

        // ── 6. Attribution du rôle admin ───────────────────────────────────
        $roleAdmin = Role::where('code', 'admin')
            ->where('id_application', $planning->id)
            ->first();

        if ($roleAdmin) {
            $dejaAttribue = DB::table('ref_personnes_roles')
                ->where('id_personne', $admin->id)
                ->where('id_role', $roleAdmin->id)
                ->exists();

            if (!$dejaAttribue) {
                DB::table('ref_personnes_roles')->insert([
                    'id_personne' => $admin->id,
                    'id_role' => $roleAdmin->id,
                    'date_attribution' => now()->toDateString(),
                ]);
                $this->command->info('✅ Rôle admin attribué à admin@amana.fr');
            } else {
                $this->command->info('ℹ️  Rôle admin déjà attribué à admin@amana.fr');
            }
        }

        // ── 7. Paramètres ref_settings ─────────────────────────────────────
        $settings = [
            ['cle' => 'heure_cours', 'valeur' => '20:00', 'type' => 'time', 'libelle' => 'Heure du cours'],
            ['cle' => 'lieu', 'valeur' => '319 Rte de Vannes, 44800 Saint-Herblain, France', 'type' => 'string', 'libelle' => 'Lieu des permanences'],

            ['cle' => 'offset_entree_debut', 'valeur' => '-30', 'type' => 'integer', 'libelle' => 'Entrée : début (min)'],
            ['cle' => 'offset_entree_fin', 'valeur' => '30', 'type' => 'integer', 'libelle' => 'Entrée : fin (min)'],
            ['cle' => 'offset_mektaba_debut', 'valeur' => '-20', 'type' => 'integer', 'libelle' => 'Mektaba : début (min)'],
            ['cle' => 'offset_mektaba_fin', 'valeur' => '100', 'type' => 'integer', 'libelle' => 'Mektaba : fin (min)'],
            ['cle' => 'offset_salle_debut', 'valeur' => '0', 'type' => 'integer', 'libelle' => 'Salle : début (min)'],
            ['cle' => 'offset_salle_fin', 'valeur' => '90', 'type' => 'integer', 'libelle' => 'Salle : fin (min)'],
            ['cle' => 'offset_amana_food_debut', 'valeur' => '30', 'type' => 'integer', 'libelle' => 'Amana Food : début (min)'],
            ['cle' => 'offset_amana_food_fin', 'valeur' => '90', 'type' => 'integer', 'libelle' => 'Amana Food : fin (min)'],
            ['cle' => 'offset_cours_debut', 'valeur' => '0', 'type' => 'integer', 'libelle' => 'Cours : début (min)'],
            ['cle' => 'offset_cours_fin', 'valeur' => '60', 'type' => 'integer', 'libelle' => 'Cours : fin (min)'],

            ['cle' => 'offset_rappel_sandwich_debut', 'valeur' => '0', 'type' => 'integer', 'libelle' => 'Rappel sandwich : début (ignoré, fixe 08:00)'],
            ['cle' => 'offset_rappel_sandwich_fin', 'valeur' => '15', 'type' => 'integer', 'libelle' => 'Rappel sandwich : fin (ignoré, fixe 08:15)'],
            ['cle' => 'offset_assistance_amana_food_debut', 'valeur' => '30', 'type' => 'integer', 'libelle' => 'Assistance Amana Food : début (min)'],
            ['cle' => 'offset_assistance_amana_food_fin', 'valeur' => '90', 'type' => 'integer', 'libelle' => 'Assistance Amana Food : fin (min)'],
            ['cle' => 'offset_annonce_cours_debut', 'valeur' => '-360', 'type' => 'integer', 'libelle' => 'Annonce cours : début (min)'],
            ['cle' => 'offset_annonce_cours_fin', 'valeur' => '-345', 'type' => 'integer', 'libelle' => 'Annonce cours : fin (min)'],
            ['cle' => 'offset_message_bot_debut', 'valeur' => '-30', 'type' => 'integer', 'libelle' => 'Message bot : début (min)'],
            ['cle' => 'offset_message_bot_fin', 'valeur' => '0', 'type' => 'integer', 'libelle' => 'Message bot : fin (min)'],

            // ── D. Noms de calendriers Google Calendar ─────────────────────
            // Vides par défaut — à renseigner via la page Paramètres.
            // Chaque valeur est le nom exact du calendrier Google Calendar
            // dans lequel Make.com créera les événements pour cette tâche.
            // Si vide → Make.com utilise son calendrier par défaut.
            [
                'cle' => 'calendar_entree',
                'valeur' => 'AMANA - Planning',
                'type' => 'string',
                'libelle' => 'Entrée',
            ],
            [
                'cle' => 'calendar_mektaba',
                'valeur' => 'AMANA - Planning',
                'type' => 'string',
                'libelle' => 'Mektaba',
            ],
            [
                'cle' => 'calendar_salle',
                'valeur' => 'AMANA - Planning',
                'type' => 'string',
                'libelle' => 'Salle',
            ],
            [
                'cle' => 'calendar_amana_food',
                'valeur' => 'AMANA - Planning',
                'type' => 'string',
                'libelle' => 'Amana Food',
            ],
            [
                'cle' => 'calendar_cours',
                'valeur' => 'AMANA - Planning',
                'type' => 'string',
                'libelle' => 'Cours',
            ],
            [
                'cle' => 'calendar_rappel_sandwich',
                'valeur' => 'AMANA - Planning',
                'type' => 'string',
                'libelle' => 'Rappel Sandwich',
            ],
            [
                'cle' => 'calendar_assistance_amana_food',
                'valeur' => 'AMANA - Planning',
                'type' => 'string',
                'libelle' => 'Assistance Amana Food',
            ],
            [
                'cle' => 'calendar_annonce_cours',
                'valeur' => 'AMANA - Communications',
                'type' => 'string',
                'libelle' => 'Annonce Cours',
            ],
            [
                'cle' => 'calendar_message_bot',
                'valeur' => 'AMANA - Communications',
                'type' => 'string',
                'libelle' => 'Message Bot',
            ],
        ];

        foreach ($settings as $s) {
            DB::table('ref_settings')->updateOrInsert(
                ['id_application' => $planning->id, 'cle' => $s['cle']],
                ['valeur' => $s['valeur'], 'type' => $s['type'], 'libelle' => $s['libelle'], 'description' => null]
            );
        }
        $this->command->info('✅ Paramètres ref_settings insérés : ' . count($settings));

        // ── Récapitulatif ──────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  Connexion admin :');
        $this->command->info('  Email        : admin@amana.fr');
        $this->command->info('  Mot de passe : changeme123!');
        $this->command->warn('  ⚠️  Changez ce mot de passe en production !');
        $this->command->newLine();
        $this->command->warn('  ⚠️  COURS : configurer les restrictions via l\'UI');
        $this->command->warn('       Restrictions > cocher cours=true pour la personne');
        $this->command->warn('       désignée uniquement (tous les autres = false)');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}