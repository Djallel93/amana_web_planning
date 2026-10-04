<?php
// tests/Unit/Services/GoogleCalendarPayloadMapperTest.php
//
// Le mapper n'a qu'une dépendance d'I/O : la résolution code → id_tache
// (Tache::pluck). On la remplace en pré-remplissant son cache privé, ce qui
// est exactement l'état du mapper après sa première résolution. Log est
// remplacé par un enregistreur (Log::swap) : ni base, ni application Laravel.

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\GoogleCalendarPayloadMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\Support\LogEnregistre;

class GoogleCalendarPayloadMapperTest extends TestCase
{
    private LogEnregistre $log;

    protected function setUp(): void
    {
        parent::setUp();
        $this->log = LogEnregistre::installer();
    }

    protected function tearDown(): void
    {
        LogEnregistre::retirer();
        parent::tearDown();
    }

    /** @param array<string, int> $ids code → id_tache connus de ref_taches */
    private function mapper(array $ids = ['entree' => 2, 'amana_food' => 1, 'rappel_sandwich' => 20, 'annonce_cours' => 30]): GoogleCalendarPayloadMapper
    {
        $mapper = new GoogleCalendarPayloadMapper();
        (new ReflectionProperty($mapper, 'tacheIdsParCode'))->setValue($mapper, collect($ids));

        return $mapper;
    }

    private function ligne(array $surcharge = []): array
    {
        return array_replace([
            'code' => 'entree',
            'nom' => 'Entrée',
            'assigne' => 'Jean Dupont',
            'email' => 'jean@example.test',
            'heure_debut' => '20:00',
            'heure_fin' => '21:00',
            'calendar_ids' => ['cal_entree@group.calendar.google.com'],
            'description' => 'Accueillir les élèves',
            'color_id' => '7',
        ], $surcharge);
    }

    private function creneau(array $lignes, string $groupe = 'taches', array $surcharge = []): array
    {
        return array_replace(['id_planning' => 5, 'date' => '2026-09-18', $groupe => $lignes], $surcharge);
    }

    // ── mapPlanning ───────────────────────────────────────────────────────

    public function test_planning_produit_une_operation_complete_par_ligne_et_par_calendrier(): void
    {
        $ops = $this->mapper()->mapPlanning(['creneaux' => [$this->creneau([$this->ligne()])]]);

        $this->assertSame([[
            'scope' => 'planning',
            'id_planning' => 5,
            'id_tache' => 2,
            'code' => 'entree',
            'calendar_id' => 'cal_entree@group.calendar.google.com',
            'summary' => 'JD - Entrée',
            'description' => "Accueillir les élèves\n\nAssigné(e) : Jean Dupont",
            'start' => '2026-09-18T20:00:00+02:00',
            'end' => '2026-09-18T21:00:00+02:00',
            'color_id' => '7',
        ]], $ops);
    }

    public function test_planning_une_operation_par_calendrier_cible(): void
    {
        $ligne = $this->ligne(['calendar_ids' => ['a@group.calendar.google.com', 'b@group.calendar.google.com']]);

        $ops = $this->mapper()->mapPlanning(['creneaux' => [$this->creneau([$ligne])]]);

        $this->assertSame(['a@group.calendar.google.com', 'b@group.calendar.google.com'], array_column($ops, 'calendar_id'));
    }

    public function test_planning_parcourt_taches_evenements_speciaux_et_sociaux(): void
    {
        $creneau = [
            'id_planning' => 5,
            'date' => '2026-09-18',
            'taches' => [$this->ligne()],
            'evenements_speciaux' => [$this->ligne(['code' => 'rappel_sandwich', 'nom' => 'Rappel sandwich'])],
            'evenements_sociaux' => [$this->ligne(['code' => 'annonce_cours', 'nom' => 'Annonce cours', 'assigne' => null])],
        ];

        $ops = $this->mapper()->mapPlanning(['creneaux' => [$creneau]]);

        $this->assertSame(['entree', 'rappel_sandwich', 'annonce_cours'], array_column($ops, 'code'));
    }

