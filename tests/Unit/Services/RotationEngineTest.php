<?php
// tests/Unit/Services/RotationEngineTest.php
//
// Logique pure, sans base de données ni conteneur Laravel (même esprit que
// DateHelperTest). Les méthodes privées (assignAmanaFood, assignOtherTask,
// assignCours) sont exercées par leur seul point d'entrée public, assignDay(),
// en ne fournissant au contexte que les tâches concernées : une tâche absente
// de $context['taches'] donne null pour assignOtherTask/assignCours, et
// amana_food est neutralisée en laissant amanaFoodCycles vide.
// Seule calculerJoursRepos() est appelée par réflexion : c'est une fonction
// pure dont les cas limites (absences, plafond) sont trop fins pour passer
// proprement par un score.
//
// Repères : 2026-09-18 est un vendredi, 2026-09-19 un samedi.

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DataLoader;
use App\Services\RotationEngine;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Support\PersonneDeTest;

class RotationEngineTest extends TestCase
{
    private const IDS = ['amana_food' => 1, 'entree' => 2, 'mektaba' => 3, 'salle' => 4, 'cours' => 5];
    private const VENDREDI = '2026-09-18';

    // ── Helpers ───────────────────────────────────────────────────────────

    private function moteur(): RotationEngine
    {
        return new RotationEngine(new DataLoader());
    }

    /**
     * @param list<PersonneDeTest> $personnes
     * @param list<string>         $codesTaches tâches présentes dans le contexte
     */
    private function contexte(array $personnes, array $codesTaches, array $surcharges = []): array
    {
        $taches = collect($codesTaches)->map(fn(string $code) => (object) ['id' => self::IDS[$code], 'code' => $code]);

        return array_replace([
            'personnes' => collect($personnes),
            'taches' => $taches,
            'absences' => collect(),
            'amanaFoodCycles' => [],
            'lastWorkDate' => [],
            'totalTasks' => [],
            'taskHistory' => [],
            'personOptions' => [],
        ], $surcharges);
    }

    private function jour(array &$contexte, string $date = self::VENDREDI, string $jour = 'Vendredi'): array
    {
        return $this->moteur()->assignDay($jour, Carbon::parse($date), $contexte);
    }

    private function absence(PersonneDeTest $personne, string $debut, string $fin): object
    {
        return (object) [
            'personne' => $personne,
            'date_debut' => Carbon::parse($debut),
            'date_fin' => Carbon::parse($fin),
        ];
    }

    private function joursRepos(string $lastWork, string $date, PersonneDeTest $personne, array $absences = []): int
    {
        $methode = new ReflectionMethod(RotationEngine::class, 'calculerJoursRepos');

        return $methode->invoke(
            $this->moteur(),
            Carbon::parse($lastWork),
            Carbon::parse($date),
            $personne->cle(),
            new Collection($absences),
        );
    }

    // ── assignDay : structure ─────────────────────────────────────────────

    public function test_assign_day_retourne_les_cinq_taches_dans_l_ordre(): void
    {
        $contexte = $this->contexte([], array_keys(self::IDS));

        $this->assertSame(
            ['amana_food', 'entree', 'mektaba', 'salle', 'cours'],
            array_keys($this->jour($contexte)),
        );
    }

    public function test_une_personne_n_a_qu_une_tache_parmi_amana_food_entree_mektaba_salle(): void
    {
        $gens = collect(range(1, 6))->map(fn($i) => new PersonneDeTest("P{$i}"))->all();
        $cycles = collect($gens)->mapWithKeys(fn($p) => [$p->cle() => 0])->all();
        $contexte = $this->contexte($gens, ['amana_food', 'entree', 'mektaba', 'salle'], ['amanaFoodCycles' => $cycles]);

        $res = $this->jour($contexte);
        $quatre = [$res['amana_food'], $res['entree'], $res['mektaba'], $res['salle']];

        $this->assertNotContains(null, $quatre);
        $this->assertCount(4, array_unique($quatre), 'quatre personnes différentes');
    }

    // ── assignAmanaFood ───────────────────────────────────────────────────

