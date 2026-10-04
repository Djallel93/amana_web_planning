<?php
// tests/Feature/Flows/AbsenceRegenerationFlowTest.php
//
// Enregistrer / modifier / supprimer une absence par HTTP, et ce que cela fait au planning déjà
// généré. La logique de choix de la date impactée est dans AbsenceRegenerationServiceTest ; ici :
// l'enchaînement complet avec le vrai générateur et les messages renvoyés à l'utilisateur.
//
// Repères : « aujourd'hui » = mercredi 2026-09-30 ; planning généré à partir du vendredi 2026-10-02.

declare(strict_types=1);

namespace Tests\Feature\Flows;

use Amana\Shared\Models\AuditLog;
use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\Absence;
use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Personne;
use App\Services\SchedulerMain;
use Database\Factories\TacheFactory;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\ConnecteParRole;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\Support\FauxSettings;
use Tests\TestCase;

class AbsenceRegenerationFlowTest extends TestCase
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

    protected function tearDown(): void
    {
        FauxSettings::reinitialiser();
        parent::tearDown();
    }

    /** La synchronisation d'une absence n'a lieu que si un calendrier des absences est configuré. */
    private function avecCalendrierDesAbsences(): void
    {
        FauxSettings::definir(['calendar_absence' => 'absences@group.calendar.google.com']);
    }

    private function donnees(string $debut, string $fin, array $surcharge = []): array
    {
        return array_replace(['id_personne' => $this->moi->id, 'date_debut' => $debut, 'date_fin' => $fin, 'raison' => 'Vacances'], $surcharge);
    }

    private function tachesDe(Personne $p, string ...$dates): int
    {
        return CreneauTache::where('id_personne', $p->id)
            ->whereIn('id_planning', Creneau::whereIn('date', $dates)->pluck('id'))->count();
    }

    private function envoisGoogle(): int
    {
        return Bus::dispatched(SynchroniserGoogleCalendar::class)->count() + Bus::dispatchedSync(SynchroniserGoogleCalendar::class)->count();
    }

    // ── Création ──────────────────────────────────────────────────────────

    public function test_une_absence_qui_chevauche_mes_creneaux_regenere_le_planning(): void
    {
        $this->assertGreaterThan(0, $this->tachesDe($this->moi, '2026-10-02'));

        $this->post(route('absences.store'), $this->donnees('2026-10-02', '2026-10-03'))
            ->assertRedirect(route('absences.index'))
            ->assertSessionHas('success', function (string $m) {
                return str_starts_with($m, "Absence ajoutée pour {$this->moi->prenom} {$this->moi->nom}. Planning régénéré automatiquement à partir du 2 octobre 2026")
                    && str_ends_with($m, 'pour tenir compte de cette absence.');
            });

        $this->assertSame(0, $this->tachesDe($this->moi, '2026-10-02', '2026-10-03'), 'plus aucune tâche pendant l\'absence');
        $this->assertSame(6, Creneau::count());
        $this->assertSame(30, CreneauTache::count(), 'les créneaux sont recréés complets');
    }

    public function test_la_regeneration_pousse_la_synchronisation_en_plus_de_celle_de_l_absence(): void
    {
        $this->avecCalendrierDesAbsences();
        $this->post(route('absences.store'), $this->donnees('2026-10-02', '2026-10-03'));

        $this->assertSame(2, $this->envoisGoogle(), 'un envoi pour l\'absence, un pour le planning régénéré');
    }

    public function test_sans_calendrier_des_absences_seule_la_regeneration_est_synchronisee(): void
    {
        $this->post(route('absences.store'), $this->donnees('2026-10-02', '2026-10-03'));

        $this->assertSame(1, $this->envoisGoogle(), 'le planning régénéré uniquement : pas d\'événement d\'absence sans calendrier configuré');
    }

    public function test_une_absence_sans_effet_sur_le_planning_ne_le_regenere_pas(): void
    {
        $this->avecCalendrierDesAbsences();
        $idsAvant = Creneau::orderBy('id')->pluck('id')->all();

        $this->post(route('absences.store'), $this->donnees('2027-01-04', '2027-01-08'))
            ->assertSessionHas('success', "Absence ajoutée pour {$this->moi->prenom} {$this->moi->nom}.");

        $this->assertSame($idsAvant, Creneau::orderBy('id')->pluck('id')->all(), 'aucun créneau recréé');
        $this->assertSame(1, $this->envoisGoogle(), 'seule l\'absence est synchronisée');
    }

    public function test_une_absence_dans_le_passe_ne_touche_pas_au_planning(): void
    {
        $this->travelTo('2026-10-10 09:00:00');
        $idsAvant = Creneau::orderBy('id')->pluck('id')->all();

        $this->post(route('absences.store'), $this->donnees('2026-10-02', '2026-10-03'))
            ->assertSessionHas('success', "Absence ajoutée pour {$this->moi->prenom} {$this->moi->nom}.");

        $this->assertSame($idsAvant, Creneau::orderBy('id')->pluck('id')->all());
    }

    public function test_les_semaines_anterieures_a_l_absence_ne_sont_pas_modifiees(): void
    {
        $semaine1 = Creneau::whereIn('date', ['2026-10-02', '2026-10-03'])->pluck('id')->all();
        $avant = CreneauTache::whereIn('id_planning', $semaine1)->orderBy('id_planning')->orderBy('id_tache')->get(['id_planning', 'id_tache', 'id_personne'])->toArray();
        $apres10 = CreneauTache::where('id_personne', $this->moi->id)->whereIn('id_planning', Creneau::where('date', '>=', '2026-10-09')->pluck('id'))->count();
        $this->assertGreaterThan(0, $apres10, 'moi : au moins une tâche à partir du 09/10');

        $this->post(route('absences.store'), $this->donnees('2026-10-09', '2026-10-10'));

        $this->assertSame($avant, CreneauTache::whereIn('id_planning', $semaine1)->orderBy('id_planning')->orderBy('id_tache')->get(['id_planning', 'id_tache', 'id_personne'])->toArray(), 'semaine 1 intacte');
        $this->assertSame($semaine1, Creneau::whereIn('date', ['2026-10-02', '2026-10-03'])->pluck('id')->all(), 'mêmes créneaux (non recréés)');
    }

    public function test_la_creation_est_journalisee(): void
    {
        $this->post(route('absences.store'), $this->donnees('2027-01-04', '2027-01-08'));

        $this->assertSame(1, AuditLog::where('module', 'absences')->where('action', 'create')->count());
    }

    // ── Modification ──────────────────────────────────────────────────────

    public function test_etendre_une_absence_sur_mes_creneaux_regenere_le_planning_et_le_dit(): void
    {
        $absence = Absence::factory()->pour($this->moi)->du('2027-01-04', '2027-01-08')->create();

        $this->putJson(route('absences.update', $absence->id), $this->donnees('2026-10-02', '2026-10-03'))
            ->assertOk()
            ->assertJson(['success' => true, 'planning_regenere' => true])
            ->assertJsonPath('absence.date_debut', '2026-10-02')
            ->assertJsonPath('message', fn(string $m) => str_contains($m, 'Planning régénéré automatiquement'));

        $this->assertSame(0, $this->tachesDe($this->moi, '2026-10-02', '2026-10-03'));
    }

    public function test_modifier_sans_effet_sur_le_planning_renvoie_planning_regenere_faux(): void
    {
        $absence = Absence::factory()->pour($this->moi)->du('2027-01-04', '2027-01-08')->create();

        $this->putJson(route('absences.update', $absence->id), $this->donnees('2027-02-01', '2027-02-05'))
            ->assertOk()
            ->assertJson(['planning_regenere' => false])
            ->assertJsonPath('message', "Absence de {$this->moi->prenom} {$this->moi->nom} mise à jour.");
    }

    /**
     * CARACTÉRISATION : la régénération n'est déclenchée qu'à partir des NOUVELLES dates. Si on
     * raccourcit une absence, les jours libérés ne sont pas re-planifiés : la personne, retirée du
     * planning pendant l'absence initiale, n'y revient pas — et le planning ne le signale pas.
     */
    public function test_raccourcir_une_absence_ne_rend_pas_les_jours_liberes_a_la_personne(): void
    {
        $this->post(route('absences.store'), $this->donnees('2026-10-02', '2026-10-03'));
        $absence = Absence::where('id_personne', $this->moi->id)->firstOrFail();
        $this->assertSame(0, $this->tachesDe($this->moi, '2026-10-02'));

        // Elle n'est finalement absente que la semaine suivante : le 02/10 est de nouveau disponible…
        $this->putJson(route('absences.update', $absence->id), $this->donnees('2027-01-04', '2027-01-08'))
            ->assertOk()->assertJson(['planning_regenere' => false]);

        $this->assertSame(0, $this->tachesDe($this->moi, '2026-10-02'), '… mais le planning n\'a pas été recalculé : elle n\'y est pas revenue');
    }

    // ── Suppression ───────────────────────────────────────────────────────

    /**
     * CARACTÉRISATION : supprimer une absence ne régénère rien. Le planning a été recalculé SANS
     * la personne quand l'absence a été créée ; l'annuler ne la remet pas en rotation. Il faut
     * relancer manuellement une génération pour que ses créneaux lui soient rendus.
     */
    public function test_supprimer_une_absence_ne_remet_pas_la_personne_dans_le_planning(): void
    {
        $this->avecCalendrierDesAbsences();
        $this->post(route('absences.store'), $this->donnees('2026-10-02', '2026-10-03'));
        $absence = Absence::where('id_personne', $this->moi->id)->firstOrFail();
        $absence->update(['google_calendar_id' => 'absences@group.calendar.google.com', 'google_event_id' => 'evt_absence']); // déjà synchronisée
        $idsAvant = Creneau::orderBy('id')->pluck('id')->all();
        Bus::fake();

        $this->delete(route('absences.destroy', $absence->id))
            ->assertRedirect(route('absences.index'))
            ->assertSessionHas('success', "Absence de {$this->moi->prenom} {$this->moi->nom} supprimée.");

        $this->assertNull(Absence::find($absence->id));
        $this->assertSame($idsAvant, Creneau::orderBy('id')->pluck('id')->all(), 'aucune régénération');
        $this->assertSame(0, $this->tachesDe($this->moi, '2026-10-02'), 'toujours absente du planning');
        $this->assertSame(1, $this->envoisGoogle(), 'seule la suppression de l\'événement d\'absence part vers Google');
    }

    public function test_supprimer_une_absence_jamais_synchronisee_n_appelle_pas_google(): void
    {
        $absence = Absence::factory()->pour($this->moi)->du('2027-01-04', '2027-01-08')->create();

        $this->delete(route('absences.destroy', $absence->id))->assertSessionHas('success');

        $this->assertSame(0, $this->envoisGoogle());
    }

    public function test_la_suppression_est_journalisee_avec_l_etat_precedent(): void
    {
        $absence = Absence::factory()->pour($this->moi)->du('2027-01-04', '2027-01-08')->create(['raison' => 'Vacances']);

        $this->delete(route('absences.destroy', $absence->id));

        $entree = AuditLog::where('module', 'absences')->where('action', 'delete')->firstOrFail();
        $this->assertSame('Vacances', $entree->before['raison']);
        $this->assertNull($entree->after);
    }
}
