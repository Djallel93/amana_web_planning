<?php
// tests/Feature/Factories/FactoriesTest.php
//
// Garde-fous sur les factories elles-mêmes, en particulier le piège des deux
// connexions (voir Restriction.php) : chaque modèle doit lire/écrire dans la
// bonne base, y compris quand on passe par une relation partant de Personne.

declare(strict_types=1);

namespace Tests\Feature\Factories;

use App\Models\Absence;
use App\Models\Bilan;
use App\Models\CalendrierGoogle;
use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Echange;
use App\Models\Evenement;
use App\Models\Personne;
use App\Models\Restriction;
use App\Models\Tache;
use Database\Factories\TacheFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class FactoriesTest extends TestCase
{
    use RefreshesBothDatabases;

    private function defaut(): string
    {
        return config('database.default');
    }

    private function commun(): string
    {
        return config('amana-shared.connection', 'commun');
    }

    public function test_les_deux_connexions_pointent_vers_deux_bases_de_test_distinctes(): void
    {
        $this->assertNotSame(
            DB::connection($this->defaut())->getDatabaseName(),
            DB::connection($this->commun())->getDatabaseName(),
        );
        $this->assertSame('amana_planning_test', DB::connection($this->defaut())->getDatabaseName());
        $this->assertSame('amana_commun_test', DB::connection($this->commun())->getDatabaseName());
    }

    public function test_chaque_test_est_enveloppe_dans_une_transaction_sur_les_deux_connexions(): void
    {
        $this->assertGreaterThanOrEqual(1, DB::connection($this->defaut())->transactionLevel());
        $this->assertGreaterThanOrEqual(1, DB::connection($this->commun())->transactionLevel());
    }

    public function test_personne_est_creee_dans_commun_et_pas_dans_la_base_du_planning(): void
    {
        $personne = Personne::factory()->create();

        $this->assertSame($this->commun(), $personne->getConnectionName());
        $this->assertTrue(DB::connection($this->commun())->table('ref_personnes')->where('id', $personne->id)->exists());
        $this->assertFalse(
            Schema::connection($this->defaut())->hasTable('ref_personnes'),
            'ref_personnes ne doit pas exister dans la base du planning (voir la migration drop_shadow_commun_tables)',
        );
    }

    public function test_les_modeles_du_planning_sont_sur_la_connexion_par_defaut(): void
    {
        $creations = [
            Tache::factory()->create(),
            Creneau::factory()->create(),
            Absence::factory()->create(),
            Restriction::factory()->create(),
            Evenement::factory()->create(),
            Echange::factory()->create(),
            Bilan::factory()->create(),
            CalendrierGoogle::factory()->create(),
        ];

        foreach ($creations as $modele) {
            $this->assertSame(
                $this->defaut(),
                $modele->getConnection()->getName(),
                get_class($modele) . ' doit utiliser la connexion par défaut',
            );
            $this->assertTrue($modele->exists);
        }

        $this->assertSame(1, DB::connection($this->defaut())->table('plan_absences')->count());
        $this->assertSame(1, DB::connection($this->defaut())->table('plan_restrictions')->count());
    }

    /**
     * Le piège : Absence, Restriction et CreneauTache sont le côté « many » d'un
     * hasMany partant de Personne (commun). Sans getConnectionName() explicite,
     * Eloquent leur ferait hériter de la connexion de Personne.
     */
    public function test_les_relations_depuis_personne_interrogent_la_base_du_planning(): void
    {
        $personne = Personne::factory()->create();

        foreach (['absences', 'restrictions', 'creneauxTaches'] as $relation) {
            $this->assertSame(
                $this->defaut(),
                $personne->{$relation}()->getQuery()->getConnection()->getName(),
                "Personne::{$relation}() doit interroger la connexion par défaut, pas commun",
            );
        }
    }

    public function test_creer_via_la_relation_de_personne_ecrit_dans_la_base_du_planning(): void
    {
        $personne = Personne::factory()->create();
        $tache = Tache::factory()->create();

        $personne->absences()->create(['date_debut' => '2031-01-01', 'date_fin' => '2031-01-02']);
        $personne->restrictions()->create(['id_tache' => $tache->id, 'jour' => 'Mardi', 'autorise' => false]);

        $this->assertSame(1, DB::connection($this->defaut())->table('plan_absences')->where('id_personne', $personne->id)->count());
        $this->assertSame(1, DB::connection($this->defaut())->table('plan_restrictions')->where('id_personne', $personne->id)->count());
        $this->assertTrue($personne->estAbsentLe('2031-01-02'));
    }

    public function test_creneau_tache_est_assignable_et_relisible_par_personne(): void
    {
        $personne = Personne::factory()->create();
        $creneau = Creneau::factory()->create();
        $tache = Tache::factory()->create();

        CreneauTache::factory()->pour($creneau, $tache)->assigneA($personne)->create();

        $this->assertSame(1, $personne->creneauxTaches()->count());
        $this->assertSame($personne->id, (int) CreneauTache::where('id_planning', $creneau->id)->value('id_personne'));

        $orphelin = CreneauTache::factory()->create();
        $this->assertNull(
            DB::connection($this->defaut())->table('plan_creneaux_taches')
                ->where('id_planning', $orphelin->id_planning)->where('id_tache', $orphelin->id_tache)->value('id_personne'),
            'par défaut une tâche n\'est pas assignée',
        );
    }

    public function test_roles(): void
    {
        $admin = Personne::factory()->admin()->create();
        $gestionnaire = Personne::factory()->gestionnaire()->create();
        $membre = Personne::factory()->membre()->create();
        $sansRole = Personne::factory()->create();

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isMembre(), 'admin >= membre');
        $this->assertFalse($admin->isGestionnaire());

        $this->assertTrue($gestionnaire->isGestionnaire());
        $this->assertFalse($gestionnaire->isAdmin());

        $this->assertTrue($membre->isMembre());
        $this->assertFalse($membre->hasAtLeastRole('gestionnaire'));

        $this->assertFalse($sansRole->isMembre());
    }

    public function test_les_roles_ne_sont_pas_dupliques_entre_personnes(): void
    {
        Personne::factory()->count(3)->admin()->create();

        $this->assertSame(1, DB::connection($this->commun())->table('ref_roles')->where('code', 'admin')->count());
        $this->assertSame(1, DB::connection($this->commun())->table('ref_applications')->where('code', 'planning')->count());
    }

    public function test_statuts_de_personne(): void
    {
        $this->assertSame('Validé', Personne::factory()->create()->statut);
        $this->assertSame('En attente', Personne::factory()->enAttente()->create()->statut);
        $this->assertSame('Suspendu', Personne::factory()->suspendu()->create()->statut);
        $this->assertSame('Archivé', Personne::factory()->archive()->create()->statut);
    }

    public function test_pour_code_ne_cree_pas_de_doublon(): void
    {
        $a = TacheFactory::pourCode('entree');
        $b = TacheFactory::pourCode('entree');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Tache::where('code', 'entree')->count());
    }

    public function test_echange_est_coherent_avec_les_creneaux_assignes(): void
    {
        $echange = Echange::factory()->create();

        $this->assertTrue($echange->isEnAttente());
        $this->assertSame(64, strlen($echange->token_accept));
        $this->assertNotSame($echange->token_accept, $echange->token_refuse);

        $this->assertSame(
            $echange->id_personne_demandeur,
            (int) CreneauTache::where('id_planning', $echange->id_creneau_demandeur)
                ->where('id_tache', $echange->id_tache_demandeur)->value('id_personne'),
        );
        $this->assertSame(
            $echange->id_personne_cible,
            (int) CreneauTache::where('id_planning', $echange->id_creneau_cible)
                ->where('id_tache', $echange->id_tache_cible)->value('id_personne'),
        );
    }

    public function test_etats_de_echange(): void
    {
        $this->assertTrue(Echange::factory()->lienExpire()->create()->isExpire());
        $this->assertSame(Echange::STATUT_ACCEPTE, Echange::factory()->accepte()->create()->statut);
        $this->assertSame(Echange::STATUT_EXPIRE, Echange::factory()->expire()->create()->statut);
    }

    public function test_evenement_bloquant(): void
    {
        $tache = Tache::factory()->create();
        $evenement = Evenement::factory()->bloquant($tache)->create();

        $this->assertTrue($evenement->fresh()->bloqueDesTaches());
    }

    public function test_les_dates_par_defaut_ne_collisionnent_pas(): void
    {
        Creneau::factory()->count(3)->create();
        Bilan::factory()->count(3)->create();

        $this->assertSame(3, Creneau::count());
        $this->assertSame(3, Bilan::count());
    }
}