    public function test_planning_les_heures_sont_interpretees_a_paris_ete_et_hiver(): void
    {
        $ete = $this->mapper()->mapPlanning(['creneaux' => [$this->creneau([$this->ligne()], 'taches', ['date' => '2026-09-18'])]]);
        $hiver = $this->mapper()->mapPlanning(['creneaux' => [$this->creneau([$this->ligne()], 'taches', ['date' => '2026-12-18'])]]);

        $this->assertSame('2026-09-18T20:00:00+02:00', $ete[0]['start']);
        $this->assertSame('2026-12-18T20:00:00+01:00', $hiver[0]['start']);
    }

    public function test_planning_sans_heures_start_et_end_sont_null(): void
    {
        $ligne = $this->ligne();
        unset($ligne['heure_debut'], $ligne['heure_fin']);

        $ops = $this->mapper()->mapPlanning(['creneaux' => [$this->creneau([$ligne])]]);

        $this->assertNull($ops[0]['start']);
        $this->assertNull($ops[0]['end']);
    }

    public function test_planning_ignore_creneau_sans_id_ligne_sans_code_et_creneau_sans_date(): void
    {
        $sansId = ['date' => '2026-09-18', 'taches' => [$this->ligne()]];
        $sansCode = $this->creneau([$this->ligne(['code' => null])]);
        $sansDate = ['id_planning' => 5, 'taches' => [$this->ligne()]];

        $this->assertSame([], $this->mapper()->mapPlanning(['creneaux' => [$sansId, $sansCode, $sansDate]]));
        $this->assertSame([], $this->mapper()->mapPlanning([]));
    }

    public function test_planning_ignore_un_code_inconnu_de_ref_taches(): void
    {
        $ops = $this->mapper(['entree' => 2])->mapPlanning(['creneaux' => [$this->creneau([$this->ligne(['code' => 'code_inconnu'])])]]);

        $this->assertSame([], $ops);
        $this->assertSame([], $this->log->appels, 'un code inconnu n\'est pas un problème de calendrier : pas d\'avertissement');
    }

    public function test_planning_le_titre_n_a_de_prefixe_que_si_quelqu_un_est_assigne(): void
    {
        $ops = $this->mapper()->mapPlanning(['creneaux' => [$this->creneau([
            $this->ligne(['assigne' => null]),
            $this->ligne(['assigne' => '']),
            $this->ligne(['assigne' => '   ']),
        ])]]);

        $this->assertSame(['Entrée', 'Entrée', 'Entrée'], array_column($ops, 'summary'));
    }

    /** @return array<string, array{string, string}> */
    public static function initiales(): array
    {
        return [
            'prénom nom' => ['Jean Dupont', 'JD'],
            'composés : premier et dernier mot' => ['Jean-Pierre Da Silva', 'JS'],
            'trois mots' => ['Marie Anne Claire Martin', 'MM'],
            'un seul mot' => ['Madonna', 'MM'],
            'minuscules' => ['jean dupont', 'JD'],
            'accents' => ['élodie étienne', 'ÉÉ'],
            'espaces multiples' => ["  Jean \t  Dupont  ", 'JD'],
        ];
    }

    #[DataProvider('initiales')]
    public function test_planning_initiales_du_titre(string $assigne, string $initiales): void
    {
        $ops = $this->mapper()->mapPlanning(['creneaux' => [$this->creneau([$this->ligne(['assigne' => $assigne])])]]);

        $this->assertSame("{$initiales} - Entrée", $ops[0]['summary']);
    }

    public function test_planning_le_nom_de_repli_est_le_code(): void
    {
        $ligne = $this->ligne(['assigne' => null]);
        unset($ligne['nom']);

        $ops = $this->mapper()->mapPlanning(['creneaux' => [$this->creneau([$ligne])]]);

        $this->assertSame('entree', $ops[0]['summary']);
    }

