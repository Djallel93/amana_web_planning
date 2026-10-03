<?php
// tests/Feature/Authorization/EchangeAuthorizationTest.php
//
// Qui a le droit de faire quoi sur un échange, au niveau HTTP. La machine à états elle-même est
// dans EchangeServiceTest ; ici : l'identité (demandeur toujours = utilisateur connecté), la
// visibilité, et les actions d'administration (gestionnaire+).

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\Echange;
use App\Models\Personne;
use Database\Factories\TacheFactory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ConnecteParRole;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class EchangeAuthorizationTest extends TestCase
{
    use RefreshesBothDatabases;
    use ConnecteParRole;
    use CreeDonneesPlanning;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Bus::fake();
        $this->travelTo('2026-09-14 10:00:00');
    }

    /** @return array<string, array{string}> */
    public static function comptesConnectes(): array
    {
        return ['sans rôle' => ['sans_role'], 'bénévole' => ['benevole'], 'membre' => ['membre'], 'gestionnaire' => ['gestionnaire'], 'admin' => ['admin']];
    }

    /** Le persona tient l'entrée du 02/10 ; « autre » celle du 09/10. @return array{Personne, Personne} */
    private function moiEtAutre(string $persona): array
    {
        $moi = $this->connecterEn($persona);
        $autre = Personne::factory()->create();
        $this->assigner($moi, '2026-10-02', 'entree');
        $this->assigner($autre, '2026-10-09', 'entree');

        return [$moi, $autre];
    }

    private function corps(Personne $cible, ?int $creneauDemandeur = null): array
    {
        $entree = TacheFactory::pourCode('entree');

        return [
            'creneau_demandeur_id' => $creneauDemandeur ?? $this->creneauLe('2026-10-02')->id,
            'tache_demandeur_id' => $entree->id,
            'creneau_cible_id' => $this->creneauLe('2026-10-09')->id,
            'tache_cible_id' => $entree->id,
            'personne_cible_id' => $cible->id,
        ];
    }

    // ── store : le demandeur est toujours l'utilisateur connecté ──────────

    #[DataProvider('comptesConnectes')]
    public function test_tout_compte_connecte_peut_demander_un_echange_pour_son_propre_creneau(string $persona): void
    {
        [$moi, $autre] = $this->moiEtAutre($persona);

        $this->postJson(route('echanges.store'), $this->corps($autre))
            ->assertOk()
            ->assertJson(['success' => true]);

        $echange = Echange::firstOrFail();
        $this->assertSame($moi->id, $echange->id_personne_demandeur);
        $this->assertSame($autre->id, $echange->id_personne_cible);
    }

    /**
     * CARACTÉRISATION du message : ModelNotFoundException EST une RuntimeException, donc le
     * `catch (\RuntimeException)` du contrôleur l'attrape et renvoie à l'utilisateur le texte
     * brut d'Eloquent, nom de classe compris (« No query results for model [App\Models\…] »).
     * Le refus est correct (422, rien créé) ; le message, lui, fuit un détail d'implémentation
     * et n'est pas en français comme les autres.
     */
    public function test_on_ne_peut_pas_proposer_le_creneau_de_quelqu_un_d_autre(): void
    {
        [$moi, $autre] = $this->moiEtAutre('membre');
        $creneauDeAutre = $this->creneauLe('2026-10-09')->id;

        // « Je » propose le créneau de « autre » comme s'il était à moi.
        $this->postJson(route('echanges.store'), $this->corps($autre, $creneauDeAutre))
            ->assertUnprocessable()
            ->assertJson(['success' => false])
            ->assertJsonPath('message', fn(string $m) => str_contains($m, 'No query results for model'));

        $this->assertSame(0, Echange::count());
        Notification::assertNothingSent();
    }

    public function test_un_gestionnaire_ne_peut_pas_creer_un_echange_au_nom_d_un_autre(): void
    {
        $gestionnaire = $this->connecterEn('gestionnaire');
        [$a, $b] = [Personne::factory()->create(), Personne::factory()->create()];
        $this->assigner($a, '2026-10-02', 'entree');
        $this->assigner($b, '2026-10-09', 'entree');

        // Tentative d'usurpation : champs « demandeur » supplémentaires, que le contrôleur ignore.
        $corps = $this->corps($b) + ['personne_demandeur_id' => $a->id, 'id_personne_demandeur' => $a->id, 'demandeur_id' => $a->id];
        $this->postJson(route('echanges.store'), $corps)->assertUnprocessable();

        $this->assertSame(0, Echange::count(), 'le créneau est à A, pas au gestionnaire : refusé');
        $this->assertNotSame($a->id, $gestionnaire->id);
    }

    public function test_store_valide_les_champs_et_reste_en_json_422(): void
    {
        $this->connecterEn('membre');

        $this->postJson(route('echanges.store'), [])->assertUnprocessable()->assertJsonValidationErrors([
            'creneau_demandeur_id', 'tache_demandeur_id', 'creneau_cible_id', 'tache_cible_id', 'personne_cible_id',
        ]);
    }

    public function test_une_regle_metier_refusee_donne_un_422_json_avec_le_message(): void
    {
        [$moi, $autre] = $this->moiEtAutre('membre');
        $this->assigner($moi, '2026-09-11', 'entree'); // créneau passé
        $entree = TacheFactory::pourCode('entree');

        $this->postJson(route('echanges.store'), [
            'creneau_demandeur_id' => $this->creneauLe('2026-09-11')->id,
            'tache_demandeur_id' => $entree->id,
            'creneau_cible_id' => $this->creneauLe('2026-10-09')->id,
            'tache_cible_id' => $entree->id,
            'personne_cible_id' => $autre->id,
        ])->assertUnprocessable()->assertJson(['success' => false]);
    }

    // ── destroy : seul le demandeur annule ────────────────────────────────

    private function echangeDe(Personne $demandeur, Personne $cible): Echange
    {
        $this->assigner($demandeur, '2026-10-02', 'entree');
        $this->assigner($cible, '2026-10-09', 'entree');

        return Echange::factory()->create([
            'id_personne_demandeur' => $demandeur->id, 'id_creneau_demandeur' => $this->creneauLe('2026-10-02')->id,
            'id_tache_demandeur' => TacheFactory::pourCode('entree')->id,
            'id_personne_cible' => $cible->id, 'id_creneau_cible' => $this->creneauLe('2026-10-09')->id,
            'id_tache_cible' => TacheFactory::pourCode('entree')->id,
        ]);
    }

    #[DataProvider('comptesConnectes')]
    public function test_le_demandeur_peut_annuler_sa_propre_demande(string $persona): void
    {
        $moi = $this->connecterEn($persona);
        $echange = $this->echangeDe($moi, Personne::factory()->create());

        $this->deleteJson(route('echanges.destroy', $echange->id))->assertOk()->assertJson(['success' => true]);

        $this->assertSame(Echange::STATUT_ANNULE, $echange->fresh()->statut);
    }

    #[DataProvider('comptesConnectes')]
    public function test_personne_d_autre_ne_peut_annuler_une_demande_meme_pas_un_admin(string $persona): void
    {
        $this->connecterEn($persona);
        $echange = $this->echangeDe(Personne::factory()->create(), Personne::factory()->create());

        $this->deleteJson(route('echanges.destroy', $echange->id))
            ->assertUnprocessable()
            ->assertJson(['success' => false]);

        $this->assertSame(Echange::STATUT_EN_ATTENTE, $echange->fresh()->statut);
    }

    public function test_la_cible_ne_peut_pas_annuler_la_demande_dirigee_contre_elle(): void
    {
        $cible = $this->connecterEn('membre');
        $echange = $this->echangeDe(Personne::factory()->create(), $cible);

        $this->deleteJson(route('echanges.destroy', $echange->id))->assertUnprocessable();

        $this->assertSame(Echange::STATUT_EN_ATTENTE, $echange->fresh()->statut);
    }

    public function test_l_annulation_sans_json_redirige_avec_le_message_d_erreur(): void
    {
        $this->connecterEn('membre');
        $echange = $this->echangeDe(Personne::factory()->create(), Personne::factory()->create());

        $this->delete(route('echanges.destroy', $echange->id))
            ->assertRedirect(route('echanges.index'))
            ->assertSessionHas('error');
    }

    // ── index : on ne voit que ses échanges ───────────────────────────────

    public function test_la_liste_ne_contient_que_les_echanges_ou_l_on_est_demandeur_ou_cible(): void
    {
        $moi = $this->connecterEn('membre');
        [$x, $y, $z] = [Personne::factory()->create(), Personne::factory()->create(), Personne::factory()->create()];
        $commeDemandeur = Echange::factory()->create(['id_personne_demandeur' => $moi->id, 'id_personne_cible' => $x->id]);
        $commeCible = Echange::factory()->create(['id_personne_demandeur' => $y->id, 'id_personne_cible' => $moi->id]);
        $etranger = Echange::factory()->create(['id_personne_demandeur' => $y->id, 'id_personne_cible' => $z->id]);

        $ids = $this->get(route('echanges.index'))->assertOk()->viewData('echanges')->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$commeDemandeur->id, $commeCible->id], $ids);
        $this->assertNotContains($etranger->id, $ids);
    }

    // ── administration : gestionnaire et plus ─────────────────────────────

    /** @return array<string, array{string}> */
    public static function gestionnaires(): array
    {
        return ['gestionnaire' => ['gestionnaire'], 'admin' => ['admin']];
    }

    #[DataProvider('gestionnaires')]
    public function test_gestionnaire_et_admin_voient_tous_les_echanges(string $persona): void
    {
        $this->connecterEn($persona);
        $etranger = Echange::factory()->create();

        $ids = $this->get(route('admin.echanges.index'))->assertOk()->viewData('echanges')->pluck('id')->all();

        $this->assertContains($etranger->id, $ids);
    }

    #[DataProvider('gestionnaires')]
    public function test_gestionnaire_et_admin_approuvent_et_leur_id_est_enregistre(string $persona): void
    {
        $chef = $this->connecterEn($persona);
        $echange = $this->echangeDe(Personne::factory()->create(), Personne::factory()->create());

        $this->post(route('admin.echanges.approuver', $echange->id))
            ->assertRedirect(route('admin.echanges.index'))
            ->assertSessionHas('success');

        $echange->refresh();
        $this->assertSame(Echange::STATUT_ACCEPTE, $echange->statut);
        $this->assertSame($chef->id, (int) $echange->approuve_par);
    }

    #[DataProvider('gestionnaires')]
    public function test_gestionnaire_et_admin_refusent_et_leur_id_est_enregistre(string $persona): void
    {
        $chef = $this->connecterEn($persona);
        $echange = $this->echangeDe(Personne::factory()->create(), Personne::factory()->create());

        $this->post(route('admin.echanges.refuser', $echange->id))->assertSessionHas('success');

        $echange->refresh();
        $this->assertSame(Echange::STATUT_REFUSE, $echange->statut);
        $this->assertSame($chef->id, (int) $echange->approuve_par);
    }

    public function test_approuver_un_echange_deja_traite_affiche_l_erreur_sans_rien_changer(): void
    {
        $this->connecterEn('gestionnaire');
        $echange = Echange::factory()->refuse()->create();

        $this->post(route('admin.echanges.approuver', $echange->id))
            ->assertRedirect(route('admin.echanges.index'))
            ->assertSessionHas('error');

        $this->assertSame(Echange::STATUT_REFUSE, $echange->fresh()->statut);
    }

    public function test_un_membre_n_approuve_pas_meme_l_echange_qui_le_concerne(): void
    {
        $moi = $this->connecterEn('membre');
        $echange = $this->echangeDe(Personne::factory()->create(), $moi);

        $this->post(route('admin.echanges.approuver', $echange->id))->assertRedirect(route('planning.index'));

        $this->assertSame(Echange::STATUT_EN_ATTENTE, $echange->fresh()->statut);
    }

    // ── Liens à jeton (publics) ───────────────────────────────────────────

    public function test_un_jeton_qui_n_a_pas_64_caracteres_alphanumeriques_est_rejete_par_la_route(): void
    {
        foreach ([str_repeat('a', 63), str_repeat('a', 65), str_repeat('a', 63) . '-'] as $jeton) {
            $this->get("/echanges/{$jeton}/accepter")->assertNotFound();
            $this->get("/echanges/{$jeton}/refuser")->assertNotFound();
        }
    }

    public function test_un_jeton_inconnu_affiche_la_page_lien_invalide_sans_connexion(): void
    {
        $this->get(route('echanges.accepter', str_repeat('a', 64)))
            ->assertOk()
            ->assertViewIs('echanges.token-result')
            ->assertViewHas('action', 'invalide');
    }
}
