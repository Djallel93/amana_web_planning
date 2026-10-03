<?php
// database/factories/EvenementFactory.php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Evenement;
use App\Models\Tache;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Evenement> */
class EvenementFactory extends Factory
{
    protected $model = Evenement::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->sentence(3),
            'date_debut' => '2031-06-10',
            'date_fin' => '2031-06-10',
            'description' => null,
            'couleur' => null, // colorId Google ('1'..'11') ; null = couleur du calendrier
        ];
    }

    public function du(CarbonInterface|string $debut, CarbonInterface|string $fin): static
    {
        return $this->state([
            'date_debut' => Carbon::parse($debut)->toDateString(),
            'date_fin' => Carbon::parse($fin)->toDateString(),
        ]);
    }

    /** Événement qui bloque ces tâches (ref_evenements_taches). */
    public function bloquant(Tache ...$taches): static
    {
        return $this->afterCreating(function (Evenement $evenement) use ($taches) {
            $evenement->tachesBloquees()->syncWithoutDetaching(array_map(fn(Tache $t) => $t->id, $taches));
        });
    }
}