    public function test_planning_description_combine_texte_de_reference_et_assignation(): void
    {
        $cas = [
            ['description' => 'Texte', 'assigne' => 'Jean Dupont', 'attendu' => "Texte\n\nAssigné(e) : Jean Dupont"],
            ['description' => 'Texte', 'assigne' => null, 'attendu' => 'Texte'],
            ['description' => '', 'assigne' => 'Jean Dupont', 'attendu' => 'Assigné(e) : Jean Dupont'],
            ['description' => '', 'assigne' => null, 'attendu' => null],
        ];

        foreach ($cas as $c) {
            $ops = $this->mapper()->mapPlanning(['creneaux' => [$this->creneau([$this->ligne(['description' => $c['description'], 'assigne' => $c['assigne']])])]]);
            $this->assertSame($c['attendu'], $ops[0]['description']);
        }
    }

    public function test_planning_sans_calendrier_configure_aucune_operation_et_un_seul_avertissement_par_code(): void
    {
        $sansCalendrier = $this->ligne(['calendar_ids' => []]);
        $calendrierVide = $this->ligne(['calendar_ids' => ['', null]]);
        $creneaux = [
            $this->creneau([$sansCalendrier], 'taches', ['id_planning' => 1, 'date' => '2026-09-18']),
            $this->creneau([$calendrierVide], 'taches', ['id_planning' => 2, 'date' => '2026-09-25']),
            $this->creneau([$this->ligne(['code' => 'amana_food', 'calendar_ids' => []])], 'taches', ['id_planning' => 3, 'date' => '2026-10-02']),
        ];

        $ops = $this->mapper()->mapPlanning(['creneaux' => $creneaux]);

        $this->assertSame([], $ops);
        $avertissements = $this->log->appelsDeNiveau('warning');
        $this->assertCount(1, $avertissements, 'un seul log pour tout le mapping, pas un par créneau');
        $this->assertSame(['entree', 'amana_food'], $avertissements[0]['contexte']['codes']);
    }

    public function test_planning_pas_d_avertissement_quand_tout_est_configure(): void
    {
        $this->mapper()->mapPlanning(['creneaux' => [$this->creneau([$this->ligne()])]]);

        $this->assertSame([], $this->log->appels);
    }

    public function test_planning_les_codes_sans_calendrier_sont_recomptes_a_chaque_mapping(): void
    {
        $mapper = $this->mapper();
        $mapper->mapPlanning(['creneaux' => [$this->creneau([$this->ligne(['calendar_ids' => []])])]]);
        $mapper->mapPlanning(['creneaux' => [$this->creneau([$this->ligne()])]]);

        $this->assertCount(1, $this->log->appelsDeNiveau('warning'), 'le second mapping est propre, pas de rappel du premier');
    }

    public function test_planning_une_ligne_configuree_n_est_pas_bloquee_par_une_ligne_sans_calendrier(): void
    {
        $ops = $this->mapper()->mapPlanning(['creneaux' => [$this->creneau([
            $this->ligne(['code' => 'amana_food', 'calendar_ids' => []]),
            $this->ligne(),
        ])]]);

        $this->assertSame(['entree'], array_column($ops, 'code'));
    }

    // ── mapEvenement ──────────────────────────────────────────────────────

    private function payloadEvenement(array $surcharge = []): array
    {
        return ['evenement' => array_replace([
            'id_evenement' => 12,
            'nom' => 'Ramadan',
            'date_debut' => '2027-03-01',
            'date_fin' => '2027-03-30',
            'description' => 'Horaires adaptés',
            'couleur' => '10',
            'calendar_ids' => ['a@group.calendar.google.com', 'b@group.calendar.google.com'],
        ], $surcharge)];
    }

    public function test_evenement_une_operation_par_calendrier_en_journee_entiere(): void
    {
        $ops = $this->mapper()->mapEvenement($this->payloadEvenement());

        $this->assertCount(2, $ops);
        $this->assertSame([
            'scope' => 'evenement',
            'id_evenement' => 12,
            'calendar_id' => 'a@group.calendar.google.com',
            'summary' => 'Ramadan',
            'description' => 'Horaires adaptés',
            'date_debut' => '2027-03-01',
            'date_fin' => '2027-03-31',
            'color_id' => '10',
        ], $ops[0]);
        $this->assertSame('b@group.calendar.google.com', $ops[1]['calendar_id']);
    }