    public function test_amana_food_choisit_le_cycle_le_plus_bas_et_l_incremente(): void
    {
        [$a, $b, $c] = [new PersonneDeTest('A'), new PersonneDeTest('B'), new PersonneDeTest('C')];
        $contexte = $this->contexte([$a, $b, $c], ['amana_food'], [
            'amanaFoodCycles' => [$a->cle() => 1, $b->cle() => 0, $c->cle() => 1],
        ]);

        $res = $this->jour($contexte);

        $this->assertSame($b->cle(), $res['amana_food']);
        $this->assertSame(1, $contexte['amanaFoodCycles'][$b->cle()], 'le cycle du choisi passe de 0 à 1');
        $this->assertSame(1, $contexte['amanaFoodCycles'][$a->cle()], 'les autres ne bougent pas');
    }

    public function test_amana_food_le_cycle_est_global_vendredi_et_samedi(): void
    {
        [$a, $b] = [new PersonneDeTest('A'), new PersonneDeTest('B')];
        $contexte = $this->contexte([$a, $b], ['amana_food'], [
            'amanaFoodCycles' => [$a->cle() => 0, $b->cle() => 0],
        ]);

        $vendredi = $this->jour($contexte, '2026-09-18', 'Vendredi')['amana_food'];
        $samedi = $this->jour($contexte, '2026-09-19', 'Samedi')['amana_food'];

        $this->assertSame($a->cle(), $vendredi);
        $this->assertSame($b->cle(), $samedi, 'le samedi enchaîne sur le même cycle, il ne repart pas de zéro');
    }

    public function test_amana_food_a_cycle_egal_le_plus_long_repos_gagne(): void
    {
        [$a, $b] = [new PersonneDeTest('A'), new PersonneDeTest('B')];
        $contexte = $this->contexte([$a, $b], ['amana_food'], [
            'amanaFoodCycles' => [$a->cle() => 2, $b->cle() => 2],
            'lastWorkDate' => [$a->cle() => '2026-09-11', $b->cle() => '2026-09-04'], // 7 j vs 14 j
        ]);

        $this->assertSame($b->cle(), $this->jour($contexte)['amana_food']);
    }

    public function test_amana_food_a_cycle_egal_quelqu_un_qui_n_a_jamais_travaille_passe_en_premier(): void
    {
        [$a, $b] = [new PersonneDeTest('A'), new PersonneDeTest('B')];
        $contexte = $this->contexte([$a, $b], ['amana_food'], [
            'amanaFoodCycles' => [$a->cle() => 0, $b->cle() => 0],
            // 260 jours de repos pour A ; « jamais travaillé » compte pour 999 jours.
            'lastWorkDate' => [$a->cle() => '2026-01-01', $b->cle() => null],
        ]);

        $this->assertSame($b->cle(), $this->jour($contexte)['amana_food']);
    }

    public function test_amana_food_ignore_les_personnes_hors_du_cycle(): void
    {
        [$dansLeCycle, $horsCycle] = [new PersonneDeTest('Dedans'), new PersonneDeTest('Dehors')];
        $contexte = $this->contexte([$horsCycle, $dansLeCycle], ['amana_food'], [
            'amanaFoodCycles' => [$dansLeCycle->cle() => 5],
        ]);

        $this->assertSame($dansLeCycle->cle(), $this->jour($contexte)['amana_food']);
    }

    public function test_amana_food_sans_aucun_eligible_donne_null(): void
    {
        $contexte = $this->contexte([new PersonneDeTest('A')], ['amana_food'], ['amanaFoodCycles' => []]);

        $this->assertNull($this->jour($contexte)['amana_food']);
    }

    public function test_amana_food_ne_choisit_pas_quelqu_un_dont_la_rotation_n_a_pas_commence(): void
    {
        $futur = new PersonneDeTest('Futur', 'Test', [], '2026-10-01');
        $contexte = $this->contexte([$futur], ['amana_food'], ['amanaFoodCycles' => [$futur->cle() => 0]]);

        $this->assertNull($this->jour($contexte)['amana_food']);
    }

