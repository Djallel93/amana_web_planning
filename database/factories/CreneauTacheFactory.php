<?php
// database/factories/CreneauTacheFactory.php
//
// plan_creneaux_taches : clé primaire composite (id_planning, id_tache), et le
// modèle déclare $primaryKey = null. On peut donc créer une ligne, mais pas la
// ->fresh() / ->refresh() / ->save() une seconde fois : relire via une requête.
//
// Modèle « côté many » de Personne::creneauxTaches() : il déclare
// getConnectionName() (connexion par défaut). Les dépendances Creneau/Tache
// sont sur la connexion par défaut, Personne sur commun — chaque factory
// imbriquée passe par le getConnectionName() de SON modèle.

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Personne;
use App\Models\Tache;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CreneauTache> */
class CreneauTacheFactory extends Factory
{
    protected $model = CreneauTache::class;

    public function definition(): array
    {
        return [
            'id_planning' => Creneau::factory(),
            'id_tache' => Tache::factory(),
            'id_personne' => null, // NULL = tâche non assignée
        ];
    }

    public function assigneA(Personne|int $personne): static
    {
        return $this->state(['id_personne' => $personne instanceof Personne ? $personne->id : $personne]);
    }

    public function pour(Creneau $creneau, Tache $tache): static
    {
        return $this->state(['id_planning' => $creneau->id, 'id_tache' => $tache->id]);
    }
}