    public function test_evenement_la_date_de_fin_est_exclusive_cote_google(): void
    {
        // Google Calendar « journée entière » : end.date est EXCLUSIVE, il faut ajouter un jour.
        $un_jour = $this->mapper()->mapEvenement($this->payloadEvenement(['date_debut' => '2027-03-10', 'date_fin' => '2027-03-10']));
        $fin_de_mois = $this->mapper()->mapEvenement($this->payloadEvenement(['date_fin' => '2027-12-31']));

        $this->assertSame('2027-03-11', $un_jour[0]['date_fin']);
        $this->assertSame('2028-01-01', $fin_de_mois[0]['date_fin']);
    }

    public function test_evenement_ignore_les_calendriers_vides(): void
    {
        $ops = $this->mapper()->mapEvenement($this->payloadEvenement(['calendar_ids' => ['', null, 'a@group.calendar.google.com']]));

        $this->assertSame(['a@group.calendar.google.com'], array_column($ops, 'calendar_id'));
    }

    public function test_evenement_sans_id_sans_evenement_ou_sans_calendrier_donne_liste_vide(): void
    {
        $sansId = $this->payloadEvenement();
        unset($sansId['evenement']['id_evenement']);

        $this->assertSame([], $this->mapper()->mapEvenement($sansId));
        $this->assertSame([], $this->mapper()->mapEvenement([]));
        $this->assertSame([], $this->mapper()->mapEvenement(['evenement' => null]));
        $this->assertSame([], $this->mapper()->mapEvenement($this->payloadEvenement(['calendar_ids' => []])));
    }

    public function test_evenement_champs_facultatifs_absents(): void
    {
        $payload = ['evenement' => ['id_evenement' => 1, 'calendar_ids' => ['a@group.calendar.google.com']]];

        $this->assertSame([
            'scope' => 'evenement',
            'id_evenement' => 1,
            'calendar_id' => 'a@group.calendar.google.com',
            'summary' => '',
            'description' => null,
            'date_debut' => null,
            'date_fin' => null,
            'color_id' => null,
        ], $this->mapper()->mapEvenement($payload)[0]);
    }

    // ── mapAbsence ────────────────────────────────────────────────────────

    private function payloadAbsence(array $surcharge = []): array
    {
        return ['absence' => array_replace([
            'id_absence' => 42,
            'nom' => 'Absence — Jean Dupont',
            'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-10',
            'description' => 'Vacances',
            'couleur' => '8',
            'calendar_id' => 'absences@group.calendar.google.com',
        ], $surcharge)];
    }

    public function test_absence_une_seule_operation_en_journee_entiere(): void
    {
        $this->assertSame([[
            'scope' => 'absence',
            'id_absence' => 42,
            'calendar_id' => 'absences@group.calendar.google.com',
            'summary' => 'Absence — Jean Dupont',
            'description' => 'Vacances',
            'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-11',
            'color_id' => '8',
        ]], $this->mapper()->mapAbsence($this->payloadAbsence()));
    }

    public function test_absence_sans_calendrier_sans_id_ou_sans_absence_donne_liste_vide(): void
    {
        $sansCalendrier = $this->payloadAbsence();
        unset($sansCalendrier['absence']['calendar_id']);
        $sansId = $this->payloadAbsence();
        unset($sansId['absence']['id_absence']);

        $this->assertSame([], $this->mapper()->mapAbsence($sansCalendrier));
        $this->assertSame([], $this->mapper()->mapAbsence($this->payloadAbsence(['calendar_id' => ''])));
        $this->assertSame([], $this->mapper()->mapAbsence($sansId));
        $this->assertSame([], $this->mapper()->mapAbsence([]));
    }

    public function test_absence_champs_facultatifs_absents(): void
    {
        // Payload de suppression (buildDelete) : uniquement id_absence + calendar_id.
        $ops = $this->mapper()->mapAbsence(['absence' => ['id_absence' => 7, 'calendar_id' => 'x@group.calendar.google.com']]);

        $this->assertSame('', $ops[0]['summary']);
        $this->assertNull($ops[0]['description']);
        $this->assertNull($ops[0]['date_fin']);
        $this->assertNull($ops[0]['color_id']);
    }
}
