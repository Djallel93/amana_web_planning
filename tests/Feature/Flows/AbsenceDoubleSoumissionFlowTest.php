<?php
// tests/Feature/Flows/AbsenceDoubleSoumissionFlowTest.php
//
// Une absence soumise deux fois de suite ne doit créer qu'UNE ligne et ne régénérer le
// planning qu'UNE fois. Trois défenses côté serveur :
//   1. la validation refuse une absence identique (même personne, mêmes dates) ;
//   2. le contrôleur revérifie SOUS verrou (une requête jumelle a pu écrire entre-temps) ;
//   3. le verrou refuse une requête simultanée pour la même personne.
// (Le verrouillage du bouton côté navigateur — data-submit-lock — est un confort, pas la garantie.)
//
// Repères : « aujourd'hui » = mercredi 2026-09-30 ; planning généré à partir du vendredi 2026-10-02.

declare(strict_types=1);

namespace Tests\Feature\Flows;

use Amana\Shared\Models\AuditLog;
use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\Absence;
use App\Models\CreneauTache;
use App\Models\Personne;
use App\Services\SchedulerMain;
use App\Services\VerrouAction;
use Closure;
use Database\Factories\TacheFactory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ConnecteParRole;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class AbsenceDoubleSoumissionFlowTest extends TestCase
{
    use ConnecteParRole;
    use CreeDonneesPlanning;
    use RefreshesBothDatabases;

    private Personne $moi;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->travelTo('2026-09-30 09:00:00');
        $this->creerRolesPlanning();
        $this->tachesDeRotation();
        $this->personnesValidees(6);
        $this->moi = $this->connecterEn('membre');
        $this->app->make(SchedulerMain::class)->generateSchedule('2026-10-02', 3);
        Bus::fake();
        // « moi » doit tenir au moins une tâche le 02/10 : on la lui donne si la rotation l'a oubliée.
        if (CreneauTache::where('id_planning', $this->creneauLe('2026-10-02')->id)->where('id_personne', $this->moi->id)->doesntExist()) {
            CreneauTache::where('id_planning', $this->creneauLe('2026-10-02')->id)->where('id_tache', TacheFactory::pourCode('entree')->id)
                ->update(['id_personne' => $this->moi->id]);
        }
    }

    private function donnees(string $debut = '2026-10-02', string $fin = '2026-10-03', array $surcharge = []): array
    {
        return array_replace(['id_personne' => $this->moi->id, 'date_debut' => $debut, 'date_fin' => $fin, 'raison' => 'Vacances'], $surcharge);
    }

    private function nbRegenerations(): int
    {
        return AuditLog::where('module', 'planning')->where('action', 'generate')->count();
    }

    private function envoisGoogle(): int
    {
        return Bus::dispatched(SynchroniserGoogleCalendar::class)->count() + Bus::dispatchedSync(SynchroniserGoogleCalendar::class)->count();
    }

    /**
     * Simule une requête jumelle qui écrit APRÈS la validation de la nôtre mais AVANT que nous
     * prenions le verrou : `$avant` s'exécute (une seule fois) juste avant l'acquisition.
     */
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

    // ── Le cas du bug : deux soumissions consécutives ─────────────────────

    public function test_une_double_soumission_ne_cree_qu_une_absence_et_ne_regenere_qu_une_fois(): void
    {
        $regenerationsAvant = $this->nbRegenerations();

        $this->post(route('absences.store'), $this->donnees())->assertSessionHas('success');
        $apresLaPremiere = ['regenerations' => $this->nbRegenerations(), 'google' => $this->envoisGoogle()];

        $this->post(route('absences.store'), $this->donnees())
            ->assertSessionHasErrors(['date_debut' => Absence::MESSAGE_DOUBLON]);

        $this->assertSame(1, Absence::count(), 'une seule absence créée');
        $this->assertSame($regenerationsAvant + 1, $apresLaPremiere['regenerations'], 'la première soumission a bien régénéré');
        $this->assertSame($apresLaPremiere['regenerations'], $this->nbRegenerations(), 'la seconde ne régénère pas');
        $this->assertSame($apresLaPremiere['google'], $this->envoisGoogle(), 'ni ne renvoie de synchronisation Google');
        $this->assertSame(1, AuditLog::where('module', 'absences')->where('action', 'create')->count());
    }

    public function test_deux_absences_aux_dates_differentes_restent_possibles(): void
    {
        $this->post(route('absences.store'), $this->donnees('2026-10-02', '2026-10-03'))->assertSessionHas('success');
        $this->post(route('absences.store'), $this->donnees('2026-10-09', '2026-10-10'))->assertSessionHas('success');
        $this->post(route('absences.store'), $this->donnees('2026-10-02', '2026-10-10'))->assertSessionHas('success');

        $this->assertSame(3, Absence::count(), 'seule une absence STRICTEMENT identique est refusée (début ET fin)');
    }

    public function test_la_meme_periode_pour_une_autre_personne_est_acceptee(): void
    {
        $this->post(route('absences.store'), $this->donnees())->assertSessionHas('success');

        $gestionnaire = $this->connecterEn('gestionnaire');
        $this->post(route('absences.store'), $this->donnees('2026-10-02', '2026-10-03', ['id_personne' => $gestionnaire->id]))
            ->assertSessionHas('success');

        $this->assertSame(2, Absence::count());
    }

    // ── Modification ──────────────────────────────────────────────────────

    public function test_une_modification_ne_peut_pas_dupliquer_une_autre_absence(): void
    {
        $a = Absence::factory()->pour($this->moi)->du('2026-10-02', '2026-10-03')->create();
        $b = Absence::factory()->pour($this->moi)->du('2026-10-09', '2026-10-10')->create();

        $this->putJson(route('absences.update', $b->id), $this->donnees('2026-10-02', '2026-10-03'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_debut' => Absence::MESSAGE_DOUBLON]);

        $this->assertSame('2026-10-09', $b->fresh()->date_debut->toDateString(), 'l\'absence n\'a pas bougé');
        $this->assertSame(2, Absence::count());
        $this->assertNotNull($a->fresh());
    }

    public function test_enregistrer_une_absence_sans_la_changer_reste_permis(): void
    {
        $absence = Absence::factory()->pour($this->moi)->du('2026-10-02', '2026-10-03')->create();

        $this->putJson(route('absences.update', $absence->id), $this->donnees('2026-10-02', '2026-10-03', ['raison' => 'Autre raison']))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('Autre raison', $absence->fresh()->raison, 'l\'absence ne se compare pas à elle-même');
    }

    public function test_deux_modifications_simultanees_de_la_meme_absence_sont_refusees(): void
    {
        $absence = Absence::factory()->pour($this->moi)->du('2026-10-02', '2026-10-03')->create(['raison' => 'Originale']);
        Cache::lock('verrou:absence-maj:' . $absence->id, 60)->get();

        $this->putJson(route('absences.update', $absence->id), $this->donnees('2026-10-02', '2026-10-03', ['raison' => 'Concurrente']))
            ->assertStatus(409)
            ->assertJson(['success' => false]);

        $this->assertSame('Originale', $absence->fresh()->raison);
    }

    // ── Concurrence : le verrou ───────────────────────────────────────────

    public function test_une_requete_jumelle_ecrite_apres_la_validation_est_detectee_sous_verrou(): void
    {
        $this->simulerRequeteJumelle(fn() => Absence::factory()->pour($this->moi)->du('2026-10-02', '2026-10-03')->create());
        $regenerationsAvant = $this->nbRegenerations();

        $this->post(route('absences.store'), $this->donnees())
            ->assertSessionHas('warning', fn(string $m) => str_starts_with($m, Absence::MESSAGE_DOUBLON))
            ->assertSessionMissing('success');

        $this->assertSame(1, Absence::count(), 'seule la requête jumelle a écrit');
        $this->assertSame($regenerationsAvant, $this->nbRegenerations(), 'aucune régénération pour la requête refusée');
    }

    public function test_une_requete_simultanee_pour_la_meme_personne_est_refusee(): void
    {
        Cache::lock('verrou:absence:' . $this->moi->id, 60)->get();
        $regenerationsAvant = $this->nbRegenerations();

        $this->post(route('absences.store'), $this->donnees())
            ->assertRedirect(route('absences.index'))
            ->assertSessionHas('warning', fn(string $m) => str_contains($m, 'déjà en cours d\'enregistrement'));

        $this->assertSame(0, Absence::count());
        $this->assertSame($regenerationsAvant, $this->nbRegenerations());
    }

    public function test_le_verrou_d_une_personne_ne_bloque_pas_les_autres(): void
    {
        $this->connecterEn('gestionnaire');
        $autre = Personne::factory()->create();
        Cache::lock('verrou:absence:' . $this->moi->id, 60)->get();

        $this->post(route('absences.store'), $this->donnees('2026-10-02', '2026-10-03', ['id_personne' => $autre->id]))
            ->assertSessionHas('success');

        $this->assertSame(1, Absence::where('id_personne', $autre->id)->count());
    }

    public function test_le_verrou_est_relache_apres_l_enregistrement(): void
    {
        $this->post(route('absences.store'), $this->donnees())->assertSessionHas('success');

        $this->assertTrue(Cache::lock('verrou:absence:' . $this->moi->id, 5)->get(), 'le verrou ne reste pas pris');
    }
}
