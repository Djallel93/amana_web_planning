<?php
// tests/Feature/Resources/JsonResourcesShapeTest.php
//
// La FORME des réponses JSON consommées par le front (Vue/TypeScript) : une clé renommée ou
// disparue casse l'interface sans qu'aucune erreur PHP ne le signale. Ces tests figent les
// contrats de CalendrierResource, PersonneResource, CreneauResource, BanniereResource et du
// squelette de planning.data. (BilanResource / BilanSerieResource : voir BilanFlowTest.)
//
// Repères : « aujourd'hui » = 2026-09-30 ; 2026-10-02 est un vendredi (semaine ISO 40).

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Models\CalendrierGoogle;
use App\Models\Evenement;
use App\Models\Personne;
use App\Services\SchedulerMain;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\ConnecteParRole;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class JsonResourcesShapeTest extends TestCase
{
    use RefreshesBothDatabases;
    use ConnecteParRole;
    use CreeDonneesPlanning;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->travelTo('2026-09-30 09:00:00');
        $this->creerRolesPlanning();
    }

    // ══ CalendrierResource ════════════════════════════════════════════════

    public function test_les_calendriers_sont_des_paires_id_nom_triees_par_nom_et_actives_seulement(): void
    {
        $this->connecterEn('sans_role');
        CalendrierGoogle::factory()->create(['nom' => 'Zèbre', 'calendar_id' => 'z@group.calendar.google.com']);
        CalendrierGoogle::factory()->create(['nom' => 'Abeille', 'calendar_id' => 'a@group.calendar.google.com']);
        CalendrierGoogle::factory()->inactif()->create(['nom' => 'Désactivé']);

        $this->getJson(route('calendriers.index'))->assertOk()->assertExactJson(['calendars' => [
            ['id' => 'a@group.calendar.google.com', 'name' => 'Abeille'],
            ['id' => 'z@group.calendar.google.com', 'name' => 'Zèbre'],
        ]]);
    }

    public function test_sans_calendrier_la_reponse_porte_un_message_d_erreur(): void
    {
        $this->connecterEn('membre');

        $this->getJson(route('calendriers.index'))->assertOk()->assertExactJson([
            'calendars' => [],
            'erreur' => 'Aucun calendrier Google Calendar enregistré. Un gestionnaire ou administrateur peut en ajouter depuis /parametres.',
        ]);
    }

    // ══ PersonneResource ══════════════════════════════════════════════════

    public function test_la_liste_des_personnes_actives_est_id_et_label_triee_par_nom_puis_prenom(): void
    {
        $this->connecterEn('gestionnaire', ['nom' => 'Zzz', 'prenom' => 'Chef']);
        $b = Personne::factory()->create(['nom' => 'Martin', 'prenom' => 'Zoé']);
        $a = Personne::factory()->create(['nom' => 'Martin', 'prenom' => 'Alice']);
        Personne::factory()->enAttente()->create();
        Personne::factory()->suspendu()->create();
        Personne::factory()->archive()->create();

        $reponse = $this->getJson(route('planning.edit.personnes'))->assertOk();

        $reponse->assertJsonCount(3);
        $this->assertSame(
            [['id' => $a->id, 'label' => 'Alice ' . $a->nom], ['id' => $b->id, 'label' => 'Zoé ' . $b->nom]],
            array_slice($reponse->json(), 0, 2),
            'label = « prénom nom » ; ni candidats, ni suspendus, ni archivés',
        );
        $this->assertSame(['id', 'label'], array_keys($reponse->json('0')));
    }

    // ══ planning.data : squelette ═════════════════════════════════════════

    /** Planning de 2 semaines (02/10 → 10/10) généré APRÈS la création des événements, qui y sont donc rattachés. */
    private function planningAvecEvenements(): array
    {
        $taches = $this->tachesDeRotation();
        $this->personnesValidees(6);
        $partiel = Evenement::factory()->du('2026-10-02', '2026-10-02')->bloquant($taches['entree'])->create(['nom' => 'Réunion partielle']);
        $info = Evenement::factory()->du('2026-10-01', '2026-10-31')->create(['nom' => 'Ramadan (information)']);
        $this->app->make(SchedulerMain::class)->generateSchedule('2026-10-02', 2);

        return [$taches, $partiel, $info];
    }

    public function test_le_squelette_d_une_semaine(): void
    {
        $this->planningAvecEvenements();
        $this->connecterEn('membre');

        $reponse = $this->getJson(route('planning.data'))->assertOk();

        $this->assertSame(['semaines', 'historique', 'peutEditer', 'peutAjouterPasse'], array_keys($reponse->json()));
        $this->assertSame(2, count($reponse->json('semaines')));
        $semaine = $reponse->json('semaines.0');
        $this->assertSame([
            'cle', 'numeroSemaine', 'anneeAffichage', 'moisAffichage', 'libelleSemaine', 'lundi', 'dimanche',
            'datesExistantes', 'evenementBloquantTotal', 'bannieres', 'creneaux',
        ], array_keys($semaine));
        $this->assertSame('40-2026', $semaine['cle']);
        $this->assertSame(40, $semaine['numeroSemaine']);
        $this->assertSame([2026, 10], [$semaine['anneeAffichage'], $semaine['moisAffichage']]);
        $this->assertSame('2 octobre — 3 octobre 2026', $semaine['libelleSemaine']);
        $this->assertSame(['2026-09-28', '2026-10-04'], [$semaine['lundi'], $semaine['dimanche']]);
        $this->assertSame(['2026-10-02', '2026-10-03'], $semaine['datesExistantes']);
        $this->assertSame('41-2026', $reponse->json('semaines.1.cle'), 'semaines triées par date croissante');
    }

    public function test_un_creneau_et_ses_cinq_taches(): void
    {
        $this->planningAvecEvenements();
        $this->connecterEn('membre');

        $creneau = $this->getJson(route('planning.data'))->json('semaines.0.creneaux.1'); // samedi 03/10, sans événement bloquant

        $this->assertSame(['id', 'date', 'dateLabel', 'jour', 'toutBloque', 'partielBloque', 'evenements', 'taches'], array_keys($creneau));
        $this->assertSame(['2026-10-03', '3 oct. 2026', 'Samedi', false, false], [$creneau['date'], $creneau['dateLabel'], $creneau['jour'], $creneau['toutBloque'], $creneau['partielBloque']]);
        $this->assertSame(['entree', 'mektaba', 'salle', 'amana_food', 'cours'], array_column($creneau['taches'], 'code'), 'ordre fixe des cinq tâches');
        $tache = $creneau['taches'][0];
        $this->assertSame(['code', 'tacheId', 'bloquee', 'evenementBloquant', 'personne'], array_keys($tache));
        $this->assertFalse($tache['bloquee']);
        $this->assertNull($tache['evenementBloquant']);
        $this->assertSame(['id', 'label'], array_keys($tache['personne']));
        $this->assertSame(\Database\Factories\TacheFactory::pourCode('entree')->id, $tache['tacheId']);
    }

    public function test_une_tache_bloquee_par_un_evenement_partiel(): void
    {
        $this->planningAvecEvenements();
        $this->connecterEn('membre');

        $creneau = $this->getJson(route('planning.data'))->json('semaines.0.creneaux.0'); // vendredi 02/10

        $this->assertTrue($creneau['partielBloque']);
        $this->assertFalse($creneau['toutBloque']);
        // `evenements` liste TOUS les événements rattachés au créneau, informatifs compris (ordre non garanti) ;
        // `evenementBloquant`, lui, ne cite que ceux qui bloquent des tâches.
        $this->assertEqualsCanonicalizing(['Réunion partielle', 'Ramadan (information)'], explode(', ', $creneau['evenements']));
        $entree = $creneau['taches'][0];
        $this->assertTrue($entree['bloquee']);
        $this->assertSame('Réunion partielle', $entree['evenementBloquant']);
        $this->assertNull($entree['personne'], 'tâche bloquée : personne non assignée');
        $this->assertFalse($creneau['taches'][1]['bloquee'], 'les autres tâches restent actives');
    }

    public function test_un_evenement_qui_bloque_tout_marque_le_creneau_et_la_semaine(): void
    {
        $taches = $this->tachesDeRotation();
        $this->personnesValidees(6);
        Evenement::factory()->du('2026-10-02', '2026-10-03')->bloquant(...array_values($taches))->create(['nom' => 'Fermeture']);
        $this->app->make(SchedulerMain::class)->generateSchedule('2026-10-02', 1);
        $this->connecterEn('membre');

        $semaine = $this->getJson(route('planning.data'))->json('semaines.0');

        $this->assertSame('Fermeture', $semaine['evenementBloquantTotal']);
        foreach ($semaine['creneaux'] as $creneau) {
            $this->assertTrue($creneau['toutBloque']);
            $this->assertFalse($creneau['partielBloque'], 'tout bloqué n\'est pas « partiel »');
        }
    }

    // ══ BanniereResource ══════════════════════════════════════════════════

    public function test_les_bannieres_distinguent_evenement_informatif_et_bloquant(): void
    {
        $this->planningAvecEvenements();
        $this->connecterEn('membre');

        $bannieres = $this->getJson(route('planning.data'))->json('semaines.0.bannieres');

        $this->assertSame([
            ['nom' => 'Ramadan (information)', 'dateLabel' => '1 oct. – 4 oct.', 'informatif' => true, 'tachesBloquees' => []],
            ['nom' => 'Réunion partielle', 'dateLabel' => '2 oct.', 'informatif' => false, 'tachesBloquees' => [['code' => 'entree', 'libelle' => \Database\Factories\TacheFactory::pourCode('entree')->libelle]]],
        ], $bannieres);
    }

    public function test_la_banniere_est_bornee_a_la_semaine_affichee(): void
    {
        $this->planningAvecEvenements();
        $this->connecterEn('membre');

        $semaines = $this->getJson(route('planning.data'))->json('semaines');

        $this->assertSame('1 oct. – 4 oct.', $semaines[0]['bannieres'][0]['dateLabel'], 'semaine 40 : du 1er au dimanche 4');
        $this->assertSame('5 oct. – 11 oct.', $semaines[1]['bannieres'][0]['dateLabel'], 'semaine 41 : lundi 5 → dimanche 11');
    }

    // ══ Droits d'édition annoncés au front ════════════════════════════════

    /** @return array<string, array{string, bool, bool}> */
    public static function droitsParRole(): array
    {
        return [
            'sans rôle' => ['sans_role', false, false],
            'bénévole' => ['benevole', false, false],
            'membre' => ['membre', false, false],
            'gestionnaire' => ['gestionnaire', true, false],
            'admin' => ['admin', true, true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('droitsParRole')]
    public function test_les_drapeaux_peut_editer_et_peut_ajouter_passe_suivent_le_role(string $persona, bool $edite, bool $ajoutePasse): void
    {
        $this->connecterEn($persona);

        $this->getJson(route('planning.data'))->assertOk()
            ->assertJsonPath('peutEditer', $edite)
            ->assertJsonPath('peutAjouterPasse', $ajoutePasse);
    }

    // ══ Fenêtre d'historique ══════════════════════════════════════════════

    public function test_seule_la_derniere_annee_est_chargee_sauf_demande_d_historique(): void
    {
        $this->creneauLe('2025-01-10'); // il y a plus d'un an
        $this->creneauLe('2026-09-25');
        $this->connecterEn('membre');

        $courant = $this->getJson(route('planning.data'))->json();
        $this->assertFalse($courant['historique']);
        $this->assertSame([['2026-09-25']], array_map(fn($s) => $s['datesExistantes'], $courant['semaines']));

        $complet = $this->getJson(route('planning.data', ['historique' => 1]))->json();
        $this->assertTrue($complet['historique']);
        $this->assertSame([['2025-01-10'], ['2026-09-25']], array_map(fn($s) => $s['datesExistantes'], $complet['semaines']));
    }

    public function test_sans_creneau_la_liste_des_semaines_est_vide(): void
    {
        $this->connecterEn('membre');

        $this->getJson(route('planning.data'))->assertOk()->assertJson(['semaines' => [], 'historique' => false]);
    }
}
