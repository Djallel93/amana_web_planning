<?php
// database/factories/BilanFactory.php
//
// plan_bilans_quotidiens.date est UNIQUE : compteur à partir de 2031-01-01
// (même convention que CreneauFactory). NULL sur les montants / effectifs =
// « pas de cours ce jour-là » (voir migration) — état sansCours().

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bilan;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Bilan> */
class BilanFactory extends Factory
{
    protected $model = Bilan::class;

    private static int $jours = 0;

    public function definition(): array
    {
        return [
            'date' => Carbon::parse('2031-01-01')->addDays(self::$jours++)->toDateString(),
            'montant_carte' => '120.50',
            'montant_espece' => '80.00',
            'montant_charges' => '15.00',
            'id_personne_maj_food' => null,
            'maj_food_at' => null,
            'nb_presents' => 25,
            'nb_en_ligne' => 10,
            'id_personne_maj_presence' => null,
            'maj_presence_at' => null,
        ];
    }

    public function pourDate(CarbonInterface|string $date): static
    {
        return $this->state(['date' => $date instanceof CarbonInterface ? $date->toDateString() : $date]);
    }

    public function sansCours(): static
    {
        return $this->state([
            'montant_carte' => null,
            'montant_espece' => null,
            'montant_charges' => null,
            'nb_presents' => null,
            'nb_en_ligne' => null,
        ]);
    }
}