    public function test_amana_food_respecte_les_restrictions_du_jour(): void
    {
        $pasLeVendredi = new PersonneDeTest('A', 'Test', [self::IDS['amana_food'] => ['Vendredi']]);
        $contexte = $this->contexte([$pasLeVendredi], ['amana_food'], ['amanaFoodCycles' => [$pasLeVendredi->cle() => 0]]);

        $this->assertNull($this->jour($contexte, '2026-09-18', 'Vendredi')['amana_food']);
        $this->assertSame($pasLeVendredi->cle(), $this->jour($contexte, '2026-09-19', 'Samedi')['amana_food']);
    }

    public function test_amana_food_ne_choisit_pas_quelqu_un_d_absent_ce_jour_la_bornes_incluses(): void
    {
        $a = new PersonneDeTest('A');
        $absences = collect([$this->absence($a, '2026-09-18', '2026-09-20')]);
        $base = ['amanaFoodCycles' => [$a->cle() => 0], 'absences' => $absences];

        foreach (['2026-09-18', '2026-09-19', '2026-09-20'] as $jourAbsent) {
            $contexte = $this->contexte([$a], ['amana_food'], $base);
            $this->assertNull($this->jour($contexte, $jourAbsent)['amana_food'], "absent le {$jourAbsent}");
        }

        $contexte = $this->contexte([$a], ['amana_food'], $base);
        $this->assertSame($a->cle(), $this->jour($contexte, '2026-09-17', 'Jeudi')['amana_food'], 'veille de l\'absence');
        $contexte = $this->contexte([$a], ['amana_food'], $base);
        $this->assertSame($a->cle(), $this->jour($contexte, '2026-09-21', 'Lundi')['amana_food'], 'lendemain de l\'absence');
    }

    /**
     * CARACTÉRISATION du comportement actuel, pas nécessairement du comportement voulu :
     * seules les personnes AU cycle minimum sont candidates. Si tout le monde au cycle
     * minimum est indisponible, la tâche reste non assignée — même quand des personnes
     * au cycle supérieur sont disponibles. À confirmer avec le métier ; si c'est un bug,
     * ce test est celui à corriger en premier.
     */
    public function test_amana_food_si_tous_les_cycles_minimum_sont_indisponibles_la_tache_reste_vide(): void
    {
        [$absent, $disponible] = [new PersonneDeTest('Absent'), new PersonneDeTest('Dispo')];
        $contexte = $this->contexte([$absent, $disponible], ['amana_food'], [
            'amanaFoodCycles' => [$absent->cle() => 0, $disponible->cle() => 1],
            'absences' => collect([$this->absence($absent, self::VENDREDI, self::VENDREDI)]),
        ]);

        $this->assertNull($this->jour($contexte)['amana_food']);
    }

    // ── assignOtherTask : formule du score ────────────────────────────────
    // score = totalTasks×10 − joursRepos + taskCount×multiplicateur ; le plus BAS gagne.

    public function test_score_moins_de_taches_totales_passe_en_premier(): void
    {
        [$a, $b] = [new PersonneDeTest('A'), new PersonneDeTest('B')];
        $contexte = $this->contexte([$a, $b], ['entree'], [
            'totalTasks' => [$a->cle() => 3, $b->cle() => 1],
            'lastWorkDate' => [$a->cle() => '2026-09-11', $b->cle() => '2026-09-11'],
        ]);

        $this->assertSame($b->cle(), $this->jour($contexte)['entree']);
    }

    public function test_score_plus_de_jours_de_repos_passe_en_premier(): void
    {
        [$a, $b] = [new PersonneDeTest('A'), new PersonneDeTest('B')];
        $contexte = $this->contexte([$a, $b], ['entree'], [
            'lastWorkDate' => [$a->cle() => '2026-09-17', $b->cle() => '2026-09-08'], // 1 j vs 10 j
        ]);

        $this->assertSame($b->cle(), $this->jour($contexte)['entree']);
    }

