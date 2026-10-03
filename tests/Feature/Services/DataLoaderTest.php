<?php
// tests/Feature/Services/DataLoaderTest.php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Absence;
use App\Models\Evenement;
use App\Models\Personne;
use App\Models\Restriction;
use App\Models\Tache;
use App\Services\DataLoader;
use Carbon\Carbon;
use Database\Factories\TacheFactory;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class DataLoaderTest extends TestCase
{
    use RefreshesBothDatabases;
    use CreeDonneesPlanning;

    private function loader(): DataLoader
    {
        return new DataLoader();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── initializeCountersFromHistory : la borne $avant ───────────────────

    public function test_l_historique_s_arrete_strictement_avant_la_date_de_coupure(): void
    {
        $taches = $this->tachesDeRotation();
        [$p] = $this->personnesValidees(1);
        $this->assigner($p, '2026-09-11', 'amana_food');   // avant la coupure
        $this->assigner($p, '2026-09-18', 'amana_food');   // PILE à la coupure : exclu
        $this->assigner($p, '2026-09-25', 'entree');       // après : exclu

        $ctx = $this->loader()->initializeCountersFromHistory(
            collect([$p]),
            collect($taches)->values(),
            Carbon::parse('2026-09-18'),
        );

        $cle = $this->cle($p);
        $this->assertSame(1, $ctx['totalTasks'][$cle], 'seule la ligne du 11/09 compte');
        $this->assertSame(1, $ctx['amanaFoodCycles'][$cle], 'un seul amana_food historique');
        $this->assertSame(1, $ctx['taskHistory']['amana_food'][$cle]);
        $this->assertSame(0, $ctx['taskHistory']['entree'][$cle], 'l\'entrée du 25/09 (après la coupure) ne fuit pas dans l\'historique');
        $this->assertSame('2026-09-11', $ctx['lastWorkDate'][$cle]->toDateString(), 'dernier travail = avant la coupure, pas le 25/09');
    }

    public function test_sans_date_de_coupure_tout_l_historique_est_compte(): void
    {
        $taches = $this->tachesDeRotation();
        [$p] = $this->personnesValidees(1);
        $this->assigner($p, '2026-09-11', 'amana_food');
        $this->assigner($p, '2026-09-25', 'entree');

        $ctx = $this->loader()->initializeCountersFromHistory(collect([$p]), collect($taches)->values(), null);

        $this->assertSame(2, $ctx['totalTasks'][$this->cle($p)]);
        $this->assertSame('2026-09-25', $ctx['lastWorkDate'][$this->cle($p)]->toDateString());
    }

    public function test_la_veille_de_la_coupure_est_comptee_et_le_lendemain_ne_l_est_pas(): void
    {
        $taches = $this->tachesDeRotation();
        [$p] = $this->personnesValidees(1);
        $this->assigner($p, '2026-09-17', 'salle');
        $this->assigner($p, '2026-09-19', 'mektaba');

        $ctx = $this->loader()->initializeCountersFromHistory(collect([$p]), collect($taches)->values(), Carbon::parse('2026-09-18'));

        $this->assertSame(1, $ctx['taskHistory']['salle'][$this->cle($p)]);
        $this->assertSame(0, $ctx['taskHistory']['mektaba'][$this->cle($p)]);
    }

    public function test_le_dernier_travail_est_la_date_max_quel_que_soit_l_ordre_d_insertion(): void
    {
        $taches = $this->tachesDeRotation();
        [$p] = $this->personnesValidees(1);
        $this->assigner($p, '2026-09-25', 'entree'); // inséré en premier mais plus récent
        $this->assigner($p, '2026-09-11', 'salle');

        $ctx = $this->loader()->initializeCountersFromHistory(collect([$p]), collect($taches)->values(), null);

        $this->assertSame('2026-09-25', $ctx['lastWorkDate'][$this->cle($p)]->toDateString());
    }

    public function test_les_taches_non_assignees_ne_comptent_pour_personne(): void
    {
        $taches = $this->tachesDeRotation();
        [$p] = $this->personnesValidees(1);
        $this->assigner(null, '2026-09-11', 'entree');

        $ctx = $this->loader()->initializeCountersFromHistory(collect([$p]), collect($taches)->values(), null);

        $this->assertSame(0, $ctx['totalTasks'][$this->cle($p)]);
        $this->assertNull($ctx['lastWorkDate'][$this->cle($p)]);
    }

    public function test_les_compteurs_partent_de_zero_pour_toute_personne_et_toute_tache(): void
    {
        $taches = $this->tachesDeRotation();
        [$a, $b] = $this->personnesValidees(2);

        $ctx = $this->loader()->initializeCountersFromHistory(collect([$a, $b]), collect($taches)->values(), null);

        foreach ([$a, $b] as $p) {
            $this->assertSame(0, $ctx['totalTasks'][$this->cle($p)]);
            $this->assertNull($ctx['lastWorkDate'][$this->cle($p)]);
            foreach (self::CODES_TACHES as $code) {
                $this->assertSame(0, $ctx['taskHistory'][$code][$this->cle($p)], $code);
            }
        }
    }

    public function test_les_lignes_de_l_historique_sont_attribuees_a_la_bonne_personne_et_a_la_bonne_tache(): void
    {
        $taches = $this->tachesDeRotation();
        [$a, $b] = $this->personnesValidees(2);
        $this->assigner($a, '2026-09-11', 'entree');
        $this->assigner($b, '2026-09-11', 'salle');
        $this->assigner($a, '2026-09-12', 'entree');

        $ctx = $this->loader()->initializeCountersFromHistory(collect([$a, $b]), collect($taches)->values(), null);

        $this->assertSame(2, $ctx['taskHistory']['entree'][$this->cle($a)]);
        $this->assertSame(0, $ctx['taskHistory']['entree'][$this->cle($b)]);
        $this->assertSame(1, $ctx['taskHistory']['salle'][$this->cle($b)]);
        $this->assertSame(2, $ctx['totalTasks'][$this->cle($a)]);
        $this->assertSame(1, $ctx['totalTasks'][$this->cle($b)]);
    }

    // ── initializeCountersFromHistory : cycle amana_food ──────────────────

    public function test_le_cycle_amana_food_exclut_ceux_qui_ne_peuvent_le_faire_aucun_des_deux_jours(): void
    {
        $taches = $this->tachesDeRotation();
        $amana = $taches['amana_food'];
        [$libre, $ceSamediSeulement, $jamais] = $this->personnesValidees(3);
        Restriction::factory()->pour($ceSamediSeulement, $amana)->le('Vendredi')->create();
        Restriction::factory()->pour($jamais, $amana)->le('Vendredi')->create();
        Restriction::factory()->pour($jamais, $amana)->le('Samedi')->create();

        $ctx = $this->loader()->initializeCountersFromHistory(
            collect([$libre, $ceSamediSeulement, $jamais]),
            collect($taches)->values(),
            null,
        );

        $this->assertSame(
            [$this->cle($libre) => 0, $this->cle($ceSamediSeulement) => 0],
            $ctx['amanaFoodCycles'],
            'pouvoir le faire UN des deux jours suffit ; aucun des deux → hors cycle',
        );
    }

    public function test_sans_tache_amana_food_active_pas_de_cycle(): void
    {
        [$p] = $this->personnesValidees(1);
        $seulement = collect([TacheFactory::pourCode('entree')]);

        $ctx = $this->loader()->initializeCountersFromHistory(collect([$p]), $seulement, null);

        $this->assertSame([], $ctx['amanaFoodCycles']);
    }

    public function test_le_cycle_est_le_nombre_d_amana_food_historiques(): void
    {
        $taches = $this->tachesDeRotation();
        [$p] = $this->personnesValidees(1);
        $this->assigner($p, '2026-09-04', 'amana_food');
        $this->assigner($p, '2026-09-11', 'amana_food');
        $this->assigner($p, '2026-09-11', 'entree');

        $ctx = $this->loader()->initializeCountersFromHistory(collect([$p]), collect($taches)->values(), null);

        $this->assertSame(2, $ctx['amanaFoodCycles'][$this->cle($p)]);
    }

    // ── initializeContext ─────────────────────────────────────────────────

    public function test_initialize_context_utilise_le_premier_vendredi_comme_coupure(): void
    {
        $taches = $this->tachesDeRotation();
        [$p] = $this->personnesValidees(1);
        $this->assigner($p, '2026-09-11', 'entree');
        $this->assigner($p, '2026-09-18', 'salle'); // 2026-09-18 est le premier vendredi ≥ 2026-09-15

        $ctx = $this->loader()->initializeContext('2026-09-15');

        $this->assertSame(1, $ctx['totalTasks'][$this->cle($p)], 'le 18/09 (le vendredi de départ) est exclu');
        foreach (['personnes', 'taches', 'absences', 'evenements', 'amanaFoodCycles', 'lastWorkDate', 'totalTasks', 'taskHistory', 'personOptions'] as $cle) {
            $this->assertArrayHasKey($cle, $ctx);
        }
    }

    public function test_find_premier_vendredi(): void
    {
        $this->assertSame('2026-09-18', $this->loader()->findPremierVendredi('2026-09-18')->toDateString());
        $this->assertSame('2026-09-18', $this->loader()->findPremierVendredi('2026-09-12')->toDateString());
        $this->assertSame('2026-09-25', $this->loader()->findPremierVendredi('2026-09-19')->toDateString());
    }

    // ── calculatePersonOptions ────────────────────────────────────────────

    public function test_les_options_comptent_les_couples_tache_jour_autorises(): void
    {
        $taches = $this->tachesDeRotation();
        [$libre, $restreinte, $benevole] = $this->personnesValidees(3);
        Restriction::factory()->pour($restreinte, $taches['entree'])->le('Vendredi')->create();
        Restriction::factory()->pour($restreinte, $taches['salle'])->le('Vendredi')->create();
        Restriction::factory()->pour($restreinte, $taches['salle'])->le('Samedi')->create();

        $options = $this->loader()->calculatePersonOptions(collect([$libre, $restreinte]), collect($taches)->values());

        $this->assertSame(10, $options[$this->cle($libre)], '5 tâches × 2 jours');
        $this->assertSame(7, $options[$this->cle($restreinte)], '10 − entree(V) − salle(V) − salle(S)');
    }

    // ── chargeurs ─────────────────────────────────────────────────────────

    public function test_seules_les_personnes_validees_sont_chargees_triees_par_nom(): void
    {
        $zoe = Personne::factory()->create(['nom' => 'Zoe', 'prenom' => 'Z']);
        $abel = Personne::factory()->create(['nom' => 'Abel', 'prenom' => 'A']);
        Personne::factory()->enAttente()->create(['nom' => 'Attente']);
        Personne::factory()->suspendu()->create(['nom' => 'Suspendue']);
        Personne::factory()->archive()->create(['nom' => 'Archivee']);

        $noms = $this->loader()->loadActivePersonnes()->pluck('nom')->all();

        // Le modèle met le nom en majuscules : on compare aux valeurs stockées, pas à la saisie.
        $this->assertSame([$abel->nom, $zoe->nom], $noms);
    }

    public function test_seules_les_taches_actives_sont_chargees(): void
    {
        $this->tachesDeRotation();
        Tache::factory()->inactive()->create(['code' => 'desactivee']);

        $codes = $this->loader()->loadTachesActives()->pluck('code')->all();

        $this->assertSame(self::CODES_TACHES, $codes, 'ordre = ordre d\'id ; annulation_cours (inactive, posée par migration) et la tâche désactivée absentes');
    }

    public function test_les_absences_chargees_couvrent_les_douze_derniers_mois(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 10:00:00'));
        [$p] = $this->personnesValidees(1);
        $recente = Absence::factory()->pour($p)->du('2026-09-01', '2026-09-03')->create();
        $future = Absence::factory()->pour($p)->du('2026-10-01', '2026-10-03')->create();
        $limite = Absence::factory()->pour($p)->du('2025-09-14', '2025-09-14')->create();     // fin = il y a exactement 1 an
        $tropAncienne = Absence::factory()->pour($p)->du('2025-09-10', '2025-09-13')->create(); // fin < il y a 1 an

        $ids = $this->loader()->loadAbsences()->pluck('id')->all();

        $this->assertContains($recente->id, $ids);
        $this->assertContains($future->id, $ids);
        $this->assertContains($limite->id, $ids, 'borne inclusive');
        $this->assertNotContains($tropAncienne->id, $ids);
    }

    public function test_les_absences_chargees_portent_leur_personne(): void
    {
        [$p] = $this->personnesValidees(1);
        Absence::factory()->pour($p)->du(now()->toDateString(), now()->addDay()->toDateString())->create();

        $absence = $this->loader()->loadAbsences()->first();

        $this->assertTrue($absence->relationLoaded('personne'));
        $this->assertSame($p->id, $absence->personne->id);
    }

    public function test_seuls_les_evenements_en_cours_ou_futurs_sont_charges(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 10:00:00'));
        $passe = Evenement::factory()->du('2026-09-01', '2026-09-13')->create();
        $finAujourdhui = Evenement::factory()->du('2026-09-10', '2026-09-14')->create();
        $futur = Evenement::factory()->du('2026-10-01', '2026-10-02')->create();

        $ids = $this->loader()->loadEvenements()->pluck('id')->all();

        $this->assertNotContains($passe->id, $ids);
        $this->assertContains($finAujourdhui->id, $ids, 'un événement qui finit aujourd\'hui est encore en cours');
        $this->assertContains($futur->id, $ids);
    }
}
