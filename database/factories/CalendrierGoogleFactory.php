<?php
// database/factories/CalendrierGoogleFactory.php
//
// Aucun appel Google : ce n'est qu'une ligne du registre ref_calendriers_google.

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CalendrierGoogle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CalendrierGoogle> */
class CalendrierGoogleFactory extends Factory
{
    protected $model = CalendrierGoogle::class;

    public function definition(): array
    {
        return [
            'calendar_id' => fake()->unique()->lexify('????????????') . '@group.calendar.google.com',
            'nom' => 'Calendrier ' . fake()->unique()->word(),
            'description' => null,
            'actif' => true,
            'inclure_nouveaux_membres' => false,
            'derniere_verification_at' => null,
        ];
    }

    public function inactif(): static
    {
        return $this->state(['actif' => false]);
    }

    public function partageAvecNouveauxMembres(): static
    {
        return $this->state(['inclure_nouveaux_membres' => true]);
    }
}