    public function test_score_une_tache_de_plus_pese_dix_jours_de_repos(): void
    {
        [$a, $b] = [new PersonneDeTest('A'), new PersonneDeTest('B')];
        // A : 0×10 − 9 = −9    B : 1×10 − 19 = −9  → égalité stricte évitée en donnant 1 j de plus à B
        $contexte = $this->contexte([$a, $b], ['entree'], [
            'totalTasks' => [$a->cle() => 0, $b->cle() => 1],
            'lastWorkDate' => [$a->cle() => '2026-09-09', $b->cle() => '2026-08-30'], // 9 j vs 19 j
        ]);
        $this->assertSame($a->cle(), $this->jour($contexte)['entree'], '−9 contre −9 : à égalité c\'est le premier de la liste');

        $contexte = $this->contexte([$a, $b], ['entree'], [
            'totalTasks' => [$a->cle() => 0, $b->cle() => 1],
            'lastWorkDate' => [$a->cle() => '2026-09-09', $b->cle() => '2026-08-29'], // 9 j vs 20 j → B : −10
        ]);
        $this->assertSame($b->cle(), $this->jour($contexte)['entree'], 'B gagne dès qu\'il a 1 j de repos de plus');
    }

    public function test_score_avoir_deja_fait_cette_tache_est_penalise(): void
    {
        [$a, $b] = [new PersonneDeTest('A'), new PersonneDeTest('B')];
        $contexte = $this->contexte([$a, $b], ['entree'], [
            'lastWorkDate' => [$a->cle() => '2026-09-11', $b->cle() => '2026-09-11'],
            'taskHistory' => ['entree' => [$a->cle() => 1, $b->cle() => 0]],
        ]);

        $this->assertSame($b->cle(), $this->jour($contexte)['entree']);
    }

    public function test_score_le_compteur_est_propre_a_chaque_tache(): void
    {
        [$a, $b] = [new PersonneDeTest('A'), new PersonneDeTest('B')];
        $contexte = $this->contexte([$a, $b], ['entree'], [
            'lastWorkDate' => [$a->cle() => '2026-09-11', $b->cle() => '2026-09-11'],
            'taskHistory' => ['mektaba' => [$a->cle() => 9], 'entree' => []], // A a beaucoup fait mektaba, pas entree
        ]);

        $this->assertSame($a->cle(), $this->jour($contexte)['entree']);
    }

    public function test_score_quelqu_un_qui_n_a_jamais_travaille_passe_devant_tout_le_monde(): void
    {
        [$ancien, $nouveau] = [new PersonneDeTest('Ancien'), new PersonneDeTest('Nouveau')];
        $contexte = $this->contexte([$ancien, $nouveau], ['entree'], [
            'totalTasks' => [$ancien->cle() => 0, $nouveau->cle() => 50],
            'lastWorkDate' => [$ancien->cle() => '2026-01-01', $nouveau->cle() => null],
        ]);

        $this->assertSame($nouveau->cle(), $this->jour($contexte)['entree'], '999 jours de repos : −999 écrase 50×10');
    }

    // ── assignOtherTask : paliers du multiplicateur de pénalité ───────────
    //
    // A a déjà fait la tâche une fois (taskCount = 1), B jamais. Même dernier
    // jour travaillé pour les deux (même repos) : A gagne ⇔ M < 10 × (écart de
    // totalTasks), où M = 80 (≥8 options), 60 (≥6), 40 (≥4), 20 (<4).
    // Chaque paire de lignes encadre une frontière : même écart, options
    // différentes de part et d'autre du seuil, gagnant différent.

    /** @return array<string, array{int, int, string}> */
    public static function paliersDePenalite(): array
    {
        return [
            '8 options (×80), écart 9 → A' => [8, 9, 'A'],
            '8 options (×80), écart 7 → B' => [8, 7, 'B'],
            '12 options (×80), écart 7 → B' => [12, 7, 'B'],
            '7 options (×60), écart 7 → A' => [7, 7, 'A'],
            '7 options (×60), écart 5 → B' => [7, 5, 'B'],
            '6 options (×60), écart 7 → A' => [6, 7, 'A'],
            '6 options (×60), écart 5 → B' => [6, 5, 'B'],
            '5 options (×40), écart 5 → A' => [5, 5, 'A'],
            '5 options (×40), écart 3 → B' => [5, 3, 'B'],
            '4 options (×40), écart 5 → A' => [4, 5, 'A'],
            '4 options (×40), écart 3 → B' => [4, 3, 'B'],
            '3 options (×20), écart 3 → A' => [3, 3, 'A'],
            '3 options (×20), écart 1 → B' => [3, 1, 'B'],
            '0 option (×20), écart 3 → A' => [0, 3, 'A'],
        ];
    }

