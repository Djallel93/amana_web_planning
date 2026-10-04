<?php
// tests/Feature/Services/AbsenceRegenerationServiceTest.php
//
// Deux familles : des tests de logique avec un SchedulerMain « espion » (quelle
// date est jugée impactée ? que renvoie-t-on ?), et deux tests de bout en bout
// avec le vrai générateur. Google Calendar est simulé (Bus::fake()).
//
// Repères : 2026-10-02 est un vendredi, 2026-10-03 un samedi.

declare(strict_types=1);

namespace Tests\Feature\Services;

use Amana\Shared\Models\AuditLog;
use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\Absence;
use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Personne;
use App\Services\AbsenceRegenerationService;
use App\Services\DataLoader;
use App\Services\RotationEngine;
use App\Services\SchedulerMain;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\Support\SchedulerEspion;
use Tests\TestCase;

class AbsenceRegenerationServiceTest extends TestCase
{
    use CreeDonneesPlanning;
    use RefreshesBothDatabases;

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

    private function absence(Personne $p, string $debut, string $fin): Absence
    {
        return Absence::factory()->pour($p)->du($debut, $fin)->create();
    }

    // ── Quelle date est impactée ? ────────────────────────────────────────

    public function test_sans_assignation_dans_la_periode_rien_n_est_regenere(): void
    {
        [$p] = $this->personnesValidees(1);
        $this->assigner($p, '2026-10-09', 'entree'); // hors de l'absence
        $espion = $this->espion();

        $resultat = (new AbsenceRegenerationService($espion))->regenererSiNecessaire($this->absence($p, '2026-10-02', '2026-10-03'));

        $this->assertNull($resultat);
        $this->assertSame([], $espion->dates);
        $this->assertSame(0, AuditLog::where('module', 'planning')->count());
        Bus::assertNothingDispatched();
    }

    public function test_la_premiere_date_impactee_est_la_plus_ancienne_assignation_de_la_personne_dans_la_periode(): void
    {
        [$p] = $this->personnesValidees(1);
        foreach (['2026-10-10', '2026-10-02', '2026-10-03', '2026-10-16'] as $date) {
            $this->assigner($p, $date, 'entree');
        }
        $espion = $this->espion();

        (new AbsenceRegenerationService($espion))->regenererSiNecessaire($this->absence($p, '2026-10-02', '2026-10-11'));

        $this->assertSame(['2026-10-02'], $espion->dates);
    }

    public function test_les_bornes_de_l_absence_sont_incluses_et_les_jours_voisins_exclus(): void
    {
        [$p] = $this->personnesValidees(1);
        $this->assigner($p, '2026-10-01', 'entree'); // la veille
        $this->assigner($p, '2026-10-04', 'entree'); // le lendemain
        $absence = $this->absence($p, '2026-10-02', '2026-10-03');
        $this->assertNull((new AbsenceRegenerationService($this->espion()))->regenererSiNecessaire($absence));

        $this->assigner($p, '2026-10-03', 'salle'); // pile sur la dernière journée
        $espion = $this->espion();
        (new AbsenceRegenerationService($espion))->regenererSiNecessaire($absence);
        $this->assertSame(['2026-10-03'], $espion->dates);
    }

    public function test_seules_les_assignations_de_la_personne_absente_comptent(): void
    {
        [$absente, $autre] = $this->personnesValidees(2);
        $this->assigner($autre, '2026-10-02', 'entree');

        $espion = $this->espion();
        $resultat = (new AbsenceRegenerationService($espion))->regenererSiNecessaire($this->absence($absente, '2026-10-02', '2026-10-03'));

        $this->assertNull($resultat);
        $this->assertSame([], $espion->dates);
    }

    public function test_une_assignation_deja_passee_n_est_pas_regeneree(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 09:00:00'));
        [$p] = $this->personnesValidees(1);
        $this->assigner($p, '2026-10-02', 'entree'); // passée

        $resultat = (new AbsenceRegenerationService($this->espion()))->regenererSiNecessaire($this->absence($p, '2026-10-02', '2026-10-11'));

        $this->assertNull($resultat);
    }

    public function test_l_assignation_d_aujourd_hui_compte(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 08:00:00'));
        [$p] = $this->personnesValidees(1);
        $this->assigner($p, '2026-10-02', 'entree');
        $espion = $this->espion();

        (new AbsenceRegenerationService($espion))->regenererSiNecessaire($this->absence($p, '2026-09-28', '2026-10-05'));

        $this->assertSame(['2026-10-02'], $espion->dates);
    }

    // ── Réussite ──────────────────────────────────────────────────────────

