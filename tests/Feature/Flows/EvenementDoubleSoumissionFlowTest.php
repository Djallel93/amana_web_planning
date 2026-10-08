<?php
// tests/Feature/Flows/EvenementDoubleSoumissionFlowTest.php
//
// Même garantie que pour les absences (voir AbsenceDoubleSoumissionFlowTest) : un événement — créé
// par le formulaire ou importé en masse (CSV ou saisie manuelle) — soumis deux fois de suite ne
// crée qu'UNE ligne. Défenses côté serveur : validation (nom + dates identiques refusés), re-contrôle
// sous verrou pour une requête jumelle, et verrou contre les requêtes simultanées.
//
// Repères : « aujourd'hui » = mercredi 2026-09-30 ; aucun planning généré (pas de régénération).

declare(strict_types=1);

namespace Tests\Feature\Flows;

use Amana\Shared\Models\AuditLog;
use App\Models\Evenement;
use App\Services\VerrouAction;
use Closure;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\PrepareImportEvenements;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class EvenementDoubleSoumissionFlowTest extends TestCase
{
    use PrepareImportEvenements;
    use RefreshesBothDatabases;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerImport();
    }

    private function donnees(array $surcharge = []): array
    {
        return array_replace(['nom' => 'Ramadan', 'date_debut' => '2026-12-01', 'date_fin' => '2026-12-02'], $surcharge);
    }

    private function cleDeVerrou(string $nom, string $debut, string $fin): string
    {
        return 'verrou:evenement:' . md5(mb_strtolower(trim($nom)) . '|' . $debut . '|' . $fin);
    }

    /** Une requête jumelle écrit entre la validation de la nôtre et l'acquisition du verrou. */
    private function simulerRequeteJumelle(Closure $avant): void
    {
        $this->app->instance(VerrouAction::class, new class($avant) extends VerrouAction
        {
            private bool $fait = false;

            public function __construct(private readonly Closure $avant) {}

            public function executer(string $cle, callable $action, int $attenteSecondes = 0, ?string $messageSiOccupe = null): mixed
            {
                if (!$this->fait) {
                    $this->fait = true;
                    ($this->avant)();
                }

                return parent::executer($cle, $action, $attenteSecondes, $messageSiOccupe);
            }
        });
    }

    // ══ Création par le formulaire ════════════════════════════════════════

    public function test_une_double_soumission_ne_cree_qu_un_evenement(): void
    {
        $this->post(route('evenements.store'), $this->donnees())->assertRedirect(route('evenements.index'))->assertSessionHas('success');

        $this->post(route('evenements.store'), $this->donnees())
            ->assertSessionHasErrors(['nom' => Evenement::MESSAGE_DOUBLON]);

        $this->assertSame(1, Evenement::count());
        $this->assertSame(1, AuditLog::where('module', 'evenements')->where('action', 'create')->count());
    }

    public function test_un_autre_nom_ou_d_autres_dates_restent_possibles(): void
    {
        $this->post(route('evenements.store'), $this->donnees());
        $this->post(route('evenements.store'), $this->donnees(['nom' => 'Aïd']));
        $this->post(route('evenements.store'), $this->donnees(['date_fin' => '2026-12-03']));
        $this->post(route('evenements.store'), $this->donnees(['date_debut' => '2026-12-02']));

        $this->assertSame(4, Evenement::count(), 'seul un événement STRICTEMENT identique (nom + début + fin) est refusé');
    }

    public function test_une_requete_jumelle_ecrite_apres_la_validation_est_detectee_sous_verrou(): void
    {
        $this->simulerRequeteJumelle(fn() => Evenement::factory()->du('2026-12-01', '2026-12-02')->create(['nom' => 'Ramadan']));

        $this->post(route('evenements.store'), $this->donnees())
            ->assertSessionHas('warning', fn(string $m) => str_starts_with($m, Evenement::MESSAGE_DOUBLON))
            ->assertSessionMissing('success');

        $this->assertSame(1, Evenement::count(), 'seule la requête jumelle a écrit');
    }

    public function test_une_requete_simultanee_pour_le_meme_evenement_est_refusee(): void
    {
        Cache::lock($this->cleDeVerrou('Ramadan', '2026-12-01', '2026-12-02'), 60)->get();

        $this->post(route('evenements.store'), $this->donnees())
            ->assertRedirect(route('evenements.index'))
            ->assertSessionHas('warning', fn(string $m) => str_contains($m, 'déjà en cours de création'));

        $this->assertSame(0, Evenement::count());
    }

    public function test_le_verrou_d_un_evenement_ne_bloque_pas_un_autre(): void
    {
        Cache::lock($this->cleDeVerrou('Ramadan', '2026-12-01', '2026-12-02'), 60)->get();

        $this->post(route('evenements.store'), $this->donnees(['nom' => 'Aïd']))->assertSessionHas('success');

        $this->assertSame(1, Evenement::count());
    }

    public function test_le_verrou_est_relache_apres_la_creation(): void
    {
        $this->post(route('evenements.store'), $this->donnees())->assertSessionHas('success');

        $this->assertTrue(Cache::lock($this->cleDeVerrou('Ramadan', '2026-12-01', '2026-12-02'), 5)->get());
    }

    // ══ Modification ══════════════════════════════════════════════════════

    public function test_une_modification_ne_peut_pas_dupliquer_un_autre_evenement(): void
    {
        Evenement::factory()->du('2026-12-01', '2026-12-02')->create(['nom' => 'Ramadan']);
        $autre = Evenement::factory()->du('2026-12-10', '2026-12-11')->create(['nom' => 'Aïd']);

        $this->put(route('evenements.update', $autre->id), $this->donnees())
            ->assertSessionHasErrors(['nom' => Evenement::MESSAGE_DOUBLON]);

        $this->assertSame('Aïd', $autre->fresh()->nom);
    }

    public function test_enregistrer_un_evenement_sans_changer_son_nom_ni_ses_dates_reste_permis(): void
    {
        $evenement = Evenement::factory()->du('2026-12-01', '2026-12-02')->create(['nom' => 'Ramadan', 'description' => null]);

        $this->put(route('evenements.update', $evenement->id), $this->donnees(['description' => 'Nouvelle description']))
            ->assertRedirect(route('evenements.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Nouvelle description', $evenement->fresh()->description);
    }

    public function test_deux_modifications_simultanees_du_meme_evenement_sont_refusees(): void
    {
        $evenement = Evenement::factory()->du('2026-12-01', '2026-12-02')->create(['nom' => 'Ramadan', 'description' => null]);
        Cache::lock('verrou:evenement-maj:' . $evenement->id, 60)->get();

        $this->put(route('evenements.update', $evenement->id), $this->donnees(['description' => 'Concurrente']))
            ->assertSessionHas('warning', fn(string $m) => str_contains($m, 'déjà en cours de modification'));

        $this->assertNull($evenement->fresh()->description);
    }

    // ══ Import CSV ════════════════════════════════════════════════════════

    public function test_un_fichier_csv_importe_deux_fois_ne_cree_les_evenements_qu_une_fois(): void
    {
        $fichier = self::ENTETE . "Ramadan;2026-12-01;2026-12-02;;;;\nAïd;2026-12-10;2026-12-11;;;;\n";

        $this->importer($fichier)->assertSessionHas('success');
        $this->assertSame(2, Evenement::count());

        $this->importer($fichier)->assertSessionHas('error', fn(string $m) => str_contains($m, 'un événement identique existe déjà (« Ramadan »'));

        $this->assertSame(2, Evenement::count(), 'le second envoi ne crée rien');
    }

    public function test_un_lot_contenant_un_doublon_est_refuse_en_entier(): void
    {
        Evenement::factory()->du('2026-12-01', '2026-12-02')->create(['nom' => 'Ramadan']);

        $this->importer(self::ENTETE . "Nouveau;2026-12-20;2026-12-21;;;;\nRamadan;2026-12-01;2026-12-02;;;;\n")
            ->assertSessionHas('error', fn(string $m) => str_contains($m, 'aucun événement n\'a été créé'));

        $this->assertSame(1, Evenement::count(), 'tout ou rien : même la ligne valide n\'est pas importée');
        $this->assertFalse(Evenement::where('nom', 'Nouveau')->exists());
    }

    public function test_un_meme_nom_sur_d_autres_dates_s_importe(): void
    {
        Evenement::factory()->du('2026-12-01', '2026-12-02')->create(['nom' => 'Ramadan']);

        $this->importer(self::ENTETE . "Ramadan;2027-02-01;2027-02-02;;;;\n")->assertSessionHas('success');

        $this->assertSame(2, Evenement::count());
    }

    public function test_un_import_csv_simultane_est_refuse(): void
    {
        Cache::lock('verrou:evenements-import', 60)->get();

        $this->importer(self::ENTETE . "Ramadan;2026-12-01;2026-12-02;;;;\n")
            ->assertRedirect(route('evenements.import'))
            ->assertSessionHas('warning', fn(string $m) => str_contains($m, 'import est déjà en cours'));

        $this->assertSame(0, Evenement::count());
    }

    // ══ Saisie manuelle en masse ══════════════════════════════════════════

    public function test_une_saisie_manuelle_soumise_deux_fois_ne_cree_les_evenements_qu_une_fois(): void
    {
        $lignes = ['rows' => [$this->ligneManuelle(), $this->ligneManuelle(['nom' => 'Autre', 'date_debut' => '2026-12-05', 'date_fin' => '2026-12-06'])]];

        $this->post(route('evenements.import.manuel'), $lignes)->assertSessionHas('success');
        $this->assertSame(2, Evenement::count());

        $this->post(route('evenements.import.manuel'), $lignes)
            ->assertRedirect(route('evenements.import'))
            ->assertSessionHas('error', fn(string $m) => str_contains($m, 'un événement identique existe déjà'));

        $this->assertSame(2, Evenement::count());
    }

    public function test_une_saisie_manuelle_simultanee_est_refusee_et_conserve_la_saisie(): void
    {
        Cache::lock('verrou:evenements-import', 60)->get();

        $this->post(route('evenements.import.manuel'), ['rows' => [$this->ligneManuelle()]])
            ->assertRedirect(route('evenements.import'))
            ->assertSessionHas('warning')
            ->assertSessionHasInput('rows');

        $this->assertSame(0, Evenement::count());
    }

    public function test_le_verrou_d_import_est_relache_apres_un_import(): void
    {
        $this->importer(self::ENTETE . "Ramadan;2026-12-01;2026-12-02;;;;\n")->assertSessionHas('success');

        $this->assertTrue(Cache::lock('verrou:evenements-import', 5)->get());
    }
}