    #[DataProvider('paliersDePenalite')]
    public function test_palier_du_multiplicateur_de_penalite(int $numOptions, int $ecartTotalTasks, string $gagnant): void
    {
        [$a, $b] = [new PersonneDeTest('A'), new PersonneDeTest('B')];
        $contexte = $this->contexte([$a, $b], ['entree'], [
            'totalTasks' => [$a->cle() => 0, $b->cle() => $ecartTotalTasks],
            'lastWorkDate' => [$a->cle() => '2026-09-11', $b->cle() => '2026-09-11'],
            'taskHistory' => ['entree' => [$a->cle() => 1, $b->cle() => 0]],
            'personOptions' => [$a->cle() => $numOptions, $b->cle() => 8],
        ]);

        $attendu = $gagnant === 'A' ? $a->cle() : $b->cle();
        $this->assertSame($attendu, $this->jour($contexte)['entree']);
    }

    public function test_palier_bas_le_multiplicateur_est_exactement_20(): void
    {
        // A : 0×10 − 8 + 1×M    B : 2×10 − 7    → A gagne ⇔ M − 8 < 13 ⇔ M < 21.
        // Distingue ×20 de ×30 sans dépendre d'une égalité départagée par l'ordre de la liste.
        [$a, $b] = [new PersonneDeTest('A'), new PersonneDeTest('B')];
        $contexte = $this->contexte([$a, $b], ['entree'], [
            'totalTasks' => [$a->cle() => 0, $b->cle() => 2],
            'lastWorkDate' => [$a->cle() => '2026-09-10', $b->cle() => '2026-09-11'], // 8 j vs 7 j
            'taskHistory' => ['entree' => [$a->cle() => 1, $b->cle() => 0]],
            'personOptions' => [$a->cle() => 3, $b->cle() => 8],
        ]);

        $this->assertSame($a->cle(), $this->jour($contexte)['entree']);
    }

    public function test_sans_personOptions_le_palier_par_defaut_est_celui_de_8_options(): void
    {
        [$a, $b] = [new PersonneDeTest('A'), new PersonneDeTest('B')];
        $base = [
            'lastWorkDate' => [$a->cle() => '2026-09-11', $b->cle() => '2026-09-11'],
            'taskHistory' => ['entree' => [$a->cle() => 1, $b->cle() => 0]],
        ];

        $contexte = $this->contexte([$a, $b], ['entree'], $base + ['totalTasks' => [$a->cle() => 0, $b->cle() => 7]]);
        $this->assertSame($b->cle(), $this->jour($contexte)['entree'], '×80 : écart 7 ne suffit pas');

        $contexte = $this->contexte([$a, $b], ['entree'], $base + ['totalTasks' => [$a->cle() => 0, $b->cle() => 9]]);
        $this->assertSame($a->cle(), $this->jour($contexte)['entree'], '×80 : écart 9 suffit');
    }

    // ── assignOtherTask : éligibilité et effets de bord ───────────────────

    public function test_other_task_exclut_ceux_deja_assignes_a_amana_food_le_meme_jour(): void
    {
        [$a, $b] = [new PersonneDeTest('A'), new PersonneDeTest('B')];
        $contexte = $this->contexte([$a, $b], ['amana_food', 'entree'], [
            'amanaFoodCycles' => [$a->cle() => 0, $b->cle() => 1],
        ]);

        $res = $this->jour($contexte);

        $this->assertSame($a->cle(), $res['amana_food']);
        $this->assertSame($b->cle(), $res['entree']);
    }

    public function test_other_task_reste_vide_si_tout_le_monde_est_deja_pris(): void
    {
        $seul = new PersonneDeTest('Seul');
        $contexte = $this->contexte([$seul], ['amana_food', 'entree'], ['amanaFoodCycles' => [$seul->cle() => 0]]);

        $res = $this->jour($contexte);

        $this->assertSame($seul->cle(), $res['amana_food']);
        $this->assertNull($res['entree']);
    }

