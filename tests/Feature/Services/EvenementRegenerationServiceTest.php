<?php
// tests/Feature/Services/EvenementRegenerationServiceTest.php
//
// Même structure que AbsenceRegenerationServiceTest : logique avec un
// SchedulerMain « espion », puis un test de bout en bout avec le vrai générateur.
//
// Repères : 2026-10-02 est un vendredi. « Aujourd'hui » est fixé au 2026-09-30.

declare(strict_types=1);

namespace Tests\Feature\Services;

use Amana\Shared\Models\AuditLog;
use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\Creneau;
use App\Models\Evenement;
use App\Services\DataLoader;
use App\Services\EvenementRegenerationService;
use App\Services\RotationEngine;
use App\Services\SchedulerMain;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\Support\SchedulerEspion;
use Tests\TestCase;

class EvenementRegenerationServiceTest extends TestCase
{
    use RefreshesBothDatabases;
    use CreeDonneesPlanning;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-30 09:00:00'));
        Bus::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function espion(): SchedulerEspion
    {
        return new SchedulerEspion($this->app->make(DataLoader::class), $this->app->make(RotationEngine::class));
    }

    private function evenement(string $debut, string $fin, array $attributs = []): Evenement
    {
        return Evenement::factory()->du($debut, $fin)->create($attributs);
    }

    // ── Formes d'entrée ───────────────────────────────────────────────────

    public function test_rien_a_regenerer_avec_une_liste_vide(): void
    {
        $espion = $this->espion();
        $service = new EvenementRegenerationService($espion);

        $this->assertNull($service->regenererSiNecessaire([]));
        $this->assertNull($service->regenererSiNecessaire(collect()));
        $this->assertSame([], $espion->dates);
    }

    public function test_accepte_un_evenement_un_tableau_ou_une_collection(): void
    {
        $this->creneauLe('2026-10-02');
        $evenement = $this->evenement('2026-10-02', '2026-10-03');

        foreach ([$evenement, [$evenement], collect([$evenement])] as $entree) {
            $espion = $this->espion();

            $resultat = (new EvenementRegenerationService($espion))->regenererSiNecessaire($entree);

            $this->assertNotNull($resultat);
            $this->assertSame(['2026-10-02'], $espion->dates);
        }
    }

    // ── Quelle date est impactée ? ────────────────────────────────────────

    public function test_sans_creneau_dans_la_periode_rien_n_est_regenere(): void
    {
        $this->creneauLe('2026-10-16');
        $espion = $this->espion();

        $resultat = (new EvenementRegenerationService($espion))->regenererSiNecessaire($this->evenement('2026-10-02', '2026-10-10'));

        $this->assertNull($resultat);
        $this->assertSame([], $espion->dates);
        Bus::assertNothingDispatched();
    }

    public function test_un_evenement_entierement_passe_n_est_jamais_regenere_retroactivement(): void
    {
        $this->creneauLe('2026-09-18');

        $resultat = (new EvenementRegenerationService($this->espion()))->regenererSiNecessaire($this->evenement('2026-09-15', '2026-09-20'));

        $this->assertNull($resultat);
    }

    public function test_un_evenement_qui_a_commence_ne_regenere_qu_a_partir_d_aujourd_hui(): void
    {
        $this->creneauLe('2026-09-25');   // dans l'événement mais déjà passé
        $this->creneauLe('2026-10-02');   // premier créneau non passé
        $espion = $this->espion();

        (new EvenementRegenerationService($espion))->regenererSiNecessaire($this->evenement('2026-09-20', '2026-10-10'));

        $this->assertSame(['2026-10-02'], $espion->dates);
    }

    public function test_un_evenement_qui_couvre_plusieurs_creneaux_regenere_depuis_le_premier(): void
    {
        foreach (['2026-10-09', '2026-10-02', '2026-10-16', '2026-10-17'] as $date) {
            $this->creneauLe($date);
        }
        $espion = $this->espion();

        (new EvenementRegenerationService($espion))->regenererSiNecessaire($this->evenement('2026-10-01', '2026-10-31'));

        $this->assertSame(['2026-10-02'], $espion->dates);
    }

    public function test_les_bornes_de_l_evenement_sont_incluses(): void
    {
        $this->creneauLe('2026-10-01'); // veille
        $this->creneauLe('2026-10-04'); // lendemain
        $this->assertNull((new EvenementRegenerationService($this->espion()))->regenererSiNecessaire($this->evenement('2026-10-02', '2026-10-03')));

        $this->creneauLe('2026-10-03');
        $espion = $this->espion();
        (new EvenementRegenerationService($espion))->regenererSiNecessaire($this->evenement('2026-10-02', '2026-10-03'));
        $this->assertSame(['2026-10-03'], $espion->dates);
    }

