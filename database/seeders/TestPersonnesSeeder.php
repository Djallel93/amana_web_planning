<?php
// database/seeders/TestPersonnesSeeder.php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Personne;
use App\Models\Restriction;
use App\Models\Tache;
use App\Services\RoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de données de test pour le développement local.
 *
 * Crée 10 personnes "Validé" avec un mot de passe connu, prêtes à être
 * utilisées dans le scheduler, et leur attribue des restrictions variées
 * pour tester les différents chemins de l'algorithme :
 *   - rotation stricte amana_food (avec des personnes inéligibles)
 *   - convention "une seule personne sur cours"
 *   - disponibilité partielle réaliste sur d'autres tâches
 *
 * Idempotent : peut être relancé sans créer de doublons ni dupliquer
 * les restrictions.
 *
 * Utilisation :
 *   php artisan db:seed --class=TestPersonnesSeeder
 *
 * Connexion de test :
 *   Email    : test1@amana.fr … test10@amana.fr
 *   Password : password
 */
class TestPersonnesSeeder extends Seeder
{
    private const NB_PERSONNES = 10;
    private const JOURS = ['Vendredi', 'Samedi'];

    /**
     * Index (1-based) des personnes explicitement inéligibles à amana_food.
     */
    private const INELIGIBLES_AMANA_FOOD = [2, 7];

    /**
     * Index (1-based) de la personne désignée pour le cours.
     */
    private const PERSONNE_COURS = 1;

    /**
     * Index (1-based) des personnes ayant quelques restrictions
     * partielles supplémentaires, pour de la disponibilité réaliste.
     * Format : [index => [[tache_code, jour], ...]]
     */
    private const RESTRICTIONS_PARTIELLES = [
        3 => [['salle', 'Samedi']],
        5 => [['mektaba', 'Vendredi']],
        9 => [['entree', 'Samedi'], ['salle', 'Vendredi']],
    ];

    private array $prenoms = [
        'Yasmine',
        'Karim',
        'Sofia',
        'Bilal',
        'Amina',
        'Hamza',
        'Leila',
        'Omar',
        'Nadia',
        'Tariq',
    ];

    private array $noms = [
        'Benali',
        'Cherif',
        'Haddad',
        'Khalil',
        'Mansour',
        'Said',
        'Toumi',
        'Ziani',
        'Belkacem',
        'Idrissi',
    ];

    public function __construct(
        private readonly RoleService $roleService,
    ) {
    }

    public function run(): void
    {
        $taches = Tache::actif()->get()->keyBy('code');

        if ($taches->isEmpty()) {
            $this->command->warn(
                'Aucune tâche active trouvée — lancez d\'abord DatabaseSeeder.'
            );
            return;
        }

        $this->command->info('Création de ' . self::NB_PERSONNES . ' personnes de test…');

        for ($i = 1; $i <= self::NB_PERSONNES; $i++) {
            $personne = $this->creerPersonne($i);
            $this->roleService->syncRolePlanning($personne, 'membre');
            $this->appliquerRestrictions($personne, $i, $taches);
        }

        $this->command->info('✅ ' . self::NB_PERSONNES . ' personnes de test créées/mises à jour.');
        $this->command->newLine();
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  Connexion de test :');
        $this->command->info('  Email    : test1@amana.fr … test' . self::NB_PERSONNES . '@amana.fr');
        $this->command->info('  Password : password');
        $this->command->newLine();
        $this->command->info('  Personne ' . self::PERSONNE_COURS . ' : seule autorisée sur "cours"');
        $this->command->info('  Personnes ' . implode(', ', self::INELIGIBLES_AMANA_FOOD) . ' : inéligibles à "amana_food"');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    private function creerPersonne(int $index): Personne
    {
        $email = "test{$index}@amana.fr";

        return Personne::updateOrCreate(
            ['email' => $email],
            [
                'nom' => $this->noms[$index - 1] ?? "Test{$index}",
                'prenom' => $this->prenoms[$index - 1] ?? "Personne{$index}",
                'password' => Hash::make('password'),
                'telephone' => null,
                'statut' => 'Validé',
                'date_debut_planning' => now()->subMonths(2)->toDateString(),
                'email_verified_at' => now(),
            ]
        );
    }

    private function appliquerRestrictions(Personne $personne, int $index, $taches): void
    {
        // ── amana_food : certaines personnes explicitement inéligibles ──
        if (isset($taches['amana_food']) && in_array($index, self::INELIGIBLES_AMANA_FOOD, true)) {
            foreach (self::JOURS as $jour) {
                Restriction::updateOrCreate(
                    ['id_personne' => $personne->id, 'id_tache' => $taches['amana_food']->id, 'jour' => $jour],
                    ['autorise' => false]
                );
            }
        }

        // ── cours : une seule personne autorisée, tous les autres interdits ──
        if (isset($taches['cours'])) {
            $autoriseCours = ($index === self::PERSONNE_COURS);

            foreach (self::JOURS as $jour) {
                Restriction::updateOrCreate(
                    ['id_personne' => $personne->id, 'id_tache' => $taches['cours']->id, 'jour' => $jour],
                    ['autorise' => $autoriseCours]
                );
            }
        }

        // ── Restrictions partielles : disponibilité réaliste ──
        if (isset(self::RESTRICTIONS_PARTIELLES[$index])) {
            foreach (self::RESTRICTIONS_PARTIELLES[$index] as [$code, $jour]) {
                if (!isset($taches[$code])) {
                    continue;
                }

                Restriction::updateOrCreate(
                    ['id_personne' => $personne->id, 'id_tache' => $taches[$code]->id, 'jour' => $jour],
                    ['autorise' => false]
                );
            }
        }
    }
}