    public function test_other_task_exclut_absents_restreints_et_rotation_non_commencee(): void
    {
        $absent = new PersonneDeTest('Absent');
        $restreint = new PersonneDeTest('Restreint', 'Test', [self::IDS['entree'] => ['Vendredi']]);
        $futur = new PersonneDeTest('Futur', 'Test', [], '2026-09-19');
        $ok = new PersonneDeTest('Ok');
        $contexte = $this->contexte([$absent, $restreint, $futur, $ok], ['entree'], [
            'absences' => collect([$this->absence($absent, '2026-09-10', self::VENDREDI)]),
        ]);

        $this->assertSame($ok->cle(), $this->jour($contexte)['entree']);
    }

    public function test_other_task_la_rotation_commence_le_jour_meme_de_date_debut_planning(): void
    {
        $debutPile = new PersonneDeTest('Pile', 'Test', [], self::VENDREDI);
        $contexte = $this->contexte([$debutPile], ['entree']);

        $this->assertSame($debutPile->cle(), $this->jour($contexte)['entree']);
    }

    public function test_other_task_sans_candidat_ou_sans_la_tache_donne_null(): void
    {
        $contexte = $this->contexte([], ['entree']);
        $this->assertNull($this->jour($contexte)['entree']);

        $contexte = $this->contexte([new PersonneDeTest('A')], []); // tâche « entree » absente du contexte
        $this->assertNull($this->jour($contexte)['entree']);
    }

    public function test_other_task_incremente_le_compteur_de_la_tache_choisie(): void
    {
        $a = new PersonneDeTest('A');
        $contexte = $this->contexte([$a], ['entree']);

        $this->jour($contexte);
        $this->jour($contexte, '2026-09-19', 'Samedi');

        $this->assertSame(2, $contexte['taskHistory']['entree'][$a->cle()]);
    }

    // ── calculerJoursRepos ────────────────────────────────────────────────

    public function test_jours_repos_sans_absence_est_l_ecart_brut(): void
    {
        $this->assertSame(10, $this->joursRepos('2026-09-01', '2026-09-11', new PersonneDeTest('A')));
    }

    public function test_jours_repos_retire_les_jours_d_absence(): void
    {
        $a = new PersonneDeTest('A');

        $this->assertSame(7, $this->joursRepos('2026-09-01', '2026-09-11', $a, [$this->absence($a, '2026-09-04', '2026-09-06')]));
    }

    public function test_jours_repos_additionne_plusieurs_absences(): void
    {
        $a = new PersonneDeTest('A');
        $absences = [$this->absence($a, '2026-09-03', '2026-09-04'), $this->absence($a, '2026-09-07', '2026-09-08')];

        $this->assertSame(6, $this->joursRepos('2026-09-01', '2026-09-11', $a, $absences));
    }

    public function test_jours_repos_ignore_les_absences_des_autres_et_celles_sans_personne(): void
    {
        [$a, $autre] = [new PersonneDeTest('A'), new PersonneDeTest('Autre')];
        $absences = [
            $this->absence($autre, '2026-09-02', '2026-09-09'),
            (object) ['personne' => null, 'date_debut' => Carbon::parse('2026-09-02'), 'date_fin' => Carbon::parse('2026-09-09')],
        ];

        $this->assertSame(10, $this->joursRepos('2026-09-01', '2026-09-11', $a, $absences));
    }

    public function test_jours_repos_ignore_une_absence_entierement_avant_le_dernier_travail(): void
    {
        $a = new PersonneDeTest('A');

        $this->assertSame(10, $this->joursRepos('2026-09-01', '2026-09-11', $a, [$this->absence($a, '2026-08-01', '2026-08-20')]));
    }

    public function test_jours_repos_ne_devient_jamais_negatif_meme_avec_des_absences_qui_se_chevauchent(): void
    {
        $a = new PersonneDeTest('A');
        $absences = [$this->absence($a, '2026-09-02', '2026-09-10'), $this->absence($a, '2026-09-02', '2026-09-10')];

        $this->assertSame(0, $this->joursRepos('2026-09-01', '2026-09-11', $a, $absences));
    }

