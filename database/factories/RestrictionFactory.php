<?php
// database/factories/RestrictionFactory.php
//
// Une ligne plan_restrictions dit « cette personne peut / ne peut pas faire
// cette tâche ce jour-là » (Personne::peutFaireTache : pas de ligne = autorisé).
// Par défaut autorise = false : une restriction qui autorise n'a aucun effet et
// ne sert qu'à tester ce cas précis (état autorisee()).
// UNIQUE (id_personne, id_tache, jour) : ne pas créer deux fois le même triplet.
//
// Modèle « côté many » de Personne::restrictions() : voir Restriction.php.

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Personne;
use App\Models\Restriction;
use App\Models\Tache;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Restriction> */
class RestrictionFactory extends Factory
{
    protected $model = Restriction::class;

    public function definition(): array
    {
        return [
            'id_personne' => Personne::factory(),
            'id_tache' => Tache::factory(),
            'jour' => 'Lundi',
            'autorise' => false,
        ];
    }

    public function autorisee(): static
    {
        return $this->state(['autorise' => true]);
    }

    public function le(string $jour): static
    {
        return $this->state(['jour' => $jour]);
    }

    public function pour(Personne|int $personne, Tache|int $tache): static
    {
        return $this->state([
            'id_personne' => $personne instanceof Personne ? $personne->id : $personne,
            'id_tache' => $tache instanceof Tache ? $tache->id : $tache,
        ]);
    }
}
