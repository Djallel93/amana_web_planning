<?php
// database/factories/EchangeFactory.php
//
// Un Echange COHÉRENT : demandeur et cible existent, chacun a un créneau et
// une tâche, et plan_creneaux_taches contient bien les deux lignes assignées
// (c'est ce que EchangeService::executerEchange() échange). Sans elles, un
// test du swap passerait ou échouerait pour de mauvaises raisons. Pour tester
// l'absence de ces lignes, supprimer-les explicitement dans le test.
//
// Par défaut : en_attente, expire dans 3 jours, tokens uniques de 64 caractères.

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Creneau;
use App\Models\Echange;
use App\Models\Personne;
use App\Models\Tache;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** @extends Factory<Echange> */
class EchangeFactory extends Factory
{
    protected $model = Echange::class;

    public function definition(): array
    {
        return [
            'id_personne_demandeur' => Personne::factory(),
            'id_creneau_demandeur' => Creneau::factory(),
            'id_tache_demandeur' => Tache::factory(),
            'id_personne_cible' => Personne::factory(),
            'id_creneau_cible' => Creneau::factory(),
            'id_tache_cible' => Tache::factory(),
            'statut' => Echange::STATUT_EN_ATTENTE,
            'token_accept' => Str::random(64),
            'token_refuse' => Str::random(64),
            'expires_at' => now()->addDays(3),
            'approuve_par' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Echange $echange) {
            // Connexion par défaut EXPLICITE : CreneauTache n'a pas de clé primaire,
            // updateOrCreate()/save() sur une ligne existante échouerait.
            // Un constructeur de requête NEUF par appel : un même builder réutilisé
            // accumulerait les clauses where du premier updateOrInsert().
            $ligne = fn() => DB::connection(config('database.default'))->table('plan_creneaux_taches');

            $ligne()->updateOrInsert(
                ['id_planning' => $echange->id_creneau_demandeur, 'id_tache' => $echange->id_tache_demandeur],
                ['id_personne' => $echange->id_personne_demandeur],
            );
            $ligne()->updateOrInsert(
                ['id_planning' => $echange->id_creneau_cible, 'id_tache' => $echange->id_tache_cible],
                ['id_personne' => $echange->id_personne_cible],
            );
        });
    }

    public function accepte(): static
    {
        return $this->state(['statut' => Echange::STATUT_ACCEPTE]);
    }

    public function refuse(): static
    {
        return $this->state(['statut' => Echange::STATUT_REFUSE]);
    }

    public function annule(): static
    {
        return $this->state(['statut' => Echange::STATUT_ANNULE]);
    }

    /** Statut resté « en_attente » mais lien périmé (cas de expirerEchanges / lien expiré). */
    public function lienExpire(): static
    {
        return $this->state(['expires_at' => now()->subHour()]);
    }

    /** Statut « expire » posé (après passage de expirerEchanges). */
    public function expire(): static
    {
        return $this->state(['statut' => Echange::STATUT_EXPIRE, 'expires_at' => now()->subHour()]);
    }
}