    public function test_jours_repos_plafond_a_21_jours(): void
    {
        $a = new PersonneDeTest('A');

        $this->assertSame(20, $this->joursRepos('2026-09-01', '2026-09-21', $a), '20 jours : sous le plafond');
        $this->assertSame(21, $this->joursRepos('2026-09-01', '2026-09-22', $a), '21 jours : pile au plafond');
        $this->assertSame(21, $this->joursRepos('2026-09-01', '2026-09-23', $a), '22 jours : plafonné');
        $this->assertSame(21, $this->joursRepos('2026-03-01', '2026-09-23', $a), 'six mois : plafonné');
    }

    public function test_jours_repos_le_plafond_s_applique_apres_le_retrait_des_absences(): void
    {
        $a = new PersonneDeTest('A');

        // 60 jours bruts − 10 jours d'absence = 50 → plafonné à 21.
        $this->assertSame(21, $this->joursRepos('2026-07-01', '2026-08-30', $a, [$this->absence($a, '2026-07-10', '2026-07-19')]));
        // 30 jours bruts − 15 jours d'absence = 15 → sous le plafond, pas plafonné.
        $this->assertSame(15, $this->joursRepos('2026-08-01', '2026-08-31', $a, [$this->absence($a, '2026-08-05', '2026-08-19')]));
    }

    public function test_un_long_retour_d_absence_ne_donne_pas_un_avantage_disproportionne(): void
    {
        // A revient après une longue absence, B n'a eu que du repos ordinaire (21 j).
        // Sans exclusion des absences + plafond, A aurait ~120 j de repos et écraserait B.
        [$a, $b] = [new PersonneDeTest('A'), new PersonneDeTest('B')];
        $contexte = $this->contexte([$a, $b], ['entree'], [
            'totalTasks' => [$a->cle() => 0, $b->cle() => 0],
            'lastWorkDate' => [$a->cle() => '2026-05-15', $b->cle() => '2026-08-28'],
            'absences' => collect([$this->absence($a, '2026-05-20', '2026-09-10')]),
            'taskHistory' => ['entree' => [$a->cle() => 0, $b->cle() => 0]],
        ]);

        // A : 126 j bruts − 114 j d'absence = 12 j ; B : 21 j → B est plus prioritaire.
        $this->assertSame($b->cle(), $this->jour($contexte)['entree']);
    }

    // ── assignCours : l'exception documentée ──────────────────────────────
    // Ne PAS « corriger » ces comportements pour les aligner sur les autres
    // tâches : voir le docblock de assignCours().

    public function test_cours_prend_le_premier_eligible_sans_scoring(): void
    {
        [$premier, $second] = [new PersonneDeTest('Premier'), new PersonneDeTest('Second')];
        $contexte = $this->contexte([$premier, $second], ['cours'], [
            // Avec le scoring de assignOtherTask, Second gagnerait largement.
            'totalTasks' => [$premier->cle() => 100, $second->cle() => 0],
            'lastWorkDate' => [$premier->cle() => '2026-09-17', $second->cle() => null],
            'taskHistory' => ['cours' => [$premier->cle() => 50, $second->cle() => 0]],
        ]);

        $this->assertSame($premier->cle(), $this->jour($contexte)['cours']);
    }

    public function test_cours_reste_sur_la_meme_personne_semaine_apres_semaine(): void
    {
        [$seule, $autre] = [new PersonneDeTest('Seule'), new PersonneDeTest('Autre', 'Test', [self::IDS['cours'] => ['Vendredi', 'Samedi']])];
        // « Autre » est interdite sur cours ; « Seule » est la seule autorisée.
        $contexte = $this->contexte([$autre, $seule], ['cours']);

        foreach (range(0, 9) as $semaine) {
            $date = Carbon::parse(self::VENDREDI)->addWeeks($semaine)->toDateString();
            $this->assertSame($seule->cle(), $this->jour($contexte, $date)['cours'], "semaine {$semaine}");
        }
        $this->assertSame(10, $contexte['taskHistory']['cours'][$seule->cle()]);
    }

