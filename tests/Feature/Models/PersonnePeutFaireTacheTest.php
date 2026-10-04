<?php
// tests/Feature/Models/PersonnePeutFaireTacheTest.php
//
// Personne::peutFaireTache() : RotationEngine et DataLoader en dépendent
// entièrement. Deux filtres, dans cet ordre :
//   1. le filtre de RÔLE (config planning.role_task_restrictions : les
//      bénévoles ne font que entree / salle / amana_food) ;
//   2. les lignes plan_restrictions (personne × tâche × jour) ; pas de ligne
//      = autorisé.
// Le filtre de rôle gagne toujours : une restriction « autorise = true » ne
// peut pas rouvrir une tâche que le rôle interdit.

declare(strict_types=1);

namespace Tests\Feature\Models;

use Amana\Shared\Models\Application;
use Amana\Shared\Models\Role;
use App\Models\Personne;
use App\Models\Restriction;
use Database\Factories\PersonneFactory;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class PersonnePeutFaireTacheTest extends TestCase
{
    use CreeDonneesPlanning;
    use RefreshesBothDatabases;

    private array $taches;

    protected function setUp(): void
    {
        parent::setUp();
        $this->taches = $this->tachesDeRotation();
    }

    private function id(string $code): int
    {
        return $this->taches[$code]->id;
    }

    public function test_sans_role_ni_restriction_tout_est_permis(): void
    {
        $personne = Personne::factory()->create();

        foreach (self::CODES_TACHES as $code) {
            foreach (['Vendredi', 'Samedi'] as $jour) {
                $this->assertTrue($personne->peutFaireTache($this->id($code), $jour), "{$code} {$jour}");
            }
        }
    }

    public function test_une_restriction_interdit_seulement_cette_tache_ce_jour_la(): void
    {
        $personne = Personne::factory()->create();
        Restriction::factory()->pour($personne, $this->taches['entree'])->le('Vendredi')->create();

        $this->assertFalse($personne->peutFaireTache($this->id('entree'), 'Vendredi'));
        $this->assertTrue($personne->peutFaireTache($this->id('entree'), 'Samedi'), 'autre jour');
        $this->assertTrue($personne->peutFaireTache($this->id('salle'), 'Vendredi'), 'autre tâche');
    }

    public function test_les_restrictions_d_une_personne_ne_touchent_pas_les_autres(): void
    {
        [$a, $b] = [Personne::factory()->create(), Personne::factory()->create()];
        Restriction::factory()->pour($a, $this->taches['entree'])->le('Vendredi')->create();

        $this->assertFalse($a->peutFaireTache($this->id('entree'), 'Vendredi'));
        $this->assertTrue($b->peutFaireTache($this->id('entree'), 'Vendredi'));
    }

    public function test_une_restriction_qui_autorise_ne_bloque_pas(): void
    {
        $personne = Personne::factory()->create();
        Restriction::factory()->pour($personne, $this->taches['entree'])->le('Vendredi')->autorisee()->create();

        $this->assertTrue($personne->peutFaireTache($this->id('entree'), 'Vendredi'));
    }

    public function test_un_benevole_ne_peut_faire_que_entree_salle_et_amana_food(): void
    {
        $benevole = Personne::factory()->benevole()->create();

        foreach (['entree', 'salle', 'amana_food'] as $code) {
            $this->assertTrue($benevole->peutFaireTache($this->id($code), 'Vendredi'), $code);
        }
        foreach (['mektaba', 'cours'] as $code) {
            $this->assertFalse($benevole->peutFaireTache($this->id($code), 'Vendredi'), $code);
            $this->assertFalse($benevole->peutFaireTache($this->id($code), 'Samedi'), $code);
        }
    }

    public function test_les_restrictions_s_appliquent_aussi_dans_le_perimetre_du_role(): void
    {
        $benevole = Personne::factory()->benevole()->create();
        Restriction::factory()->pour($benevole, $this->taches['entree'])->le('Vendredi')->create();

        $this->assertFalse($benevole->peutFaireTache($this->id('entree'), 'Vendredi'));
        $this->assertTrue($benevole->peutFaireTache($this->id('entree'), 'Samedi'));
        $this->assertTrue($benevole->peutFaireTache($this->id('salle'), 'Vendredi'));
    }

    public function test_le_filtre_de_role_l_emporte_sur_une_restriction_qui_autorise(): void
    {
        $benevole = Personne::factory()->benevole()->create();
        Restriction::factory()->pour($benevole, $this->taches['cours'])->le('Vendredi')->autorisee()->create();

        $this->assertFalse($benevole->peutFaireTache($this->id('cours'), 'Vendredi'));
    }

    public function test_les_autres_roles_ne_sont_pas_limites(): void
    {
        foreach (['membre', 'gestionnaire', 'admin'] as $role) {
            $personne = Personne::factory()->{$role}()->create();

            $this->assertTrue($personne->peutFaireTache($this->id('cours'), 'Vendredi'), $role);
            $this->assertTrue($personne->peutFaireTache($this->id('mektaba'), 'Samedi'), $role);
        }
    }

    public function test_un_benevole_ne_peut_faire_aucune_tache_inconnue_du_referentiel(): void
    {
        $benevole = Personne::factory()->benevole()->create();

        $this->assertFalse($benevole->peutFaireTache(999999, 'Vendredi'));
    }

    public function test_la_liste_des_taches_du_role_vient_de_la_config(): void
    {
        config(['planning.role_task_restrictions' => ['membre' => ['cours']]]);
        $membre = Personne::factory()->membre()->create();
        $benevole = Personne::factory()->benevole()->create();

        $this->assertTrue($membre->peutFaireTache($this->id('cours'), 'Vendredi'));
        $this->assertFalse($membre->peutFaireTache($this->id('entree'), 'Vendredi'));
        $this->assertTrue($benevole->peutFaireTache($this->id('cours'), 'Vendredi'), 'plus aucune restriction pour benevole');
    }

    public function test_sans_aucune_restriction_de_role_configuree_tout_est_permis(): void
    {
        config(['planning.role_task_restrictions' => []]);
        $benevole = Personne::factory()->benevole()->create();

        $this->assertTrue($benevole->peutFaireTache($this->id('cours'), 'Vendredi'));
    }

    public function test_un_role_d_une_autre_application_ne_compte_pas(): void
    {
        $personne = Personne::factory()->create();
        $autre = Application::create(['code' => 'autre', 'libelle' => 'Autre app', 'actif' => true]);
        $roleAutre = Role::create(['code' => 'benevole', 'libelle' => 'Bénévole (autre app)', 'id_application' => $autre->id]);
        $personne->roles()->attach($roleAutre->id);

        $this->assertTrue($personne->peutFaireTache($this->id('cours'), 'Vendredi'), 'seul le rôle de l\'application planning est regardé');
    }

    public function test_le_role_est_memorise_par_instance_mais_relu_sur_une_nouvelle_instance(): void
    {
        $personne = Personne::factory()->benevole()->create();
        $this->assertFalse($personne->peutFaireTache($this->id('cours'), 'Vendredi'));

        // Passage en membre : l'instance déjà interrogée garde son rôle en mémoire…
        $personne->roles()->sync([PersonneFactory::role('membre')->id]);
        $this->assertFalse($personne->peutFaireTache($this->id('cours'), 'Vendredi'));

        // … une instance rechargée voit le nouveau rôle.
        $this->assertTrue(Personne::find($personne->id)->peutFaireTache($this->id('cours'), 'Vendredi'));
    }

    public function test_le_cache_des_codes_de_taches_est_remis_a_zero_entre_les_tests(): void
    {
        // Filet de sécurité de Tests\TestCase::reinitialiserCachesStatiques() : les ids de
        // tâches changent d'un test à l'autre (transaction annulée, auto-incrément conservé).
        $benevole = Personne::factory()->benevole()->create();

        $this->assertTrue($benevole->peutFaireTache($this->id('entree'), 'Vendredi'));
        $this->assertFalse($benevole->peutFaireTache($this->id('cours'), 'Vendredi'));
    }
}
