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
 * Seeder principal.
 *
 * Insère toutes les données de référence indispensables au fonctionnement.
 * Idempotent : peut être relancé plusieurs fois sans créer de doublons.
 *
 * En développement, ce seeder gère aussi la création des tables
 * qui n'ont pas encore de migration dédiée (ref_settings, colonne
 * description sur ref_taches).
 *
 * Ordre :
 *   1. Application 'planning'
 *   2. Schéma dev : colonne description sur ref_taches + table ref_settings
 *   3. Tâches planifiables (avec descriptions)
 *   4. Rôles planning
 *   5. Compte administrateur
 *   6. Types de véhicules
 *   7. Données géographiques
 *   8. Attribution du rôle admin
 *   9. Paramètres ref_settings (planning)
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

        // ── 2. Schéma dev ──────────────────────────────────────────────────
        // Colonne description sur ref_taches (pas de migration dédiée en dev)
        if (!Schema::hasColumn('ref_taches', 'description')) {
            DB::statement('ALTER TABLE ref_taches ADD COLUMN description text NULL AFTER libelle');
            $this->command->info('✅ Colonne description ajoutée sur ref_taches');
        }

        // Table ref_settings (pas de migration dédiée en dev)
        if (!Schema::hasTable('ref_settings')) {
            DB::statement('
                CREATE TABLE ref_settings (
                    id            TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    id_application TINYINT UNSIGNED NULL COMMENT "NULL = paramètre global",
                    cle           VARCHAR(100) NOT NULL,
                    valeur        VARCHAR(500) NOT NULL,
                    type          ENUM("string","integer","time","boolean") NOT NULL DEFAULT "string",
                    libelle       VARCHAR(200) NOT NULL,
                    description   TEXT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_settings_app_cle (id_application, cle),
                    CONSTRAINT fk_settings_application
                        FOREIGN KEY (id_application)
                        REFERENCES ref_applications(id)
                        ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
            $this->command->info('✅ Table ref_settings créée');
        }

        // ── 3. Tâches planifiables ─────────────────────────────────────────
        //
        // Tâches actives (participent à la rotation du scheduler) :
        //   entree, mektaba, salle, amana_food, cours
        //
        // Tâches inactives (webhook payload uniquement, jamais dans le scheduler) :
        //   rappel_sandwich, assistance_amana_food, annonce_cours, message_general
        //
        // Les descriptions sont intentionnellement laissées vides ici —
        // à compléter manuellement via l'UI ou directement en base.
        //
        $taches = [
            // ── Tâches actives ─────────────────────────────────────────────
            [
                'code' => 'entree',
                'libelle' => 'Entrée',
                'actif' => true,
                'description' => '',
            ],
            [
                'code' => 'mektaba',
                'libelle' => 'Mektaba',
                'actif' => true,
                'description' => '',
            ],
            [
                'code' => 'salle',
                'libelle' => 'Salle',
                'actif' => true,
                'description' => '',
            ],
            [
                'code' => 'amana_food',
                'libelle' => 'Amana Food',
                'actif' => true,
                'description' => '',
            ],
            [
                'code' => 'cours',
                'libelle' => 'Cours',
                'actif' => true,
                'description' => 'Animation du cours',
                // ⚠️  IMPORTANT : après le premier déploiement, un admin doit
                // configurer les restrictions via l'UI :
                //   - Tous les membres : autorise = false pour cours (Vendredi + Samedi)
                //   - Une seule personne désignée : autorise = true
                // Sans quoi le scheduler ne pourra pas assigner le cours.
            ],

            // ── Tâches inactives (webhook uniquement) ──────────────────────
            [
                'code' => 'rappel_sandwich',
                'libelle' => 'Rappel Sandwich',
                'actif' => false,
                'description' => '',
                // Horaire fixe 08:00–08:15, personne = celle assignée à amana_food
            ],
            [
                'code' => 'assistance_amana_food',
                'libelle' => 'Assistance Amana Food',
                'actif' => false,
                'description' => '',
                // Horaire relatif à heure_cours, personne = celle assignée à entree
            ],
            [
                'code' => 'annonce_cours',
                'libelle' => 'Annonce Cours',
                'actif' => false,
                'description' => '',
                // Événement social — pas d'assignee, timing relatif à heure_cours
            ],
            [
                'code' => 'message_general',
                'libelle' => 'Message Général',
                'actif' => false,
                'description' => '',
                // Événement social — pas d'assignee, timing relatif à heure_cours
            ],
        ];

        foreach ($taches as $tacheData) {
            Tache::updateOrCreate(
                ['code' => $tacheData['code']],
                [
                    'libelle' => $tacheData['libelle'],
                    'actif' => $tacheData['actif'],
                    'description' => $tacheData['description'],
                ]
            );
        }
        $this->command->info('✅ Tâches insérées/mises à jour : ' . count($taches));

        // ── 4. Rôles liés à l'application planning ─────────────────────────
        $roles = [
            ['code' => 'admin', 'libelle' => 'Administrateur', 'id_application' => $planning->id],
            ['code' => 'gestionnaire', 'libelle' => 'Gestionnaire', 'id_application' => $planning->id],
            ['code' => 'membre', 'libelle' => 'Membre officiel', 'id_application' => $planning->id],
            ['code' => 'benevole', 'libelle' => 'Bénévole', 'id_application' => $planning->id],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['code' => $role['code'], 'id_application' => $role['id_application']],
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
                'tirelire' => false,
            ]
        );
        $this->command->info('✅ Administrateur créé/mis à jour : admin@amana.fr');

        // ── 6. Types de véhicules ──────────────────────────────────────────
        $vehicules = [
            ['id' => 1, 'type' => 'Citadine', 'capacite_kg' => 150, 'nombre_parts_max' => 6],
            ['id' => 2, 'type' => 'Berline', 'capacite_kg' => 250, 'nombre_parts_max' => 8],
            ['id' => 3, 'type' => 'Break', 'capacite_kg' => 300, 'nombre_parts_max' => 15],
            ['id' => 4, 'type' => 'Monospace', 'capacite_kg' => 400, 'nombre_parts_max' => 20],
            ['id' => 5, 'type' => 'Fourgon moyen', 'capacite_kg' => 700, 'nombre_parts_max' => 30],
            ['id' => 6, 'type' => 'Grands fourgon', 'capacite_kg' => 1000, 'nombre_parts_max' => 50],
            ['id' => 7, 'type' => 'Permis', 'capacite_kg' => 0, 'nombre_parts_max' => 0],
            ['id' => 8, 'type' => 'Sans permis', 'capacite_kg' => 0, 'nombre_parts_max' => 0],
        ];

        foreach ($vehicules as $vehicule) {
            DB::table('ref_vehicules')->updateOrInsert(['id' => $vehicule['id']], $vehicule);
        }
        $this->command->info('✅ Types de véhicules ajoutés');

        // ── 7. Données géographiques ───────────────────────────────────────
        $this->call(GeoSeeder::class);
        $this->command->info('✅ Données Geo ajoutées');

        // ── 8. Attribution du rôle admin ───────────────────────────────────
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

        // ── 9. Paramètres ref_settings pour l'application planning ─────────
        //
        // Tous les offsets sont en minutes relatifs à heure_cours.
        // Exception : rappel_sandwich a un horaire fixe (08:00–08:15),
        // les offsets de cette clé sont ignorés dans WebhookPayloadBuilder.
        //
        $settings = [
            // ── Horaires généraux ──────────────────────────────────────────
            [
                'cle' => 'heure_cours',
                'valeur' => '20:00',
                'type' => 'time',
                'libelle' => 'Heure du cours',
            ],
            [
                'cle' => 'lieu',
                'valeur' => '319 Rte de Vannes, 44800 Saint-Herblain, France',
                'type' => 'string',
                'libelle' => 'Lieu des permanences',
            ],

            // ── Offsets tâche : Entrée ─────────────────────────────────────
            [
                'cle' => 'offset_entree_debut',
                'valeur' => '-30',
                'type' => 'integer',
                'libelle' => 'Entrée : début (min)',
            ],
            [
                'cle' => 'offset_entree_fin',
                'valeur' => '30',
                'type' => 'integer',
                'libelle' => 'Entrée : fin (min)',
            ],

            // ── Offsets tâche : Mektaba ────────────────────────────────────
            [
                'cle' => 'offset_mektaba_debut',
                'valeur' => '-20',
                'type' => 'integer',
                'libelle' => 'Mektaba : début (min)',
            ],
            [
                'cle' => 'offset_mektaba_fin',
                'valeur' => '100',
                'type' => 'integer',
                'libelle' => 'Mektaba : fin (min)',
            ],

            // ── Offsets tâche : Salle ──────────────────────────────────────
            [
                'cle' => 'offset_salle_debut',
                'valeur' => '0',
                'type' => 'integer',
                'libelle' => 'Salle : début (min)',
            ],
            [
                'cle' => 'offset_salle_fin',
                'valeur' => '90',
                'type' => 'integer',
                'libelle' => 'Salle : fin (min)',
            ],

            // ── Offsets tâche : Amana Food ─────────────────────────────────
            [
                'cle' => 'offset_amana_food_debut',
                'valeur' => '30',
                'type' => 'integer',
                'libelle' => 'Amana Food : début (min)',
            ],
            [
                'cle' => 'offset_amana_food_fin',
                'valeur' => '90',
                'type' => 'integer',
                'libelle' => 'Amana Food : fin (min)',
            ],

            // ── Offsets tâche : Cours ──────────────────────────────────────
            [
                'cle' => 'offset_cours_debut',
                'valeur' => '0',
                'type' => 'integer',
                'libelle' => 'Cours : début (min)',
            ],
            [
                'cle' => 'offset_cours_fin',
                'valeur' => '60',
                'type' => 'integer',
                'libelle' => 'Cours : fin (min)',
            ],

            // ── Offsets rappel sandwich (valeurs ignorées — horaire fixe) ──
            [
                'cle' => 'offset_rappel_sandwich_debut',
                'valeur' => '0',
                'type' => 'integer',
                'libelle' => 'Rappel sandwich : heure fixe début (ignoré, toujours 08:00)',
            ],
            [
                'cle' => 'offset_rappel_sandwich_fin',
                'valeur' => '15',
                'type' => 'integer',
                'libelle' => 'Rappel sandwich : heure fixe fin (ignoré, toujours 08:15)',
            ],

            // ── Offsets tâche : Assistance Amana Food ─────────────────────
            [
                'cle' => 'offset_assistance_amana_food_debut',
                'valeur' => '30',
                'type' => 'integer',
                'libelle' => 'Assistance Amana Food : début (min)',
            ],
            [
                'cle' => 'offset_assistance_amana_food_fin',
                'valeur' => '90',
                'type' => 'integer',
                'libelle' => 'Assistance Amana Food : fin (min)',
            ],

            // ── Offsets événements sociaux ─────────────────────────────────
            [
                'cle' => 'offset_annonce_cours_debut',
                'valeur' => '-360',
                'type' => 'integer',
                'libelle' => 'Annonce cours : début (min)',
            ],
            [
                'cle' => 'offset_annonce_cours_fin',
                'valeur' => '-345',
                'type' => 'integer',
                'libelle' => 'Annonce cours : fin (min)',
            ],
            [
                'cle' => 'offset_message_bot_debut',
                'valeur' => '-30',
                'type' => 'integer',
                'libelle' => 'Message bot : début (min)',
            ],
            [
                'cle' => 'offset_message_bot_fin',
                'valeur' => '0',
                'type' => 'integer',
                'libelle' => 'Message bot : fin (min)',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('ref_settings')->updateOrInsert(
                [
                    'id_application' => $planning->id,
                    'cle' => $setting['cle'],
                ],
                [
                    'valeur' => $setting['valeur'],
                    'type' => $setting['type'],
                    'libelle' => $setting['libelle'],
                    'description' => null,
                ]
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
        $this->command->warn('       Restrictions > cocher cours=true pour');
        $this->command->warn('       la personne désignée (tous les autres = false)');
        $this->command->newLine();
        $this->command->info('  Rôles disponibles :');
        $this->command->info('  admin        → accès complet');
        $this->command->info('  gestionnaire → planning + événements + absences + restrictions');
        $this->command->info('  membre       → lecture + ses propres données');
        $this->command->info('  benevole     → rôle bénévole');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}