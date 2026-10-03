<?php
// database/factories/PersonneFactory.php
//
// Factory de App\Models\Personne (étend Amana\Shared\Models\Personne, table
// ref_personnes de la base COMMUN). Les rôles vivent eux aussi dans commun
// (ref_applications / ref_roles / ref_personnes_roles) : les états admin(),
// gestionnaire()… créent l'application 'planning' et le rôle à la demande
// (idempotent, même logique que PlanningApplicationSeeder) — rien n'est à
// seeder avant. Tout se fait DANS la transaction du test, donc annulé avec elle.

declare(strict_types=1);

namespace Database\Factories;

use Amana\Shared\Models\Application;
use Amana\Shared\Models\Role;
use App\Models\Personne;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<Personne> */
class PersonneFactory extends Factory
{
    protected $model = Personne::class;

    public const MOT_DE_PASSE = 'password';

    private static ?string $hash = null;

    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::$hash ??= Hash::make(self::MOT_DE_PASSE),
            'telephone' => null,
            'date_debut_planning' => null,
            // Validé par défaut : la plupart des tests ont besoin d'une
            // personne active. Les autres statuts ont un état dédié.
            'statut' => 'Validé',
        ];
    }

    public function enAttente(): static
    {
        return $this->state(['statut' => 'En attente']);
    }

    public function suspendu(): static
    {
        return $this->state(['statut' => 'Suspendu']);
    }

    public function archive(): static
    {
        return $this->state(['statut' => 'Archivé']);
    }

    public function dansLaRotationDepuis(CarbonInterface|string $date): static
    {
        return $this->state(['date_debut_planning' => $date instanceof CarbonInterface ? $date->toDateString() : $date]);
    }

    public function sansMotDePasse(): static
    {
        return $this->state(['password' => null]);
    }

    /** Rôle planning : 'admin' | 'gestionnaire' | 'membre' | 'benevole'. */
    public function avecRole(string $code): static
    {
        return $this->afterCreating(function (Personne $personne) use ($code) {
            $personne->roles()->syncWithoutDetaching([self::role($code)->id]);
        });
    }

    public function admin(): static
    {
        return $this->avecRole('admin');
    }

    public function gestionnaire(): static
    {
        return $this->avecRole('gestionnaire');
    }

    public function membre(): static
    {
        return $this->avecRole('membre');
    }

    public function benevole(): static
    {
        return $this->avecRole('benevole');
    }

    /** Rôle planning `$code`, créé (avec l'application) s'il n'existe pas encore. */
    public static function role(string $code): Role
    {
        $application = Application::firstOrCreate(
            ['code' => 'planning'],
            ['libelle' => 'AMANA Planning', 'actif' => true],
        );

        return Role::firstOrCreate(
            ['code' => $code, 'id_application' => $application->id],
            ['libelle' => ucfirst($code)],
        );
    }
}