    public function test_succes_renvoie_un_message_journalise_et_synchronise_google_calendar(): void
    {
        [$p] = $this->personnesValidees(1);
        $this->assigner($p, '2026-10-02', 'entree');
        $absence = $this->absence($p, '2026-10-02', '2026-10-03');

        $resultat = (new AbsenceRegenerationService($this->espion()))->regenererSiNecessaire($absence);

        $this->assertSame(
            'Planning régénéré automatiquement à partir du 2 octobre 2026 (4 jours, 1 non assigné(s)) pour tenir compte de cette absence.',
            $resultat['message'],
        );
        $audit = AuditLog::where('module', 'planning')->where('action', 'generate')->firstOrFail();
        $this->assertSame('absence', $audit->after['declencheur']);
        $this->assertSame($absence->id, $audit->after['id_absence']);
        $this->assertSame($p->id, $audit->after['id_personne']);
        $this->assertSame(4, $audit->after['jours_generes']);
        Bus::assertDispatchedTimes(SynchroniserGoogleCalendar::class, 1);
    }

    // ── Échec du générateur ───────────────────────────────────────────────

    public function test_un_echec_du_generateur_renvoie_un_avertissement_sans_journal_ni_synchronisation(): void
    {
        [$p] = $this->personnesValidees(1);
        $this->assigner($p, '2026-10-02', 'entree');
        $espion = $this->espion();
        $espion->exception = new RuntimeException('Aucune personne active dans le planning.');

        $resultat = (new AbsenceRegenerationService($espion))->regenererSiNecessaire($this->absence($p, '2026-10-02', '2026-10-03'));

        $this->assertStringStartsWith('⚠️ La réassignation automatique du planning a échoué (Aucune personne active dans le planning.)', $resultat['message']);
        $this->assertStringContainsString('Planning > Générer', $resultat['message']);
        $this->assertSame(0, AuditLog::where('module', 'planning')->count());
        Bus::assertNothingDispatched();
    }

    // ── De bout en bout avec le vrai générateur ───────────────────────────

    public function test_une_personne_absente_perd_ses_taches_apres_regeneration(): void
    {
        $this->tachesDeRotation();
        $this->personnesValidees(6);
        $scheduler = $this->app->make(SchedulerMain::class);
        $scheduler->generateSchedule('2026-10-02', 3);
        $titulaire = Personne::find($this->idPersonneDuCreneau('2026-10-02', 'entree'));
        $this->assertNotNull($titulaire);

        $resultat = (new AbsenceRegenerationService($scheduler))
            ->regenererSiNecessaire($this->absence($titulaire, '2026-10-02', '2026-10-03'));

        $this->assertStringStartsWith('Planning régénéré automatiquement', $resultat['message']);
        $this->assertSame(0, CreneauTache::where('id_personne', $titulaire->id)
            ->whereIn('id_planning', Creneau::whereIn('date', ['2026-10-02', '2026-10-03'])->pluck('id'))->count());
        $this->assertSame(6, Creneau::count(), 'les trois semaines existent toujours');
        $this->assertSame(30, CreneauTache::count());
        Bus::assertDispatchedTimes(SynchroniserGoogleCalendar::class, 1);
    }

    /**
     * CARACTÉRISATION : quand la date impactée est un SAMEDI, la régénération repart du
     * VENDREDI précédent (SchedulerMain::regenerateFromImpactedDate). Si ce vendredi est
     * déjà passé — ici la veille —, son créneau est supprimé et recréé : ce qui s'est
     * réellement passé hier est réécrit. Le filtre « date >= aujourd'hui » du service
     * ne protège que la date impactée, pas le point de départ de la régénération.
     */
    public function test_un_impact_le_samedi_reecrit_le_vendredi_de_la_veille(): void
    {
        $this->tachesDeRotation();
        $this->personnesValidees(6);
        $scheduler = $this->app->make(SchedulerMain::class);
        $scheduler->generateSchedule('2026-10-02', 2);
        Carbon::setTestNow(Carbon::parse('2026-10-03 10:00:00')); // samedi ; le vendredi 02/10 est passé
        $idVendrediAvant = $this->creneauLe('2026-10-02')->id;
        $titulaireSamedi = Personne::find($this->idPersonneDuCreneau('2026-10-03', 'entree'));

        (new AbsenceRegenerationService($scheduler))->regenererSiNecessaire($this->absence($titulaireSamedi, '2026-10-03', '2026-10-03'));

        $this->assertNotSame($idVendrediAvant, Creneau::where('date', '2026-10-02')->value('id'), 'le créneau d\'hier a été supprimé puis recréé');
    }
}
