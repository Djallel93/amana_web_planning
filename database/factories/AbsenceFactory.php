<?php
// database/factories/AbsenceFactory.php
//
// Modèle « côté many » de Personne::absences() : getConnectionName() déclaré
// explicitement (voir Absence.php). id_personne n'a pas de clé étrangère SQL
// (la personne est dans une autre base), rien ne protège d'un id inexistant :
// la factory crée donc une vraie Personne par défaut.

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Absence;
use App\Models\Personne;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Absence> */
class AbsenceFactory extends Factory
{
    protected $model = Absence::class;

    public function definition(): array
    {
        return [
            'id_personne' => Personne::factory(),
            'date_debut' => '2031-03-03',
            'date_fin' => '2031-03-05',
            'raison' => null,
            'google_calendar_id' => null,
            'google_event_id' => null,
        ];
    }

    public function du(CarbonInterface|string $debut, CarbonInterface|string $fin): static
    {
        return $this->state([
            'date_debut' => Carbon::parse($debut)->toDateString(),
            'date_fin' => Carbon::parse($fin)->toDateString(),
        ]);
    }

    public function pour(Personne|int $personne): static
    {
        return $this->state(['id_personne' => $personne instanceof Personne ? $personne->id : $personne]);
    }

    public function synchronisee(string $calendrierId = 'cal_test@group.calendar.google.com', string $evenementId = 'evt_test'): static
    {
        return $this->state(['google_calendar_id' => $calendrierId, 'google_event_id' => $evenementId]);
    }
}