    public function test_cours_ignore_les_personnes_deja_assignees_le_meme_jour(): void
    {
        [$a, $b] = [new PersonneDeTest('A'), new PersonneDeTest('B', 'Test', [self::IDS['cours'] => ['Vendredi']])];
        // A est autorisée sur cours (B non) ET tombe sur amana_food le même jour.
        $contexte = $this->contexte([$a, $b], ['amana_food', 'cours'], [
            'amanaFoodCycles' => [$a->cle() => 0, $b->cle() => 1],
        ]);

        $res = $this->jour($contexte);

        $this->assertSame($a->cle(), $res['amana_food']);
        $this->assertSame($a->cle(), $res['cours'], 'A cumule amana_food et cours');
    }

    public function test_cours_n_ecarte_personne_des_autres_taches(): void
    {
        // Le seul autorisé sur cours doit rester disponible pour entree/mektaba/salle.
        [$prof, $b, $c] = [new PersonneDeTest('Prof'), new PersonneDeTest('B', 'Test', [self::IDS['cours'] => ['Vendredi']]), new PersonneDeTest('C', 'Test', [self::IDS['cours'] => ['Vendredi']])];
        $contexte = $this->contexte([$prof, $b, $c], ['entree', 'mektaba', 'salle', 'cours'], [
            'lastWorkDate' => [$prof->cle() => '2026-01-01', $b->cle() => '2026-09-17', $c->cle() => '2026-09-17'],
        ]);

        $res = $this->jour($contexte);

        $this->assertSame($prof->cle(), $res['entree'], 'le plus reposé prend entree');
        $this->assertSame($prof->cle(), $res['cours'], 'et garde aussi le cours');
    }

    public function test_cours_exclut_absents_restreints_et_rotation_non_commencee(): void
    {
        $absent = new PersonneDeTest('Absent');
        $restreint = new PersonneDeTest('Restreint', 'Test', [self::IDS['cours'] => ['Vendredi']]);
        $futur = new PersonneDeTest('Futur', 'Test', [], '2026-10-01');
        $ok = new PersonneDeTest('Ok');
        $contexte = $this->contexte([$absent, $restreint, $futur, $ok], ['cours'], [
            'absences' => collect([$this->absence($absent, self::VENDREDI, self::VENDREDI)]),
        ]);

        $this->assertSame($ok->cle(), $this->jour($contexte)['cours']);
    }

    public function test_cours_non_assigne_si_personne_n_est_disponible_ou_si_la_tache_est_absente(): void
    {
        $interdit = new PersonneDeTest('A', 'Test', [self::IDS['cours'] => ['Vendredi']]);

        $contexte = $this->contexte([$interdit], ['cours']);
        $this->assertNull($this->jour($contexte)['cours']);

        $contexte = $this->contexte([new PersonneDeTest('B')], ['entree']); // pas de tâche « cours »
        $this->assertNull($this->jour($contexte)['cours']);
    }

    // ── updateContextAfterDay ─────────────────────────────────────────────

    public function test_update_context_met_a_jour_dernier_travail_et_total(): void
    {
        $contexte = ['lastWorkDate' => ['A Test' => '2026-09-01'], 'totalTasks' => ['A Test' => 2]];
        $date = Carbon::parse(self::VENDREDI);

        $this->moteur()->updateContextAfterDay(
            $contexte,
            ['amana_food' => 'A Test', 'entree' => 'B Test', 'mektaba' => null, 'cours' => 'A Test'],
            $date,
        );

        $this->assertSame('2026-09-18', $contexte['lastWorkDate']['A Test']->toDateString());
        $this->assertSame(4, $contexte['totalTasks']['A Test'], 'deux tâches ce jour-là : 2 + 2');
        $this->assertSame(1, $contexte['totalTasks']['B Test'], 'personne inconnue du contexte : part de 0');
        $this->assertCount(2, $contexte['totalTasks'], 'les tâches non assignées (null) sont ignorées');
    }

    public function test_update_context_stocke_une_copie_de_la_date(): void
    {
        $contexte = [];
        $date = Carbon::parse(self::VENDREDI);

        $this->moteur()->updateContextAfterDay($contexte, ['entree' => 'A Test'], $date);
        $date->addDays(7);

        $this->assertSame('2026-09-18', $contexte['lastWorkDate']['A Test']->toDateString(), 'modifier $date après coup ne change pas le contexte');
    }
}
