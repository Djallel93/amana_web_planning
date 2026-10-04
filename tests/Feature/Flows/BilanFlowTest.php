<?php
// tests/Feature/Flows/BilanFlowTest.php
//
// Bilan quotidien : deux groupes indépendants de valeurs par date — « Amana food » (carte, espèces,
// charges) et « Présences » (présents, en ligne) — enregistrés, réinitialisés (« pas de cours ») et
// agrégés en statistiques. Droits : membre et plus saisissent ; gestionnaire et admin réinitialisent.
//
// Repère : « aujourd'hui » = mercredi 2026-09-30 ; les bilans portent sur des vendredis.

declare(strict_types=1);

namespace Tests\Feature\Flows;

use Amana\Shared\Models\AuditLog;
use App\Models\Bilan;
use App\Models\Personne;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ConnecteParRole;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class BilanFlowTest extends TestCase
{
    use ConnecteParRole;
    use CreeDonneesPlanning;
    use RefreshesBothDatabases;

    private const DATE = '2026-09-25';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-30 09:00:00');
        $this->creerRolesPlanning();
    }

    private function food(array $surcharge = []): array
    {
        return array_replace(['date' => self::DATE, 'montant_carte' => '120.50', 'montant_espece' => '80', 'montant_charges' => '15.25'], $surcharge);
    }

    private function presence(array $surcharge = []): array
    {
        return array_replace(['date' => self::DATE, 'nb_presents' => 25, 'nb_en_ligne' => 10], $surcharge);
    }

    /** @return array<string, array{string}> */
    public static function saisissants(): array
    {
        return ['membre' => ['membre'], 'gestionnaire' => ['gestionnaire'], 'admin' => ['admin']];
    }

    // ── Lecture ───────────────────────────────────────────────────────────

    public function test_une_date_sans_bilan_renvoie_des_valeurs_vides_et_existe_faux(): void
    {
        $this->connecterEn('membre');

        $this->getJson(route('bilan.data.show', ['date' => self::DATE]))->assertOk()->assertExactJson([
            'date' => self::DATE, 'montantCarte' => null, 'montantEspece' => null, 'montantCharges' => null,
            'nbPresents' => null, 'nbEnLigne' => null, 'existe' => false,
            'derniereMajFood' => null, 'derniereMajFoodPar' => null, 'derniereMajPresence' => null, 'derniereMajPresencePar' => null,
            'peutReinitialiser' => false,
        ]);
    }

    public function test_la_lecture_valide_la_date(): void
    {
        $this->connecterEn('membre');

        $this->getJson(route('bilan.data.show'))->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->getJson(route('bilan.data.show', ['date' => 'demain-matin']))->assertUnprocessable();
    }

    #[DataProvider('saisissants')]
    public function test_le_drapeau_peut_reinitialiser_depend_du_role(string $persona): void
    {
        $this->connecterEn($persona);

        $attendu = in_array($persona, ['gestionnaire', 'admin'], true);
        $this->getJson(route('bilan.data.show', ['date' => self::DATE]))->assertJsonPath('peutReinitialiser', $attendu);
    }

    // ── Enregistrement : Amana food ───────────────────────────────────────

    #[DataProvider('saisissants')]
    public function test_enregistrer_amana_food_cree_le_bilan_et_signe_la_mise_a_jour(string $persona): void
    {
        $moi = $this->connecterEn($persona);

        $this->postJson(route('bilan.data.store.amana-food'), $this->food())
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Bilan Amana food enregistré.'])
            ->assertJsonPath('bilan.montantCarte', 120.5)
            ->assertJsonPath('bilan.montantEspece', 80)
            ->assertJsonPath('bilan.montantCharges', 15.25)
            ->assertJsonPath('bilan.existe', true)
            ->assertJsonPath('bilan.derniereMajFood', '30 sept. 2026 à 09:00')
            ->assertJsonPath('bilan.derniereMajFoodPar', $moi->prenom . ' ' . $moi->nom);

        $bilan = Bilan::firstOrFail();
        $this->assertSame(self::DATE, $bilan->date->toDateString());
        $this->assertSame($moi->id, (int) $bilan->id_personne_maj_food);
        $this->assertNull($bilan->id_personne_maj_presence, 'les présences ne sont pas signées');
        $this->assertNull($bilan->nb_presents);
    }

    public function test_enregistrer_a_nouveau_la_meme_date_met_a_jour_sans_creer_de_doublon(): void
    {
        $premier = $this->connecterEn('membre');
        $this->postJson(route('bilan.data.store.amana-food'), $this->food());
        $second = $this->connecterEn('gestionnaire');

        $this->postJson(route('bilan.data.store.amana-food'), $this->food(['montant_carte' => '200', 'montant_espece' => '0', 'montant_charges' => '0']))->assertOk();

        $this->assertSame(1, Bilan::count());
        $bilan = Bilan::firstOrFail();
        $this->assertEquals(200, $bilan->montant_carte);
        $this->assertSame($second->id, (int) $bilan->id_personne_maj_food, 'la dernière personne à modifier signe');
        $this->assertNotSame($premier->id, $second->id);
    }

    public function test_les_deux_groupes_sont_independants_et_chacun_a_son_auteur(): void
    {
        $a = $this->connecterEn('membre');
        $this->postJson(route('bilan.data.store.amana-food'), $this->food());
        $b = $this->connecterEn('membre');

        $this->postJson(route('bilan.data.store.presence'), $this->presence())->assertOk()->assertJson(['message' => 'Bilan Présences enregistré.']);

        $bilan = Bilan::firstOrFail();
        $this->assertEquals(120.5, $bilan->montant_carte, 'les montants n\'ont pas bougé');
        $this->assertSame(25, $bilan->nb_presents);
        $this->assertSame(10, $bilan->nb_en_ligne);
        $this->assertSame($a->id, (int) $bilan->id_personne_maj_food);
        $this->assertSame($b->id, (int) $bilan->id_personne_maj_presence);
    }

    public function test_zero_est_une_valeur_valide_distincte_de_vide(): void
    {
        $this->connecterEn('membre');

        $this->postJson(route('bilan.data.store.amana-food'), $this->food(['montant_carte' => '0', 'montant_espece' => '0', 'montant_charges' => '0']))->assertOk();
        $this->postJson(route('bilan.data.store.presence'), $this->presence(['nb_presents' => 0, 'nb_en_ligne' => 0]))->assertOk();

        $bilan = Bilan::firstOrFail();
        $this->assertSame('0.00', (string) $bilan->getRawOriginal('montant_carte'));
        $this->assertSame(0, $bilan->nb_presents);
    }

    public function test_tout_vide_marque_pas_de_cours(): void
    {
        $this->connecterEn('membre');

        $this->postJson(route('bilan.data.store.amana-food'), ['date' => self::DATE])->assertOk()->assertJsonPath('bilan.montantCarte', null);
        $this->postJson(route('bilan.data.store.presence'), ['date' => self::DATE])->assertOk()->assertJsonPath('bilan.nbPresents', null);

        $bilan = Bilan::firstOrFail();
        $this->assertNull($bilan->montant_carte);
        $this->assertNull($bilan->nb_presents);
        $this->assertTrue($bilan->exists, 'la ligne existe : « pas de cours » est distinct de « jamais saisi »');
    }

    /** @return array<string, array{array, string}> */
    public static function foodInvalides(): array
    {
        $tous = ['montant_carte' => '10', 'montant_espece' => '10', 'montant_charges' => '10'];

        return [
            'un seul montant' => [['montant_carte' => '10', 'montant_espece' => null, 'montant_charges' => null], 'montant_carte'],
            'deux montants sur trois' => [['montant_carte' => '10', 'montant_espece' => '5', 'montant_charges' => null], 'montant_carte'],
            'carte négative' => [['montant_carte' => '-1'] + $tous, 'montant_carte'],
            'espèces négatives' => [['montant_espece' => '-0.01'] + $tous, 'montant_espece'],
            'charges négatives' => [['montant_charges' => '-5'] + $tous, 'montant_charges'],
            'montant non numérique' => [['montant_carte' => 'beaucoup'] + $tous, 'montant_carte'],
            'montant trop grand' => [['montant_espece' => '1000000'] + $tous, 'montant_espece'],
            'date manquante' => [['date' => ''] + $tous, 'date'],
            'date invalide' => [['date' => 'pas-une-date'] + $tous, 'date'],
        ];
    }

    #[DataProvider('foodInvalides')]
    public function test_amana_food_invalide_n_enregistre_rien(array $surcharge, string $champ): void
    {
        $this->connecterEn('membre');

        $this->postJson(route('bilan.data.store.amana-food'), array_replace($this->food(), $surcharge))->assertUnprocessable()->assertJsonValidationErrors($champ);

        $this->assertSame(0, Bilan::count());
    }

    public function test_les_montants_aux_bornes_sont_acceptes(): void
    {
        $this->connecterEn('membre');

        $this->postJson(route('bilan.data.store.amana-food'), $this->food(['montant_carte' => '999999.99', 'montant_espece' => '0.01', 'montant_charges' => '0']))->assertOk();
    }

    public function test_le_message_de_la_regle_tout_ou_rien_est_explicite(): void
    {
        $this->connecterEn('membre');

        $this->postJson(route('bilan.data.store.amana-food'), $this->food(['montant_espece' => null, 'montant_charges' => null]))
            ->assertJsonValidationErrors(['montant_carte' => 'Renseignez les trois montants, ou laissez-les tous vides pour marquer "pas de cours".']);
    }

    // ── Enregistrement : Présences ────────────────────────────────────────

    /** @return array<string, array{array, string}> */
    public static function presencesInvalides(): array
    {
        return [
            'un seul champ' => [['nb_presents' => 5, 'nb_en_ligne' => null], 'nb_presents'],
            'présents négatifs' => [['nb_presents' => -1, 'nb_en_ligne' => 0], 'nb_presents'],
            'en ligne négatifs' => [['nb_presents' => 0, 'nb_en_ligne' => -1], 'nb_en_ligne'],
            'non entier' => [['nb_presents' => 2.5, 'nb_en_ligne' => 1], 'nb_presents'],
            'dépasse 65535' => [['nb_presents' => 65536, 'nb_en_ligne' => 0], 'nb_presents'],
        ];
    }

    #[DataProvider('presencesInvalides')]
    public function test_presences_invalides_n_enregistrent_rien(array $surcharge, string $champ): void
    {
        $this->connecterEn('membre');

        $this->postJson(route('bilan.data.store.presence'), array_replace($this->presence(), $surcharge))->assertUnprocessable()->assertJsonValidationErrors($champ);

        $this->assertSame(0, Bilan::count());
    }

    public function test_la_borne_haute_des_presences_est_acceptee(): void
    {
        $this->connecterEn('membre');

        $this->postJson(route('bilan.data.store.presence'), $this->presence(['nb_presents' => 65535, 'nb_en_ligne' => 65535]))->assertOk();
    }

    // ── Droits (en plus de RouteAccessMatrixTest) : rien n'est écrit si refusé ──

    public function test_un_benevole_ou_un_compte_sans_role_ne_peut_rien_enregistrer(): void
    {
        foreach (['benevole', 'sans_role'] as $persona) {
            $this->connecterEn($persona);

            $this->postJson(route('bilan.data.store.amana-food'), $this->food())->assertStatus(302);
            $this->postJson(route('bilan.data.store.presence'), $this->presence())->assertStatus(302);
            $this->post(route('logout'));
        }

        $this->assertSame(0, Bilan::count());
    }

    // ── Réinitialisation ──────────────────────────────────────────────────

    #[DataProvider('saisissants')]
    public function test_seuls_gestionnaire_et_admin_peuvent_reinitialiser(string $persona): void
    {
        $this->connecterEn('gestionnaire');
        $this->postJson(route('bilan.data.store.amana-food'), $this->food());
        $this->post(route('logout'));
        $this->connecterEn($persona);

        $reponse = $this->postJson(route('bilan.data.reset.amana-food'), ['date' => self::DATE]);

        if (in_array($persona, ['gestionnaire', 'admin'], true)) {
            $reponse->assertOk()->assertJson(['success' => true, 'message' => 'Bilan Amana food réinitialisé pour cette date (pas de cours).']);
            $this->assertNull(Bilan::firstOrFail()->montant_carte);
        } else {
            $reponse->assertStatus(302);
            $this->assertEquals(120.5, Bilan::firstOrFail()->montant_carte, 'un membre ne peut pas effacer');
        }
    }

    public function test_reinitialiser_un_groupe_ne_touche_pas_a_l_autre(): void
    {
        $this->connecterEn('gestionnaire');
        $this->postJson(route('bilan.data.store.amana-food'), $this->food());
        $this->postJson(route('bilan.data.store.presence'), $this->presence());

        $this->postJson(route('bilan.data.reset.amana-food'), ['date' => self::DATE])->assertOk();
        $bilan = Bilan::firstOrFail();
        $this->assertNull($bilan->montant_carte);
        $this->assertNull($bilan->montant_espece);
        $this->assertNull($bilan->montant_charges);
        $this->assertSame(25, $bilan->nb_presents, 'les présences sont intactes');

        $this->postJson(route('bilan.data.store.amana-food'), $this->food());
        $this->postJson(route('bilan.data.reset.presence'), ['date' => self::DATE])->assertOk()->assertJson(['message' => 'Bilan Présences réinitialisé pour cette date (pas de cours).']);
        $bilan = Bilan::firstOrFail();
        $this->assertNull($bilan->nb_presents);
        $this->assertNull($bilan->nb_en_ligne);
        $this->assertEquals(120.5, $bilan->montant_carte, 'les montants sont intacts');
    }

    public function test_reinitialiser_une_date_sans_bilan_cree_la_ligne_pas_de_cours(): void
    {
        $chef = $this->connecterEn('gestionnaire');

        $this->postJson(route('bilan.data.reset.presence'), ['date' => self::DATE])->assertOk()->assertJsonPath('bilan.existe', true);

        $bilan = Bilan::firstOrFail();
        $this->assertSame($chef->id, (int) $bilan->id_personne_maj_presence);
        $this->assertNotNull($bilan->maj_presence_at);
    }

    public function test_reinitialiser_valide_la_date(): void
    {
        $this->connecterEn('gestionnaire');

        $this->postJson(route('bilan.data.reset.amana-food'), [])->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->postJson(route('bilan.data.reset.presence'), ['date' => 'xx'])->assertUnprocessable();
    }

    // ── Journalisation ────────────────────────────────────────────────────

    public function test_creation_modification_et_reinitialisation_sont_journalisees_avec_l_etat_precedent(): void
    {
        $this->connecterEn('gestionnaire');

        $this->postJson(route('bilan.data.store.amana-food'), $this->food());
        $this->postJson(route('bilan.data.store.amana-food'), $this->food(['montant_carte' => '300']));
        $this->postJson(route('bilan.data.reset.amana-food'), ['date' => self::DATE]);

        $actions = AuditLog::where('module', 'bilan')->orderBy('id')->pluck('action')->all();
        $this->assertSame(['create', 'update', 'reset'], $actions);
        $maj = AuditLog::where('module', 'bilan')->where('action', 'update')->firstOrFail();
        $this->assertEquals(120.5, $maj->before['montant_carte']);
        $this->assertEquals(300, $maj->after['montant_carte']);
        $reset = AuditLog::where('module', 'bilan')->where('action', 'reset')->firstOrFail();
        $this->assertEquals(300, $reset->before['montant_carte']);
        $this->assertSame(['montant_carte' => null, 'montant_espece' => null, 'montant_charges' => null], $reset->after);
    }

    /**
     * CARACTÉRISATION : aucune limite de date — n'importe quel membre peut créer, modifier (et un
     * gestionnaire effacer) le bilan d'une date passée de plusieurs années ou lointaine dans le
     * futur. Il n'y a pas de verrouillage des périodes clôturées.
     */
    public function test_aucune_limite_de_date_pour_saisir_un_bilan(): void
    {
        $this->connecterEn('membre');

        $this->postJson(route('bilan.data.store.amana-food'), $this->food(['date' => '2001-01-05']))->assertOk();
        $this->postJson(route('bilan.data.store.presence'), $this->presence(['date' => '2099-12-31']))->assertOk();

        $this->assertSame(2, Bilan::count());
    }

    // ── Statistiques ──────────────────────────────────────────────────────

    private function bilan(string $date, array $attributs): Bilan
    {
        return Bilan::factory()->pourDate($date)->create($attributs);
    }

    public function test_les_statistiques_valident_la_periode(): void
    {
        $this->connecterEn('membre');

        $this->getJson(route('bilan.statistiques.data'))->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
        $this->getJson(route('bilan.statistiques.data', ['from' => '2026-09-25', 'to' => '2026-09-01']))->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_les_statistiques_d_une_periode_sans_bilan_sont_vides(): void
    {
        $this->connecterEn('membre');

        $this->getJson(route('bilan.statistiques.data', ['from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk()->assertJson([
            'serie' => [],
            'cartes' => [
                'totalMontant' => 0, 'revenuNetTotal' => 0, 'moyennePresence' => 0, 'meilleureDate' => null,
                'meilleureCollecte' => null, 'tauxRemplissage' => null, 'nbBilans' => 0, 'nbCreneaux' => 0,
            ],
        ]);
    }

    public function test_les_statistiques_agregent_montants_presences_et_taux_de_remplissage(): void
    {
        $this->connecterEn('membre');
        $this->creneauLe('2026-09-04');
        $this->creneauLe('2026-09-11');
        $this->creneauLe('2026-09-18');
        $this->creneauLe('2026-09-25');
        // 04/09 : complet ; 11/09 : complet ; 18/09 : présences seulement ; 25/09 : « pas de cours » (tout NULL) ; 02/10 : hors période.
        $this->bilan('2026-09-04', ['montant_carte' => '100.00', 'montant_espece' => '50.00', 'montant_charges' => '20.00', 'nb_presents' => 20, 'nb_en_ligne' => 5]);
        $this->bilan('2026-09-11', ['montant_carte' => '200.00', 'montant_espece' => '0.00', 'montant_charges' => '30.00', 'nb_presents' => 30, 'nb_en_ligne' => 15]);
        $this->bilan('2026-09-18', ['montant_carte' => null, 'montant_espece' => null, 'montant_charges' => null, 'nb_presents' => 10, 'nb_en_ligne' => 0]);
        $this->bilan('2026-09-25', ['montant_carte' => null, 'montant_espece' => null, 'montant_charges' => null, 'nb_presents' => null, 'nb_en_ligne' => null]);
        $this->bilan('2026-10-02', ['montant_carte' => '999.00', 'montant_espece' => '0.00', 'montant_charges' => '0.00', 'nb_presents' => 99, 'nb_en_ligne' => 0]);

        $reponse = $this->getJson(route('bilan.statistiques.data', ['from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk();

        $this->assertSame(['2026-09-04', '2026-09-11', '2026-09-18', '2026-09-25'], array_column($reponse->json('serie'), 'date'), 'trié par date, hors période exclu');
        $cartes = $reponse->json('cartes');
        $this->assertEquals(350, $cartes['totalMontant'], '(100+50) + (200+0)');
        $this->assertEquals(300, $cartes['revenuNetTotal'], '(150−20) + (200−30)');
        $this->assertEquals(26.7, $cartes['moyennePresence'], '(25 + 45 + 10) / 3 jours avec présences = 26,67 arrondi à 1 décimale');
        $this->assertSame(['date' => '2026-09-11', 'valeur' => 45], $cartes['meilleureDate']);
        $this->assertEquals(['date' => '2026-09-11', 'valeur' => 170], $cartes['meilleureCollecte']);
        $this->assertSame(3, $cartes['nbBilans'], 'le jour « pas de cours » n\'est pas compté comme rempli');
        $this->assertSame(4, $cartes['nbCreneaux']);
        $this->assertEquals(75, $cartes['tauxRemplissage'], '3 bilans remplis / 4 créneaux');
    }

    public function test_la_serie_calcule_totaux_net_et_responsables_par_date(): void
    {
        $this->connecterEn('membre');
        $this->tachesDeRotation();
        $responsable = Personne::factory()->create(['prenom' => 'Karim', 'nom' => 'Kaddour']);
        $this->assigner($responsable, '2026-09-04', 'amana_food');
        $this->bilan('2026-09-04', ['montant_carte' => '100.00', 'montant_espece' => '50.00', 'montant_charges' => '20.00', 'nb_presents' => 20, 'nb_en_ligne' => 5]);

        $ligne = $this->getJson(route('bilan.statistiques.data', ['from' => '2026-09-01', 'to' => '2026-09-30']))->json('serie.0');

        $this->assertEquals(25, $ligne['totalPresence']);
        $this->assertEquals(150, $ligne['totalMontant']);
        $this->assertEquals(130, $ligne['revenuNet']);
        $this->assertSame('Karim ' . $responsable->nom, $ligne['responsableAmanaFood']);
        $this->assertNull($ligne['responsableMektaba']);
    }

    public function test_les_pages_du_bilan_s_affichent_pour_un_membre(): void
    {
        $this->connecterEn('membre');

        $this->get(route('bilan.index'))->assertOk();
        $this->get(route('bilan.statistiques'))->assertOk();
    }
}
