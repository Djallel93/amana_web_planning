<?php
// database/factories/TacheFactory.php
//
// ref_taches.code est UNIQUE et la migration seed déjà 'annulation_cours'.
// Pour un code métier précis (entree, amana_food, cours…), utiliser
// TacheFactory::pourCode() — find-or-create — plutôt que ->create(['code' => …])
// qui échoue dès que deux fixtures demandent la même tâche.

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tache;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tache> */
class TacheFactory extends Factory
{
    protected $model = Tache::class;

    private static int $n = 0;

    public function definition(): array
    {
        $n = ++self::$n;

        return [
            'code' => "tache_test_{$n}",
            'libelle' => "Tâche de test {$n}",
            'description' => "Description de la tâche de test {$n}",
            'description_calendrier' => null,
            'actif' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['actif' => false]);
    }

    public static function pourCode(string $code, array $attributs = []): Tache
    {
        return Tache::firstOrCreate(
            ['code' => $code],
            $attributs + [
                'libelle' => ucfirst(str_replace('_', ' ', $code)),
                'description' => "Tâche {$code}",
                'actif' => true,
            ],
        );
    }
}