    public function test_avec_plusieurs_evenements_c_est_la_premiere_date_impactee_toutes_periodes_confondues(): void
    {
        foreach (['2026-10-02', '2026-10-16', '2026-10-30'] as $d) {
            $this->creneauLe($d);
        }
        $tardif = $this->evenement('2026-10-30', '2026-10-31');
        $precoce = $this->evenement('2026-10-01', '2026-10-05');
        $sansEffet = $this->evenement('2026-11-10', '2026-11-11');
        $espion = $this->espion();

        (new EvenementRegenerationService($espion))->regenererSiNecessaire([$tardif, $sansEffet, $precoce]);

        $this->assertSame(['2026-10-02'], $espion->dates, 'un seul appel pour tout le lot');
    }

    // ── Réussite ──────────────────────────────────────────────────────────

    public function test_succes_avec_un_seul_evenement_le_message_le_nomme(): void
    {
        $this->creneauLe('2026-10-02');
        $evenement = $this->evenement('2026-10-02', '2026-10-03', ['nom' => 'Ramadan']);

        $resultat = (new EvenementRegenerationService($this->espion()))->regenererSiNecessaire($evenement);

        $this->assertSame(
            'Planning régénéré automatiquement à partir du 2 octobre 2026 (4 jours, 1 non assigné(s)) pour tenir compte de l\'événement « Ramadan ».',
            $resultat['message'],
        );
    }

    public function test_succes_avec_plusieurs_evenements_le_message_les_compte(): void
    {
        $this->creneauLe('2026-10-02');
        $lot = [$this->evenement('2026-10-02', '2026-10-03'), $this->evenement('2026-10-02', '2026-10-09'), $this->evenement('2026-10-01', '2026-10-05')];

        $resultat = (new EvenementRegenerationService($this->espion()))->regenererSiNecessaire($lot);

        $this->assertStringEndsWith('pour tenir compte de 3 événements importés.', $resultat['message']);
    }

    public function test_succes_journalise_le_declencheur_et_synchronise_google_calendar_une_fois(): void
    {
        $this->creneauLe('2026-10-02');
        $lot = [$this->evenement('2026-10-02', '2026-10-03'), $this->evenement('2026-10-02', '2026-10-09')];

        (new EvenementRegenerationService($this->espion()))->regenererSiNecessaire($lot);

        $audit = AuditLog::where('module', 'planning')->where('action', 'generate')->firstOrFail();
        $this->assertSame('evenement', $audit->after['declencheur']);
        $this->assertSame(2, $audit->after['nb_evenements']);
        $this->assertSame([$lot[0]->id, $lot[1]->id], $audit->after['ids_evenements']);
        Bus::assertDispatchedTimes(SynchroniserGoogleCalendar::class, 1);
    }

    // ── Échec du générateur ───────────────────────────────────────────────

    public function test_un_echec_du_generateur_renvoie_un_avertissement_sans_journal_ni_synchronisation(): void
    {
        $this->creneauLe('2026-10-02');
        $espion = $this->espion();
        $espion->exception = new RuntimeException('base indisponible');

        $resultat = (new EvenementRegenerationService($espion))->regenererSiNecessaire($this->evenement('2026-10-02', '2026-10-03'));

        $this->assertStringStartsWith('⚠️ La mise à jour automatique du planning a échoué (base indisponible)', $resultat['message']);
        $this->assertSame(0, AuditLog::where('module', 'planning')->count());
        Bus::assertNothingDispatched();
    }

    // ── De bout en bout avec le vrai générateur ───────────────────────────

    public function test_un_evenement_bloquant_vide_les_taches_du_jour_apres_regeneration(): void
    {
        $taches = $this->tachesDeRotation();
        $this->personnesValidees(6);
        $scheduler = $this->app->make(SchedulerMain::class);
        $scheduler->generateSchedule('2026-10-02', 3);
        foreach (self::CODES_TACHES as $code) {
            $this->assertNotNull($this->idPersonneDuCreneau('2026-10-09', $code), "avant : {$code} est assignée");
        }
        $evenement = Evenement::factory()->du('2026-10-09', '2026-10-09')->bloquant(...array_values($taches))->create();

        $resultat = (new EvenementRegenerationService($scheduler))->regenererSiNecessaire($evenement);

        $this->assertStringStartsWith('Planning régénéré automatiquement', $resultat['message']);
        foreach (self::CODES_TACHES as $code) {
            $this->assertNull($this->idPersonneDuCreneau('2026-10-09', $code), "après : {$code} est bloquée");
        }
        $this->assertSame(6, Creneau::count());
        Bus::assertDispatchedTimes(SynchroniserGoogleCalendar::class, 1);
    }
}
