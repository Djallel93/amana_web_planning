<?php
// database/factories/CreneauFactory.php
//
// plan_creneaux.date est UNIQUE : les dates par défaut viennent d'un compteur
// à partir de 2031-01-01, loin des dates utilisées dans les tests (2026) pour
// ne jamais entrer en collision avec un ->pourDate() explicite.

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Creneau;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Creneau> */
class CreneauFactory extends Factory
{
    protected $model = Creneau::class;

    private static int $jours = 0;

    public function definition(): array
    {
        return [
            'date' => Carbon::parse('2031-01-01')->addDays(self::$jours++)->toDateString(),
        ];
    }

    public function pourDate(CarbonInterface|string $date): static
    {
        return $this->state(['date' => $date instanceof CarbonInterface ? $date->toDateString() : $date]);
    }
}
