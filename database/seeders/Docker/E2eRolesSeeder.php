<?php
// database/seeders/Docker/E2eRolesSeeder.php

declare(strict_types=1);

namespace Database\Seeders\Docker;

use App\Models\Personne;
use App\Services\RoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Un compte par rôle planning, pour les tests Playwright et la QA visuelle
 * (14/10/2026, décision D18).
 *
 * Idempotent. Refuse de s'exécuter hors environnement local/testing : ces
 * comptes ont un mot de passe connu et ne doivent JAMAIS exister en
 * préprod ou en production.
 *
 * Prérequis : DatabaseSeeder (application 'planning' + rôles) déjà exécuté.
 * Utilisation (voir `make fresh`, docs/docker.md) :
 *   php artisan db:seed --class='Database\Seeders\Docker\E2eRolesSeeder'
 *
 * Connexion : e2e-<rôle>@amana.test / mot de passe ci-dessous.
 */
class E2eRolesSeeder extends Seeder
{
    public const PASSWORD = 'e2e-password';

    /** rôle planning => [prénom, nom, statut] ; null = aucun rôle attribué */
    private const COMPTES = [
        'admin' => ['Ada', 'E2E-Admin', 'Validé'],
        'gestionnaire' => ['Gaëlle', 'E2E-Gestionnaire', 'Validé'],
        'membre' => ['Malik', 'E2E-Membre', 'Validé'],
        'benevole' => ['Bilel', 'E2E-Benevole', 'Validé'],
        'attente' => ['Anis', 'E2E-Attente', 'En attente'],
    ];

    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command->error('E2eRolesSeeder : refusé hors environnement local/testing.');

            return;
        }

        foreach (self::COMPTES as $role => [$prenom, $nom, $statut]) {
            $personne = Personne::updateOrCreate(
                ['email' => "e2e-{$role}@amana.test"],
                [
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'password' => Hash::make(self::PASSWORD),
                    'statut' => $statut,
                    'date_debut_planning' => now()->subMonths(2)->toDateString(),
                ]
            );

            // email_verified_at n'est pas mass-assignable (voir amana/shared,
            // Personne::$fillable) : affectation explicite.
            $personne->email_verified_at = $statut === 'Validé' ? now() : null;
            $personne->save();

            // Le compte « attente » n'a volontairement aucun rôle planning.
            if ($role !== 'attente') {
                $this->roleService->syncRolePlanning($personne, $role);
            }
        }

        $this->command->info('✅ Comptes e2e : e2e-{admin,gestionnaire,membre,benevole,attente}@amana.test');
        $this->command->info('   Mot de passe : ' . self::PASSWORD);
    }
}
