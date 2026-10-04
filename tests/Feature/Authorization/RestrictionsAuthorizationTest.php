<?php
// tests/Feature/Authorization/RestrictionsAuthorizationTest.php
//
// RestrictionsController::update : la grille personne × tâche × jour. Un compte non privilégié
// n'écrit que SES cases ; gestionnaire et admin réécrivent la grille de TOUTE personne active.

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use Amana\Shared\Models\AuditLog;
use App\Models\Personne;
use App\Models\Restriction;
use Database\Factories\TacheFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ConnecteParRole;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class RestrictionsAuthorizationTest extends TestCase
{
    use ConnecteParRole;
    use CreeDonneesPlanning;
    use RefreshesBothDatabases;

    /** @return array<string, array{string}> */
    public static function nonPrivilegies(): array
    {
        return ['sans rôle' => ['sans_role'], 'bénévole' => ['benevole'], 'membre' => ['membre']];
    }

    /** @return array<string, array{string}> */
    public static function privilegies(): array
    {
        return ['gestionnaire' => ['gestionnaire'], 'admin' => ['admin']];
    }

    private function autorise(Personne $p, string $codeTache, string $jour): ?bool
    {
        $ligne = Restriction::where('id_personne', $p->id)
            ->where('id_tache', TacheFactory::pourCode($codeTache)->id)->where('jour', $jour)->first();

        return $ligne === null ? null : (bool) $ligne->autorise;
    }

    #[DataProvider('nonPrivilegies')]
    public function test_un_compte_non_privilegie_n_ecrit_que_ses_propres_cases(string $persona): void
    {
        $this->tachesDeRotation();
        $moi = $this->connecterEn($persona);
        $autre = Personne::factory()->create();

        $this->post(route('restrictions.update'), ['checkboxes' => [
            $moi->id => [TacheFactory::pourCode('entree')->id => ['Vendredi' => '1']],
            $autre->id => [TacheFactory::pourCode('entree')->id => ['Vendredi' => '1', 'Samedi' => '1']],
        ]])->assertRedirect(route('restrictions.index'))->assertSessionHas('success');

        $this->assertTrue($this->autorise($moi, 'entree', 'Vendredi'), 'ma case cochée');
        $this->assertFalse($this->autorise($moi, 'entree', 'Samedi'), 'mes cases non cochées sont enregistrées « interdit »');
        $this->assertNull($this->autorise($autre, 'entree', 'Vendredi'), 'rien n\'est écrit pour quelqu\'un d\'autre');
        $this->assertSame(0, Restriction::where('id_personne', $autre->id)->count());
    }

    #[DataProvider('nonPrivilegies')]
    public function test_les_cases_d_autrui_dans_la_requete_sont_ignorees_meme_si_la_personne_a_deja_des_restrictions(string $persona): void
    {
        $this->tachesDeRotation();
        $this->connecterEn($persona);
        $autre = Personne::factory()->create();
        Restriction::factory()->pour($autre, TacheFactory::pourCode('salle'))->le('Samedi')->autorisee()->create();

        $this->post(route('restrictions.update'), ['checkboxes' => [$autre->id => []]]);

        $this->assertTrue($this->autorise($autre, 'salle', 'Samedi'), 'inchangé');
    }

    #[DataProvider('privilegies')]
    public function test_gestionnaire_et_admin_reecrivent_la_grille_de_toutes_les_personnes_actives(string $persona): void
    {
        $this->tachesDeRotation();
        $moi = $this->connecterEn($persona);
        $autre = Personne::factory()->create();
        $enAttente = Personne::factory()->enAttente()->create();
        $entree = TacheFactory::pourCode('entree')->id;

        $this->post(route('restrictions.update'), ['checkboxes' => [
            $autre->id => [$entree => ['Vendredi' => '1']],
            $moi->id => [$entree => ['Samedi' => '1']],
            $enAttente->id => [$entree => ['Vendredi' => '1']],
        ]])->assertSessionHas('success');

        $this->assertTrue($this->autorise($autre, 'entree', 'Vendredi'));
        $this->assertFalse($this->autorise($autre, 'entree', 'Samedi'));
        $this->assertTrue($this->autorise($moi, 'entree', 'Samedi'));
        $this->assertSame(0, Restriction::where('id_personne', $enAttente->id)->count(), 'une candidature en attente n\'est pas dans la grille');
    }

    /**
     * CARACTÉRISATION (risque de perte de données) : la mise à jour d'un gestionnaire/admin
     * traite l'absence d'une case comme « décochée ». Une requête sans `checkboxes` (champ
     * nullable), ou qui omet une personne — par ex. ajoutée après le chargement de la page —
     * passe donc TOUTES ses cases (tâche × jour) à « interdit » : plus personne n'est
     * disponible nulle part. Aucune confirmation, aucune garde.
     */
    public function test_une_requete_vide_d_un_gestionnaire_interdit_toutes_les_taches_a_toutes_les_personnes(): void
    {
        $this->tachesDeRotation();
        $this->connecterEn('gestionnaire');
        $autre = Personne::factory()->create();
        Restriction::factory()->pour($autre, TacheFactory::pourCode('entree'))->le('Vendredi')->autorisee()->create();

        $this->post(route('restrictions.update'))->assertSessionHas('success');

        $this->assertFalse($this->autorise($autre, 'entree', 'Vendredi'), 'la case cochée avant est désormais interdite');
        $this->assertSame(0, Restriction::where('autorise', true)->count());
    }

    public function test_la_mise_a_jour_est_journalisee_avec_l_auteur(): void
    {
        $this->tachesDeRotation();
        $this->connecterEn('admin');

        $this->post(route('restrictions.update'));

        $entree = AuditLog::where('module', 'restrictions')->firstOrFail();
        $this->assertSame('Grille complète mise à jour par admin', $entree->after['message']);
    }

    public function test_la_page_liste_les_personnes_actives_pour_tout_compte_connecte(): void
    {
        $this->tachesDeRotation();
        $this->connecterEn('sans_role');
        $active = Personne::factory()->create();
        $enAttente = Personne::factory()->enAttente()->create();

        $ids = $this->get(route('restrictions.index'))->assertOk()->viewData('personnes')->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($enAttente->id, $ids);
    }
}
