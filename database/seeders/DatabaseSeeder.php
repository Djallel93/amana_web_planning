<?php
// database/seeders/DatabaseSeeder.php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tache;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder principal.
 * Insère les données de référence indispensables au fonctionnement :
 *  - Les 4 tâches (entree, mektaba, salle, amana_food)
 *  - Les rôles (admin, membre, benevole)
 *  - Un utilisateur admin par défaut
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Tâches planifiables ─────────────────────────────────────────
        $taches = [
            ['code' => 'entree',     'libelle' => 'Entrée',       'actif' => true],
            ['code' => 'mektaba',    'libelle' => 'Médiathèque',  'actif' => true],
            ['code' => 'salle',      'libelle' => 'Salle',        'actif' => true],
            ['code' => 'amana_food', 'libelle' => 'Amana Food',   'actif' => true],
        ];

        foreach ($taches as $tache) {
            Tache::firstOrCreate(['code' => $tache['code']], $tache);
        }
        $this->command->info('✅ Tâches insérées : ' . count($taches));

        // ── 2. Rôles ──────────────────────────────────────────────────────
        $roles = [
            ['code' => 'admin',    'libelle' => 'Administrateur'],
            ['code' => 'membre',   'libelle' => 'Membre officiel'],
            ['code' => 'benevole', 'libelle' => 'Bénévole'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['code' => $role['code']], $role);
        }
        $this->command->info('✅ Rôles insérés : ' . count($roles));

        // ── 3. Utilisateur admin (table users de Laravel) ─────────────────
        // Cette table est gérée par Laravel pour l'authentification.
        // Elle doit exister via la migration Laravel par défaut.
        if (DB::table('users')->where('email', 'admin@amana.fr')->doesntExist()) {
            DB::table('users')->insert([
                'name'              => 'Admin AMANA',
                'email'             => 'admin@amana.fr',
                'password'          => Hash::make('changeme123!'),
                'email_verified_at' => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
            $this->command->info('✅ Utilisateur admin créé : admin@amana.fr / changeme123!');
            $this->command->warn('⚠️  IMPORTANT : changez ce mot de passe en production !');
        } else {
            $this->command->info('ℹ️  Utilisateur admin déjà existant.');
        }
    }
}
