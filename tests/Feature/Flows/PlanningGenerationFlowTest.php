<?php
// tests/Feature/Flows/PlanningGenerationFlowTest.php
//
// Génération, prévisualisation et « rollback » du planning via PlanningController. La génération
// elle-même est testée dans SchedulerMainTest ; ici : le contrat HTTP (validation, confirmation,
// messages, session, synchronisation Google). Bus::fake() : rien ne part vers Google.
//
// Repères : « aujourd'hui » est fixé au lundi 2026-09-14 ; 2026-09-18 est un vendredi.

declare(strict_types=1);

namespace Tests\Feature\Flows;

use Amana\Shared\Models\AuditLog;
use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Personne;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ConnecteParRole;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\Support\FauxSettings;
use Tests\TestCase;

class PlanningGenerationFlowTest extends TestCase
{
    use ConnecteParRole;
    use CreeDonneesPlanning;
    use RefreshesBothDatabases;

    private const VENDREDI = '2026-09-18';

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->travelTo('2026-09-14 10:00:00');
        $this->creerRolesPlanning();
        $this->tachesDeRotation();
        $this->personnesValidees(6);
        $this->connecterEn('gestionnaire');
    }

    protected function tearDown(): void
    {
        FauxSettings::reinitialiser();
        parent::tearDown();
    }

    private function generer(array $surcharge = [])
    {
        return $this->post(route('planning.generate'), array_replace(['date_debut' => self::VENDREDI, 'semaines' => 2], $surcharge));
    }

    // ── Formulaire et validation ──────────────────────────────────────────

    public function test_le_formulaire_de_generation_s_affiche(): void
    {
        $this->get(route('planning.generate.form'))->assertOk();
    }

    /** @return array<string, array{array, string}> */
    public static function requetesInvalides(): array
    {
        return [
            'date manquante' => [['date_debut' => ''], 'date_debut'],
            'date non valide' => [['date_debut' => 'pas-une-date'], 'date_debut'],
            'date passée' => [['date_debut' => '2026-09-13'], 'date_debut'],
            'semaines manquantes' => [['semaines' => ''], 'semaines'],
            'zéro semaine' => [['semaines' => 0], 'semaines'],
            '53 semaines' => [['semaines' => 53], 'semaines'],
            'semaines non entières' => [['semaines' => 'beaucoup'], 'semaines'],
        ];
    }

    #[DataProvider('requetesInvalides')]
    public function test_une_requete_invalide_ne_genere_rien(array $surcharge, string $champ): void
    {
        $this->generer($surcharge)->assertSessionHasErrors($champ);
        $this->post(route('planning.preview'), array_replace(['date_debut' => self::VENDREDI, 'semaines' => 2], $surcharge))->assertSessionHasErrors($champ);

        $this->assertSame(0, Creneau::count());
        Bus::assertNothingDispatched();
    }

    public function test_la_date_du_jour_et_les_bornes_de_semaines_sont_acceptees(): void
    {
        $this->generer(['date_debut' => '2026-09-14', 'semaines' => 1])->assertSessionDoesntHaveErrors();
        $this->generer(['date_debut' => '2026-09-14', 'semaines' => 52])->assertSessionDoesntHaveErrors();
    }

    // ── Génération ────────────────────────────────────────────────────────

    public function test_generer_cree_le_planning_et_le_resume(): void
    {
        $this->generer()
            ->assertRedirect(route('planning.generate.form'))
            ->assertSessionHas('success', fn(string $m) => (bool) preg_match('/^Planning généré : 4 jours créés en [\d.]+ms\. \(0 non assigné\(s\)\)$/', $m));

        $this->assertSame(4, Creneau::count());
        $this->assertSame(20, CreneauTache::count());
    }

    public function test_generer_garde_un_instantane_pour_le_rollback_et_journalise(): void
    {
        $this->generer();

        $this->assertSame(Creneau::orderBy('date')->pluck('id')->all(), array_column(session('last_generated_creneaux'), 'id'));
        $entree = AuditLog::where('module', 'planning')->where('action', 'generate')->firstOrFail();
        $this->assertSame(4, $entree->after['jours_generes']);
    }

    public function test_generer_pousse_la_synchronisation_google_en_file(): void
    {
        $this->generer();

        Bus::assertDispatchedTimes(SynchroniserGoogleCalendar::class, 1);
        Bus::assertNotDispatchedSync(SynchroniserGoogleCalendar::class);
    }

    public function test_generer_avertit_des_taches_sans_calendrier_google(): void
    {
        $this->generer()
            ->assertSessionHas('success')
            ->assertSessionHas('warning', fn(string $m) => str_contains($m, 'Aucun calendrier Google configuré pour :') && str_contains($m, 'Paramètres'));
    }

    public function test_aucun_avertissement_quand_tous_les_calendriers_sont_configures(): void
    {
        $codes = ['entree', 'mektaba', 'salle', 'amana_food', 'cours', 'rappel_sandwich', 'assistance_amana_food', 'annonce_cours', 'message_bot'];
        FauxSettings::definir(array_combine(array_map(fn($c) => "calendar_{$c}", $codes), array_map(fn($c) => "{$c}@group.calendar.google.com", $codes)));

        $this->generer()->assertSessionHas('success')->assertSessionMissing('warning');
    }

    public function test_generer_sans_personne_active_affiche_l_erreur_et_ne_cree_rien(): void
    {
        Personne::where('statut', 'Validé')->whereDoesntHave('roles')->update(['statut' => 'Suspendu']);
        Personne::whereHas('roles')->update(['statut' => 'Suspendu']);

        $this->from(route('planning.generate.form'))->generer()
            ->assertRedirect(route('planning.generate.form'))
            ->assertSessionHas('error', 'Erreur lors de la génération : Aucune personne active dans le planning.')
            ->assertSessionHasInput('semaines', 2);

        $this->assertSame(0, Creneau::count());
        $this->assertNull(session('last_generated_creneaux'));
        Bus::assertNothingDispatched();
    }

    // ── Confirmation avant écrasement ─────────────────────────────────────

    public function test_generer_par_dessus_un_planning_existant_demande_confirmation_et_ne_touche_a_rien(): void
    {
        $this->generer();
        $idsAvant = Creneau::orderBy('id')->pluck('id')->all();
        Bus::fake();

        $this->generer()
            ->assertRedirect(route('planning.generate.form'))
            ->assertSessionHas('warning', 'Des créneaux existants vont être supprimés. Veuillez confirmer ci-dessous.');

        $this->assertSame($idsAvant, Creneau::orderBy('id')->pluck('id')->all(), 'rien n\'est supprimé avant confirmation');
        Bus::assertNothingDispatched();
        $en_attente = session('pending_generation');
        $this->assertSame(self::VENDREDI, $en_attente['date_debut']);
        $this->assertSame(2, $en_attente['semaines']);
        $this->assertSame(4, $en_attente['nb_total']);
        $this->assertCount(2, $en_attente['semaines_affectees'], 'deux semaines concernées');
        $this->assertSame(2, $en_attente['semaines_affectees'][0]['nb_creneaux']);
    }

    public function test_la_confirmation_regenere_et_efface_l_etat_en_attente(): void
    {
        $this->generer();
        $idsAvant = Creneau::orderBy('id')->pluck('id')->all();
        $this->generer();
        $this->assertNotNull(session('pending_generation'));

        $this->generer(['confirmed' => '1'])->assertSessionHas('success');

        $this->assertNull(session('pending_generation'));
        $this->assertSame(4, Creneau::count());
        $this->assertEmpty(array_intersect($idsAvant, Creneau::pluck('id')->all()), 'tous les créneaux ont été recréés');
    }

    public function test_seul_confirmed_egal_1_vaut_confirmation(): void
    {
        $this->generer();

        foreach (['0', 'true', 'oui', ''] as $valeur) {
            $this->generer(['confirmed' => $valeur])->assertSessionHas('warning');
        }
        $this->assertSame(4, Creneau::count());
    }

    public function test_pas_de_confirmation_demandee_quand_les_creneaux_existants_sont_avant_la_date_de_debut(): void
    {
        $this->generer(['date_debut' => self::VENDREDI, 'semaines' => 1]);

        $this->generer(['date_debut' => '2026-09-25', 'semaines' => 1])
            ->assertSessionMissing('pending_generation')
            ->assertSessionHas('success');

        $this->assertSame(4, Creneau::count(), 'la semaine du 18/09 est conservée, celle du 25/09 ajoutée');
    }

    // ── Prévisualisation ──────────────────────────────────────────────────

    public function test_previsualiser_ne_modifie_rien(): void
    {
        $this->post(route('planning.preview'), ['date_debut' => self::VENDREDI, 'semaines' => 2])
            ->assertOk()
            ->assertViewIs('planning.preview')
            ->assertViewHas('dateDebut', self::VENDREDI)
            ->assertViewHas('semaines', 2)
            ->assertViewHas('propositions', fn(array $p) => count($p['creneaux']) === 4);

        $this->assertSame(0, Creneau::count());
        Bus::assertNothingDispatched();
    }

    public function test_previsualiser_par_dessus_un_planning_existant_le_laisse_intact(): void
    {
        $this->generer();
        $avant = CreneauTache::orderBy('id_planning')->orderBy('id_tache')->get(['id_planning', 'id_tache', 'id_personne'])->toArray();

        $this->post(route('planning.preview'), ['date_debut' => self::VENDREDI, 'semaines' => 2])->assertOk();

        $this->assertSame($avant, CreneauTache::orderBy('id_planning')->orderBy('id_tache')->get(['id_planning', 'id_tache', 'id_personne'])->toArray());
    }

    public function test_previsualiser_sans_personne_active_affiche_l_erreur(): void
    {
        Personne::query()->update(['statut' => 'Suspendu']);

        $this->post(route('planning.preview'), ['date_debut' => self::VENDREDI, 'semaines' => 1])
            ->assertRedirect(route('planning.generate.form'))
            ->assertSessionHas('error', 'Erreur lors de la prévisualisation : Aucune personne active dans le planning.');
    }

    // ── Rollback ──────────────────────────────────────────────────────────

    public function test_rollback_sans_session_active_est_refuse(): void
    {
        $this->post(route('planning.rollback'))
            ->assertRedirect(route('planning.generate.form'))
            ->assertSessionHas('error', 'Aucune session de rollback active.');
    }

    public function test_rollback_total_supprime_les_creneaux_generes_et_previent_google(): void
    {
        $this->generer();
        Bus::fake();

        $this->post(route('planning.rollback'))
            ->assertRedirect(route('planning.generate.form'))
            ->assertSessionHas('success', 'Annulation totale : 4 créneaux supprimés.');

        $this->assertSame(0, Creneau::count());
        $this->assertSame(0, CreneauTache::count(), 'les lignes de tâches partent en cascade');
        $this->assertNull(session('last_generated_creneaux'));
        $this->assertSame(4, Bus::dispatchedSync(SynchroniserGoogleCalendar::class)->count(), 'un appel Google SYNCHRONE par créneau supprimé');
        $this->assertSame(1, AuditLog::where('module', 'planning')->where('action', 'delete')->count());
    }

    /**
     * CARACTÉRISATION (perte de données) : « rollback » ne RESTAURE rien. Il supprime les
     * créneaux qui viennent d'être générés ; or générer par-dessus un planning existant
     * (après confirmation) a déjà supprimé l'ancien. Après « Annuler » il ne reste donc ni
     * le nouveau planning ni l'ancien : la période est vide. Le message dit « Annulation
     * totale », ce qui laisse croire à un retour à l'état précédent.
     */
    public function test_le_rollback_ne_restaure_pas_l_ancien_planning(): void
    {
        $this->generer(['semaines' => 1]);
        $ancienne = CreneauTache::where('id_planning', Creneau::where('date', self::VENDREDI)->value('id'))->pluck('id_personne', 'id_tache')->all();
        $this->assertNotEmpty($ancienne);
        $this->generer(['semaines' => 1, 'confirmed' => '1']);

        $this->post(route('planning.rollback'));

        $this->assertSame(0, Creneau::count(), 'ni le nouveau, ni l\'ancien planning');
    }

    public function test_rollback_partiel_ne_supprime_que_les_semaines_choisies(): void
    {
        $this->generer();
        $semaine1 = Creneau::whereIn('date', [self::VENDREDI, '2026-09-19'])->pluck('id')->all();
        $semaine2 = Creneau::whereIn('date', ['2026-09-25', '2026-09-26'])->pluck('id')->all();
        Bus::fake();

        $this->post(route('planning.rollback'), [
            'rollback_type' => 'partial',
            'selected_weeks' => ['S38'],
            'creneau_ids' => ['S38' => $semaine1, 'S39' => $semaine2],
        ])->assertSessionHas('success', 'Annulation partielle : 2 créneau(x) supprimé(s).');

        $this->assertSame($semaine2, Creneau::orderBy('date')->pluck('id')->all(), 'seule la semaine cochée est supprimée');
        $this->assertSame($semaine2, array_column(session('last_generated_creneaux'), 'id'), 'le reste du rollback reste possible');
    }

    public function test_rollback_partiel_ignore_les_creneaux_qui_ne_viennent_pas_de_la_generation(): void
    {
        $this->generer();
        $etranger = $this->creneauLe('2027-01-01'); // existe, mais pas dans l'instantané de la session

        $this->post(route('planning.rollback'), [
            'rollback_type' => 'partial',
            'selected_weeks' => ['S1'],
            'creneau_ids' => ['S1' => [$etranger->id]],
        ])->assertSessionHas('success', 'Annulation partielle : 0 créneau(x) supprimé(s).');

        $this->assertNotNull(Creneau::find($etranger->id), 'impossible de supprimer un créneau hors de la dernière génération');
        $this->assertSame(5, Creneau::count());
    }

    public function test_rollback_partiel_sans_selection_est_refuse(): void
    {
        $this->generer();

        $this->from(route('planning.generate.form'))->post(route('planning.rollback'), ['rollback_type' => 'partial'])
            ->assertSessionHas('error', 'Aucun créneau sélectionné pour l\'annulation.');

        $this->assertSame(4, Creneau::count());
    }

    public function test_rollback_partiel_de_tout_vide_la_session(): void
    {
        $this->generer(['semaines' => 1]);
        $ids = Creneau::pluck('id')->all();

        $this->post(route('planning.rollback'), ['rollback_type' => 'partial', 'selected_weeks' => ['S38'], 'creneau_ids' => ['S38' => $ids]]);

        $this->assertSame(0, Creneau::count());
        $this->assertNull(session('last_generated_creneaux'));
    }

    public function test_conserver_le_planning_ferme_la_session_de_rollback_sans_rien_supprimer(): void
    {
        $this->generer();

        $this->post(route('planning.rollback.dismiss'))
            ->assertRedirect(route('planning.generate.form'))
            ->assertSessionHas('success', 'Planning conservé. Session de rollback fermée.');

        $this->assertNull(session('last_generated_creneaux'));
        $this->assertSame(4, Creneau::count());
        $this->post(route('planning.rollback'))->assertSessionHas('error', 'Aucune session de rollback active.');
    }

    // ── Droits ────────────────────────────────────────────────────────────

    public function test_un_membre_ne_peut_ni_generer_ni_annuler(): void
    {
        $this->post(route('logout'));
        $this->connecterEn('membre');

        $this->generer()->assertRedirect(route('planning.index'));
        $this->post(route('planning.rollback'))->assertRedirect(route('planning.index'));

        $this->assertSame(0, Creneau::count());
        Bus::assertNothingDispatched();
    }
}
