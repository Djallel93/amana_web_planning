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

/**
 * Seeder principal.
 *
 * Insère toutes les données de référence indispensables au fonctionnement :
 *
 *   1. Application 'planning' (ref_applications)
 *   2. Tâches planifiables (ref_taches)
 *   3. Rôles liés à l'application planning (ref_roles)
 *   4. Compte administrateur dans ref_personnes
 *   5. Attribution du rôle admin à l'administrateur
 *
 * Idempotent : peut être relancé plusieurs fois sans créer de doublons
 * grâce à firstOrCreate() et updateOrCreate().
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

        // ── 2. Tâches planifiables ─────────────────────────────────────────
        $taches = [
            ['code' => 'entree', 'libelle' => 'Entrée', 'actif' => true],
            ['code' => 'mektaba', 'libelle' => 'Médiathèque', 'actif' => true],
            ['code' => 'salle', 'libelle' => 'Salle', 'actif' => true],
            ['code' => 'amana_food', 'libelle' => 'Amana Food', 'actif' => true],
            ['code' => 'cours', 'libelle' => 'Cours', 'actif' => true],
        ];

        foreach ($taches as $tache) {
            Tache::firstOrCreate(['code' => $tache['code']], $tache);
        }
        $this->command->info('✅ Tâches insérées : ' . count($taches));

        // ── 3. Rôles liés à l'application planning ─────────────────────────
        //
        // admin  : accès complet à toutes les fonctionnalités
        // membre : accès lecture + gestion de ses propres données
        //          (absences, restrictions)
        //
        $roles = [
            [
                'code' => 'admin',
                'libelle' => 'Administrateur',
                'id_application' => $planning->id,
            ],
            [
                'code' => 'gestionnaire',
                'libelle' => 'Gestionnaire',
                'id_application' => $planning->id,
            ],
            [
                'code' => 'membre',
                'libelle' => 'Membre officiel',
                'id_application' => $planning->id,
            ],
            [
                'code' => 'benevole',
                'libelle' => 'Bénévole',
                'id_application' => $planning->id,
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                [
                    'code' => $role['code'],
                    'id_application' => $role['id_application'],
                ],
                ['libelle' => $role['libelle']]
            );
        }
        $this->command->info('✅ Rôles insérés : ' . count($roles));

        // ── 4. Compte administrateur dans ref_personnes ────────────────────
        //
        // L'admin est maintenant une Personne à part entière.
        // Son mot de passe par défaut est 'changeme123!' — à changer
        // immédiatement après la première connexion en production.
        //
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

        // ── 5. Type vehicules ───────────────────────────────────

        DB::table('ref_vehicules')->insert([
            ['id' => 1, 'type' => 'Citadine', 'capacite_kg' => 150, 'nombre_parts_max' => 6],
            ['id' => 2, 'type' => 'Berline', 'capacite_kg' => 250, 'nombre_parts_max' => 8],
            ['id' => 3, 'type' => 'Break', 'capacite_kg' => 300, 'nombre_parts_max' => 15],
            ['id' => 4, 'type' => 'Monospace', 'capacite_kg' => 400, 'nombre_parts_max' => 20],
            ['id' => 5, 'type' => 'Fourgon moyen', 'capacite_kg' => 700, 'nombre_parts_max' => 30],
            ['id' => 6, 'type' => 'Grands fourgon', 'capacite_kg' => 1000, 'nombre_parts_max' => 50],
            ['id' => 7, 'type' => 'Permis', 'capacite_kg' => 0, 'nombre_parts_max' => 0],
            ['id' => 8, 'type' => 'Sans permis', 'capacite_kg' => 0, 'nombre_parts_max' => 0],
        ]);

        $this->command->info('✅ Type vehicule ajoutees');

        // ── 6. Donnees geographique ───────────────────────────────────

        $this->call(GeoSeeder::class);
        $this->command->info('✅ Donnees Geo ajoutees');

        // ── 7. Attribution du rôle admin ───────────────────────────────────
        //
        // On utilise syncWithoutDetaching pour ne pas supprimer d'autres
        // rôles éventuellement déjà attribués à cette personne.
        //
        $roleAdmin = Role::where('code', 'admin')
            ->where('id_application', $planning->id)
            ->first();

        if ($roleAdmin) {
            // Vérifier si le rôle n'est pas déjà attribué
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

        // ── Récapitulatif ──────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  Connexion admin :');
        $this->command->info('  Email    : admin@amana.fr');
        $this->command->info('  Mot de passe : changeme123!');
        $this->command->warn('  ⚠️  Changez ce mot de passe en production !');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}