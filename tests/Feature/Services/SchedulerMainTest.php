<?php
// tests/Feature/Services/SchedulerMainTest.php
//
// Vrai DataLoader + vrai RotationEngine + MySQL. Chaque test tourne DÉJÀ dans
// une transaction (RefreshesBothDatabases) : generateSchedule() y ouvre sa
// propre transaction, que Laravel imbrique en SAVEPOINT. Le rollback du
// dry-run est donc observable (les lignes disparaissent), mais on ne peut pas
// distinguer « annulé par le service » de « annulé par la fin du test » en
// regardant APRÈS le test : toutes les assertions se font DANS le test, juste
// après l'appel, et on vérifie aussi que le niveau de transaction est revenu
// à sa valeur d'avant l'appel (aucune transaction laissée ouverte).
//
// Repères : 2026-10-02 est un vendredi.

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Echange;
use App\Models\Evenement;
use App\Services\SchedulerMain;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class SchedulerMainTest extends TestCase
{
    use RefreshesBothDatabases;
    use CreeDonneesPlanning;

    private const VENDREDI = '2026-10-02';

    protected function setUp(): void
    {
        parent::setUp();

        // Les dates du fichier (2026-10-02, …) doivent rester « dans le futur » : DataLoader
        // ne charge que les événements dont date_fin >= now() (Evenement::scopeFutursOuEnCours).
        $this->travelTo('2026-09-30 09:00:00');
    }

    private function scheduler(): SchedulerMain
    {
        return $this->app->make(SchedulerMain::class);
    }

    private function niveauTransaction(): int
    {
        return DB::connection(config('database.default'))->transactionLevel();
    }

    // ── Dry-run : rien de persisté ────────────────────────────────────────

    public function test_dry_run_ne_laisse_aucune_ligne_et_ferme_sa_transaction(): void
    {
        $this->tachesDeRotation();
        $this->personnesValidees(5);
        $avant = $this->niveauTransaction();

        $resultat = $this->scheduler()->generateSchedule(self::VENDREDI, 2, dryRun: true);

        $this->assertSame(0, Creneau::count(), 'aucun Creneau ne doit rester');
        $this->assertSame(0, CreneauTache::count(), 'aucun CreneauTache ne doit rester');
        $this->assertSame($avant, $this->niveauTransaction(), 'la transaction du service est refermée');
        $this->assertCount(4, $resultat['creneaux'], '2 semaines × (vendredi + samedi) proposés');
        $this->assertArrayHasKey('non_assignes', $resultat);
        $this->assertArrayNotHasKey('jours_generes', $resultat);
    }

    public function test_dry_run_sur_un_planning_existant_ne_modifie_ni_ne_supprime_rien(): void
    {
        $this->tachesDeRotation();
        [$a, $b, $c] = $this->personnesValidees(3);
        // Planning existant, volontairement peu « naturel » : A partout.
        foreach ([self::VENDREDI, '2026-10-03'] as $date) {
            foreach (self::CODES_TACHES as $code) {
                $this->assigner($a, $date, $code);
            }
        }
        $idsAvant = Creneau::orderBy('id')->pluck('id')->all();
        $lignesAvant = CreneauTache::orderBy('id_planning')->orderBy('id_tache')->get(['id_planning', 'id_tache', 'id_personne'])->toArray();

        $this->scheduler()->generateSchedule(self::VENDREDI, 1, dryRun: true);

        $this->assertSame($idsAvant, Creneau::orderBy('id')->pluck('id')->all(), 'mêmes créneaux, mêmes ids (pas supprimés/recréés)');
        $this->assertSame(
            $lignesAvant,
            CreneauTache::orderBy('id_planning')->orderBy('id_tache')->get(['id_planning', 'id_tache', 'id_personne'])->toArray(),
            'le dry-run a réaffecté des lignes puis tout annulé',
        );
    }

    public function test_dry_run_proposition_par_jour_avec_les_assignations(): void
    {
        $this->tachesDeRotation();
        $this->personnesValidees(5);

        $resultat = $this->scheduler()->generateSchedule(self::VENDREDI, 1, dryRun: true);

        $this->assertCount(2, $resultat['creneaux']);
        $premiere = array_values($resultat['creneaux'])[0];
        $this->assertIsArray($premiere);
        $this->assertNotEmpty($premiere);
    }

    // ── Génération réelle ─────────────────────────────────────────────────

    public function test_generation_cree_un_vendredi_et_un_samedi_par_semaine_avec_toutes_les_taches(): void
    {
        $this->tachesDeRotation();
        $this->personnesValidees(5);
        $avant = $this->niveauTransaction();

        $resultat = $this->scheduler()->generateSchedule(self::VENDREDI, 2);

        $this->assertSame(4, $resultat['jours_generes']);
        $this->assertSame($avant, $this->niveauTransaction());
        $this->assertSame(
            ['2026-10-02', '2026-10-03', '2026-10-09', '2026-10-10'],
            Creneau::orderBy('date')->get()->map(fn($c) => $c->date->toDateString())->all(),
        );
        $this->assertSame(20, CreneauTache::count(), '4 jours × 5 tâches');
    }

    public function test_une_date_de_debut_qui_n_est_pas_un_vendredi_demarre_au_vendredi_suivant(): void
    {
        $this->tachesDeRotation();
        $this->personnesValidees(5);

        $this->scheduler()->generateSchedule('2026-10-05', 1); // lundi

        $this->assertSame(
            ['2026-10-09', '2026-10-10'],
            Creneau::orderBy('date')->get()->map(fn($c) => $c->date->toDateString())->all(),
        );
    }

    public function test_avec_assez_de_monde_personne_n_a_deux_des_quatre_premieres_taches_le_meme_jour(): void
    {
        $this->tachesDeRotation();
        $this->personnesValidees(6);

        $this->scheduler()->generateSchedule(self::VENDREDI, 2);

        foreach (Creneau::all() as $creneau) {
            $ids = CreneauTache::where('id_planning', $creneau->id)
                ->whereIn('id_tache', collect(['amana_food', 'entree', 'mektaba', 'salle'])->map(fn($c) => $this->tachesDeRotation()[$c]->id))
                ->whereNotNull('id_personne')->pluck('id_personne')->all();

            $this->assertCount(4, $ids, $creneau->date->toDateString() . ' : les 4 tâches sont assignées');
            $this->assertCount(4, array_unique($ids), $creneau->date->toDateString() . ' : à 4 personnes différentes');
        }
    }

    public function test_amana_food_tourne_sur_tout_le_monde_avant_de_repasser_par_la_meme_personne(): void
    {
        $taches = $this->tachesDeRotation();
        $this->personnesValidees(5);

        $this->scheduler()->generateSchedule(self::VENDREDI, 2); // 4 jours, 5 personnes

        $amana = CreneauTache::where('id_tache', $taches['amana_food']->id)->pluck('id_personne')->all();
        $this->assertCount(4, $amana);
        $this->assertCount(4, array_unique($amana), 'cycle : personne ne refait amana_food avant que les cinq l\'aient fait');
    }

    public function test_seule_une_personne_prend_amana_food_et_cours_et_le_reste_est_non_assigne(): void
    {
        $taches = $this->tachesDeRotation();
        [$seule] = $this->personnesValidees(1);

        $resultat = $this->scheduler()->generateSchedule(self::VENDREDI, 1);

        $this->assertSame(6, $resultat['non_assignes'], 'entree + mektaba + salle × 2 jours');
        $this->assertSame($seule->id, $this->idPersonneDuCreneau(self::VENDREDI, 'amana_food'));
        $this->assertSame($seule->id, $this->idPersonneDuCreneau(self::VENDREDI, 'cours'), 'cours ignore les personnes déjà assignées');
        $this->assertNull($this->idPersonneDuCreneau(self::VENDREDI, 'entree'));
        $this->assertNull($this->idPersonneDuCreneau(self::VENDREDI, 'mektaba'));
    }

    public function test_les_taches_inactives_ne_sont_pas_generees(): void
    {
        $this->tachesDeRotation();
        \App\Models\Tache::factory()->inactive()->create(['code' => 'desactivee']);
        $this->personnesValidees(5);

        $this->scheduler()->generateSchedule(self::VENDREDI, 1);

        $this->assertSame(10, CreneauTache::count(), '2 jours × 5 tâches actives');
    }

    public function test_les_personnes_non_validees_ne_recoivent_rien(): void
    {
        $this->tachesDeRotation();
        $valides = $this->personnesValidees(5);
        $enAttente = \App\Models\Personne::factory()->enAttente()->create(['nom' => 'Aaa']);
        $suspendue = \App\Models\Personne::factory()->suspendu()->create(['nom' => 'Bbb']);

        $this->scheduler()->generateSchedule(self::VENDREDI, 2);

        $this->assertSame(0, CreneauTache::whereIn('id_personne', [$enAttente->id, $suspendue->id])->count());
        $this->assertGreaterThan(0, CreneauTache::whereIn('id_personne', collect($valides)->pluck('id'))->count());
    }

    public function test_sans_personne_active_le_service_leve_une_exception_et_ne_touche_a_rien(): void
    {
        $this->tachesDeRotation();
        $this->creneauLe(self::VENDREDI); // existant : ne doit pas être supprimé
        \App\Models\Personne::factory()->enAttente()->create();

        foreach ([false, true] as $dryRun) {
            try {
                $this->scheduler()->generateSchedule(self::VENDREDI, 1, $dryRun);
                $this->fail('RuntimeException attendue (dry-run : ' . var_export($dryRun, true) . ')');
            } catch (RuntimeException $e) {
                $this->assertSame('Aucune personne active dans le planning.', $e->getMessage());
            }
        }

        $this->assertSame(1, Creneau::count(), 'le créneau existant est intact');
    }

    // ── cleanExistingCreneaux ─────────────────────────────────────────────

    public function test_la_generation_supprime_puis_recree_les_creneaux_a_partir_de_la_date_de_debut(): void
    {
        $this->tachesDeRotation();
        $this->personnesValidees(5);
        $avant = $this->assigner(null, '2026-09-25', 'entree');   // AVANT la date de début : conservé
        $this->assigner(null, self::VENDREDI, 'entree');           // à la date de début : remplacé
        $idAncien = $this->creneauLe(self::VENDREDI)->id;

        $this->scheduler()->generateSchedule(self::VENDREDI, 1);

        $this->assertNotNull(Creneau::where('date', '2026-09-25')->first(), 'antérieur : conservé');
        $this->assertSame(1, CreneauTache::where('id_planning', Creneau::where('date', '2026-09-25')->value('id'))->count());
        $this->assertNull(Creneau::find($idAncien), 'l\'ancien créneau du 02/10 est supprimé (id différent après recréation)');
        $this->assertNotNull(Creneau::where('date', self::VENDREDI)->first());
        $this->assertSame(5, CreneauTache::where('id_planning', Creneau::where('date', self::VENDREDI)->value('id'))->count());
    }

    /**
     * CARACTÉRISATION : la suppression va de la date de début jusqu'à la FIN de la table,
     * pas seulement sur la fenêtre régénérée. Générer 1 semaine alors que 3 existent
     * supprime silencieusement les 2 dernières sans les recréer.
     * regenerateFromImpactedDate() évite le piège en calculant le nombre de semaines ;
     * un appel direct à generateSchedule() (PlanningController) ne le fait pas.
     */
    public function test_generer_moins_de_semaines_que_l_existant_supprime_aussi_les_semaines_suivantes(): void
    {
        $this->tachesDeRotation();
        $this->personnesValidees(5);
        $this->scheduler()->generateSchedule(self::VENDREDI, 3);
        $this->assertSame(6, Creneau::count());

        $this->scheduler()->generateSchedule(self::VENDREDI, 1);

        $this->assertSame(2, Creneau::count(), 'seule la semaine demandée reste ; les deux suivantes ont disparu');
    }

    /**
     * CARACTÉRISATION : plan_echanges a ON DELETE CASCADE vers les créneaux. Régénérer
     * supprime donc aussi les échanges (en attente ET historiques) qui les référencent.
     */
    public function test_regenerer_supprime_en_cascade_les_echanges_lies_aux_creneaux(): void
    {
        $this->tachesDeRotation();
        $this->personnesValidees(5);
        $this->scheduler()->generateSchedule(self::VENDREDI, 1);
        $echange = Echange::factory()->create([
            'id_creneau_demandeur' => Creneau::where('date', self::VENDREDI)->value('id'),
            'id_creneau_cible' => Creneau::where('date', '2026-10-03')->value('id'),
        ]);

        $this->scheduler()->generateSchedule(self::VENDREDI, 1);

        $this->assertNull(Echange::find($echange->id));
    }

    /**
     * CARACTÉRISATION : cleanExistingCreneaux() s'exécute AVANT DB::beginTransaction().
     * Si la génération échoue en cours de route, la transaction annule ce qui a été créé,
     * mais l'ancien planning est déjà supprimé — et pas restauré. Un échec laisse donc un
     * planning VIDE à partir de la date de début. (Envelopper aussi la suppression dans la
     * transaction règlerait cela ; ce test devrait alors passer à « ancien planning conservé ».)
     */
    public function test_un_echec_en_cours_de_generation_a_deja_supprime_l_ancien_planning(): void
    {
        $this->tachesDeRotation();
        $this->personnesValidees(5);
        $this->assigner(null, self::VENDREDI, 'entree');
        $idAncien = $this->creneauLe(self::VENDREDI)->id;
        $niveauAvant = $this->niveauTransaction();
        Creneau::creating(function (Creneau $c) {
            if ($c->date->toDateString() === '2026-10-03') {
                throw new RuntimeException('panne simulée au 2e jour');
            }
        });

        try {
            $this->scheduler()->generateSchedule(self::VENDREDI, 1);
            $this->fail('RuntimeException attendue');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('panne simulée', $e->getMessage());
        } finally {
            Creneau::flushEventListeners();
        }

        $this->assertNull(Creneau::find($idAncien), 'l\'ancien créneau du 02/10 a été supprimé avant l\'échec');
        $this->assertSame(0, Creneau::count(), 'et rien de nouveau n\'a survécu : planning vide');
        $this->assertSame($niveauAvant, $this->niveauTransaction(), 'la transaction du service est bien refermée');
    }

    // ── Événements bloquants ──────────────────────────────────────────────

    public function test_un_evenement_bloque_les_taches_designees_et_est_lie_au_creneau(): void
    {
        $taches = $this->tachesDeRotation();
        $this->personnesValidees(5);
        $evenement = Evenement::factory()->du(self::VENDREDI, self::VENDREDI)->bloquant($taches['entree'])->create();

        $resultat = $this->scheduler()->generateSchedule(self::VENDREDI, 1);

        $this->assertNull($this->idPersonneDuCreneau(self::VENDREDI, 'entree'), 'tâche bloquée : ligne présente, non assignée');
        $this->assertNotNull($this->idPersonneDuCreneau(self::VENDREDI, 'salle'));
        $this->assertNotNull($this->idPersonneDuCreneau('2026-10-03', 'entree'), 'le samedi n\'est pas concerné');
        $this->assertSame(1, $resultat['non_assignes']);
        $this->assertSame([$evenement->id], $this->creneauLe(self::VENDREDI)->evenements()->pluck('ref_evenements.id')->all());
        $this->assertSame([], $this->creneauLe('2026-10-03')->evenements()->pluck('ref_evenements.id')->all());
    }

    public function test_un_evenement_qui_bloque_tout_ne_donne_aucune_assignation_ce_jour_la(): void
    {
        $taches = $this->tachesDeRotation();
        $this->personnesValidees(5);
        Evenement::factory()->du(self::VENDREDI, self::VENDREDI)->bloquant(...array_values($taches))->create();

        $resultat = $this->scheduler()->generateSchedule(self::VENDREDI, 1);

        foreach (self::CODES_TACHES as $code) {
            $this->assertNull($this->idPersonneDuCreneau(self::VENDREDI, $code), $code);
        }
        $this->assertSame(5, $resultat['non_assignes'], 'le vendredi : 5 tâches non assignées ; le samedi est complet');
        $this->assertSame(5, CreneauTache::where('id_planning', $this->creneauLe(self::VENDREDI)->id)->count(), 'les lignes existent quand même');
    }

    public function test_un_evenement_bloquant_ne_compte_pas_comme_du_travail_dans_la_rotation(): void
    {
        $taches = $this->tachesDeRotation();
        $this->personnesValidees(5);
        Evenement::factory()->du(self::VENDREDI, self::VENDREDI)->bloquant(...array_values($taches))->create();

        $this->scheduler()->generateSchedule(self::VENDREDI, 1);

        // Le vendredi est vide ; le samedi démarre donc comme si c'était le premier jour :
        // amana_food revient à la première personne (ordre alphabétique).
        $this->assertNotNull($this->idPersonneDuCreneau('2026-10-03', 'amana_food'));
        $premiere = \App\Models\Personne::orderBy('nom')->first();
        $this->assertSame($premiere->id, $this->idPersonneDuCreneau('2026-10-03', 'amana_food'));
    }

    // ── Les absences sont respectées ──────────────────────────────────────

    public function test_une_personne_absente_ne_recoit_rien_pendant_son_absence(): void
    {
        $this->tachesDeRotation();
        $personnes = $this->personnesValidees(5);
        $absente = $personnes[0]; // celle qui serait servie en premier
        \App\Models\Absence::factory()->pour($absente)->du(self::VENDREDI, '2026-10-03')->create();

        $this->scheduler()->generateSchedule(self::VENDREDI, 1);

        $this->assertSame(0, CreneauTache::where('id_personne', $absente->id)
            ->whereIn('id_planning', Creneau::whereIn('date', [self::VENDREDI, '2026-10-03'])->pluck('id'))->count());
    }

    // ── regenerateFromImpactedDate : arithmétique des semaines ────────────

    /** Sous-classe qui remplace la génération (déjà testée ci-dessus) pour ne vérifier que les arguments calculés. */
    private function schedulerEspion(): SchedulerMain
    {
        return new class($this->app->make(\App\Services\DataLoader::class), $this->app->make(\App\Services\RotationEngine::class)) extends SchedulerMain {
            public array $appels = [];

            public function generateSchedule(string $dateDebut, int $semaines, bool $dryRun = false): array
            {
                $this->appels[] = [$dateDebut, $semaines, $dryRun];

                return ['jours_generes' => 0, 'non_assignes' => 0, 'duree_ms' => 0.0];
            }
        };
    }

    /** @return array<string, array{?string, string, string, int}> dernier créneau existant, date impactée, début attendu, semaines attendues */
    public static function casDeRegeneration(): array
    {
        return [
            'impact un vendredi, dernier créneau un samedi, 4 semaines' => ['2026-10-31', '2026-10-09', '2026-10-09', 4],
            'impact un samedi : on repart du vendredi' => ['2026-10-31', '2026-10-10', '2026-10-09', 4],
            'impact au tout dernier vendredi' => ['2026-10-31', '2026-10-30', '2026-10-30', 1],
            'impact au tout dernier samedi' => ['2026-10-31', '2026-10-31', '2026-10-30', 1],
            'dernier créneau un vendredi (pas de samedi)' => ['2026-10-30', '2026-10-09', '2026-10-09', 4],
            'une semaine d\'écart' => ['2026-10-10', '2026-10-02', '2026-10-02', 2],
            'après le dernier créneau : au moins 1 semaine' => ['2026-10-31', '2026-11-13', '2026-11-13', 1],
            'aucun créneau existant : 1 semaine' => [null, '2026-10-09', '2026-10-09', 1],
            // Hors domaine normal (les créneaux sont des vendredis/samedis) : une date impactée
            // ni vendredi ni samedi recule d'UN jour, pas jusqu'au vendredi. Sans conséquence,
            // generateSchedule() repartant du premier vendredi ≥ cette date.
            'date impactée un dimanche : veille (samedi)' => ['2026-10-31', '2026-10-04', '2026-10-03', 4],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('casDeRegeneration')]
    public function test_regenerer_calcule_le_debut_et_le_nombre_de_semaines(?string $dernierCreneau, string $impact, string $debutAttendu, int $semainesAttendues): void
    {
        if ($dernierCreneau !== null) {
            $this->creneauLe($dernierCreneau);
        }
        $scheduler = $this->schedulerEspion();

        $regen = $scheduler->regenerateFromImpactedDate(Carbon::parse($impact));

        $this->assertSame([[$debutAttendu, $semainesAttendues, false]], $scheduler->appels);
        $this->assertSame($debutAttendu, $regen['dateDebutRegen']);
        $this->assertSame($semainesAttendues, $regen['semaines']);
        $this->assertSame($debutAttendu, $regen['regenererDepuis']->toDateString());
    }

    public function test_regenerer_ne_modifie_pas_la_date_recue(): void
    {
        $impact = Carbon::parse('2026-10-10'); // samedi
        $this->schedulerEspion()->regenerateFromImpactedDate($impact);

        $this->assertSame('2026-10-10', $impact->toDateString(), 'l\'appelant garde sa date (copie interne)');
    }

    public function test_regenerer_range_l_instantane_de_retour_en_arriere_dans_la_session(): void
    {
        $this->creneauLe('2026-10-02');
        $this->creneauLe('2026-10-03');

        $this->schedulerEspion()->regenerateFromImpactedDate(Carbon::parse('2026-10-02'));

        $instantane = session('last_generated_creneaux');
        $this->assertSame(['2026-10-02', '2026-10-03'], array_column($instantane, 'date'));
        $this->assertSame('Semaine 40 — 2 octobre 2026', $instantane[0]['week_label']);
    }

    /**
     * CARACTÉRISATION : l'instantané couvre `semaines` semaines PLUS une semaine (dateFin =
     * début + semaines×7 + 1 jour, bornes incluses) : le vendredi et le samedi qui suivent
     * la fenêtre régénérée y figurent s'ils existent. Probablement un décalage d'une semaine ;
     * sans effet quand la régénération va jusqu'au dernier créneau (cas de regenerateFromImpactedDate).
     */
    public function test_l_instantane_depasse_d_une_semaine_la_fenetre_regeneree(): void
    {
        foreach (['2026-10-02', '2026-10-03', '2026-10-09', '2026-10-10', '2026-10-16', '2026-10-17', '2026-10-23'] as $date) {
            $this->creneauLe($date);
        }

        $dates = array_column($this->scheduler()->buildRollbackSnapshot('2026-10-02', 2), 'date');

        $this->assertSame(['2026-10-02', '2026-10-03', '2026-10-09', '2026-10-10', '2026-10-16', '2026-10-17'], $dates);
    }

    public function test_regeneration_reelle_de_bout_en_bout(): void
    {
        $this->tachesDeRotation();
        $this->personnesValidees(5);
        $this->scheduler()->generateSchedule(self::VENDREDI, 3);
        $idsSemaine1 = Creneau::whereIn('date', [self::VENDREDI, '2026-10-03'])->pluck('id')->all();

        $regen = $this->scheduler()->regenerateFromImpactedDate(Carbon::parse('2026-10-09'));

        $this->assertSame(2, $regen['semaines']);
        $this->assertSame(6, Creneau::count(), 'les 3 semaines existent toujours');
        $this->assertSame($idsSemaine1, Creneau::whereIn('date', [self::VENDREDI, '2026-10-03'])->pluck('id')->all(), 'semaine 1 intacte');
        $this->assertSame(30, CreneauTache::count());
    }
}
