<?php
// tests/Feature/Services/WebhookPayloadBuilderTest.php
//
// Les build*() lisent le planning en base (Creneau, CreneauTache, Tache, Evenement)
// et les paramètres via Setting::get() — dont le cache statique est pré-rempli
// avec FauxSettings, sans lignes dans ref_settings. Rien n'est envoyé nulle part :
// on ne vérifie que la FORME de ce qui serait posté à Make.com.
//
// Repères : 2026-10-02 est un vendredi.

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Helpers\GoogleCalendarColors;
use App\Models\Creneau;
use App\Models\Evenement;
use App\Models\Personne;
use App\Models\Tache;
use App\Services\WebhookPayloadBuilder;
use Database\Factories\TacheFactory;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\Support\FauxSettings;
use Tests\TestCase;

class WebhookPayloadBuilderTest extends TestCase
{
    use RefreshesBothDatabases;
    use CreeDonneesPlanning;

    private const VENDREDI = '2026-10-02';

    /** @var array<string, Tache> */
    private array $taches;

    protected function setUp(): void
    {
        parent::setUp();
        $this->taches = $this->tachesDeRotation();
        foreach (['rappel_sandwich', 'assistance_amana_food', 'annonce_cours', 'message_bot'] as $code) {
            $this->taches[$code] = TacheFactory::pourCode($code);
        }
        FauxSettings::definir(['lieu' => 'Mosquée Test']);
    }

    protected function tearDown(): void
    {
        FauxSettings::reinitialiser();
        parent::tearDown();
    }

    private function builder(): WebhookPayloadBuilder
    {
        return new WebhookPayloadBuilder();
    }

    private function personne(string $prenom = 'Jean', string $nom = 'Dupont'): Personne
    {
        return Personne::factory()->create(['prenom' => $prenom, 'nom' => $nom, 'email' => strtolower($prenom) . '@example.test']);
    }

    private function ligne(array $lignes, string $code): array
    {
        foreach ($lignes as $ligne) {
            if ($ligne['code'] === $code) {
                return $ligne;
            }
        }
        $this->fail("ligne « {$code} » absente : " . implode(', ', array_column($lignes, 'code')));
    }

    private function codes(array $lignes): array
    {
        return array_column($lignes, 'code');
    }

    private function creneauComplet(?Personne $entree = null, ?Personne $amana = null): Creneau
    {
        $this->assigner($entree, self::VENDREDI, 'entree');
        $this->assigner($amana, self::VENDREDI, 'amana_food');

        return $this->creneauLe(self::VENDREDI);
    }

    private function bloquer(Creneau $creneau, string ...$codes): void
    {
        $evenement = Evenement::factory()->du(self::VENDREDI, self::VENDREDI)->bloquant(...array_map(fn($c) => $this->taches[$c], $codes))->create();
        $creneau->evenements()->attach($evenement->id);
    }

    // ══ build() : structure ═══════════════════════════════════════════════

    public function test_build_structure_generale(): void
    {
        $this->creneauComplet();

        $payload = $this->builder()->build(self::VENDREDI, 1);

        $this->assertSame('Mosquée Test', $payload['lieu']);
        $this->assertCount(1, $payload['creneaux']);
        $creneau = $payload['creneaux'][0];
        $this->assertSame(['id_planning', 'date', 'taches', 'evenements_speciaux', 'evenements_sociaux'], array_keys($creneau));
        $this->assertSame(self::VENDREDI, $creneau['date']);
        $this->assertSame(['entree', 'mektaba', 'salle', 'amana_food', 'cours'], $this->codes($creneau['taches']));
        $this->assertSame(['rappel_sandwich', 'assistance_amana_food'], $this->codes($creneau['evenements_speciaux']));
        $this->assertSame(['annonce_cours', 'message_bot'], $this->codes($creneau['evenements_sociaux']));
    }

    public function test_build_sans_parametre_lieu_renvoie_une_chaine_vide(): void
    {
        FauxSettings::definir(['lieu' => null]);

        $this->assertSame('', $this->builder()->build(self::VENDREDI, 1)['lieu']);
    }

    public function test_build_trie_les_creneaux_par_date_et_part_du_premier_vendredi(): void
    {
        $this->creneauLe('2026-10-10');
        $this->creneauLe('2026-10-02');
        $this->creneauLe('2026-10-03');
        $this->creneauLe('2026-09-25'); // avant le premier vendredi

        $dates = array_column($this->builder()->build('2026-09-28', 2)['creneaux'], 'date'); // lundi → vendredi 02/10

        $this->assertSame(['2026-10-02', '2026-10-03', '2026-10-10'], $dates);
    }

    /**
     * CARACTÉRISATION : la fenêtre va de début à début + semaines×7 + 1 jour, bornes
     * incluses — une semaine de plus que demandé (même décalage que
     * SchedulerMain::buildRollbackSnapshot). Sans effet quand la régénération va jusqu'au
     * dernier créneau existant ; sinon la semaine suivante est aussi envoyée à Google.
     */
    public function test_build_inclut_la_semaine_qui_suit_la_fenetre_demandee(): void
    {
        foreach (['2026-10-02', '2026-10-03', '2026-10-09', '2026-10-10', '2026-10-16', '2026-10-17', '2026-10-23'] as $d) {
            $this->creneauLe($d);
        }

        $dates = array_column($this->builder()->build(self::VENDREDI, 1)['creneaux'], 'date');

        $this->assertSame(['2026-10-02', '2026-10-03', '2026-10-09', '2026-10-10'], $dates);
    }

    public function test_build_sans_creneau_renvoie_une_liste_vide(): void
    {
        $this->assertSame([], $this->builder()->build(self::VENDREDI, 4)['creneaux']);
    }

    // ══ Contenu d'une ligne ═══════════════════════════════════════════════

    public function test_une_ligne_assignee_porte_la_personne_ses_horaires_et_ses_reglages(): void
    {
        $jean = $this->personne();
        $this->creneauComplet($jean);
        FauxSettings::definir(['calendar_entree' => 'cal_entree@group.calendar.google.com', 'couleur_entree' => '4']);
        $this->taches['entree']->update(['libelle' => 'Entrée', 'description_calendrier' => 'Accueillir les élèves']);

        $ligne = $this->ligne($this->builder()->build(self::VENDREDI, 1)['creneaux'][0]['taches'], 'entree');

        $this->assertSame([
            'code' => 'entree',
            'nom' => 'Entrée',
            'assigne' => 'Jean ' . $jean->nom,
            'email' => 'jean@example.test',
            'heure_debut' => '20:00',
            'heure_fin' => '21:00',
            'calendar_ids' => ['cal_entree@group.calendar.google.com'],
            'description' => 'Accueillir les élèves',
            'color_id' => '4',
        ], $ligne);
    }

    public function test_une_ligne_non_assignee_a_assigne_et_email_null(): void
    {
        $this->creneauComplet(null);

        $ligne = $this->ligne($this->builder()->build(self::VENDREDI, 1)['creneaux'][0]['taches'], 'entree');

        $this->assertNull($ligne['assigne']);
        $this->assertNull($ligne['email']);
    }

    public function test_sans_reglage_calendrier_ni_couleur_ni_description(): void
    {
        $this->creneauComplet();

        $ligne = $this->ligne($this->builder()->build(self::VENDREDI, 1)['creneaux'][0]['taches'], 'salle');

        $this->assertSame([], $ligne['calendar_ids']);
        $this->assertSame(GoogleCalendarColors::TACHES['salle'], $ligne['color_id'], 'couleur par défaut de la palette');
        $this->assertSame('', $ligne['description']);
    }

    public function test_la_couleur_d_un_reglage_vide_retombe_sur_la_palette(): void
    {
        $this->creneauComplet();
        FauxSettings::definir(['couleur_salle' => '']);

        $ligne = $this->ligne($this->builder()->build(self::VENDREDI, 1)['creneaux'][0]['taches'], 'salle');

        $this->assertSame(GoogleCalendarColors::TACHES['salle'], $ligne['color_id']);
    }

    public function test_le_nom_de_repli_est_le_code_lisible_quand_la_tache_est_inconnue_du_referentiel(): void
    {
        Tache::whereIn('code', ['message_bot'])->delete();
        $this->creneauComplet();

        $ligne = $this->ligne($this->builder()->build(self::VENDREDI, 1)['creneaux'][0]['evenements_sociaux'], 'message_bot');

        $this->assertSame('Message bot', $ligne['nom']);
    }

    public function test_le_prenom_et_le_nom_de_l_assigne_sont_ordonnes_prenom_nom(): void
    {
        $this->creneauComplet($this->personne('Élodie', 'Martin'));

        $ligne = $this->ligne($this->builder()->build(self::VENDREDI, 1)['creneaux'][0]['taches'], 'entree');

        $this->assertStringStartsWith('Élodie ', $ligne['assigne']);
    }

    // ══ Horaires ══════════════════════════════════════════════════════════

    public function test_horaires_par_defaut_a_partir_de_l_heure_du_cours(): void
    {
        $this->creneauComplet();
        FauxSettings::definir(['heure_cours' => '19:15']);

        $ligne = $this->ligne($this->builder()->build(self::VENDREDI, 1)['creneaux'][0]['taches'], 'entree');

        $this->assertSame(['19:15', '20:15'], [$ligne['heure_debut'], $ligne['heure_fin']]);
    }

    public function test_les_decalages_configures_s_appliquent_a_l_heure_du_cours(): void
    {
        $this->creneauComplet();
        FauxSettings::definir(['offset_entree_debut' => -30, 'offset_entree_fin' => 15]);

        $ligne = $this->ligne($this->builder()->build(self::VENDREDI, 1)['creneaux'][0]['taches'], 'entree');

        $this->assertSame(['19:30', '20:15'], [$ligne['heure_debut'], $ligne['heure_fin']]);
    }

    public function test_un_decalage_incomplet_est_ignore_au_profit_des_valeurs_par_defaut(): void
    {
        $this->creneauComplet();
        FauxSettings::definir(['offset_entree_debut' => -30, 'offset_entree_fin' => null]);

        $ligne = $this->ligne($this->builder()->build(self::VENDREDI, 1)['creneaux'][0]['taches'], 'entree');

        $this->assertSame(['20:00', '21:00'], [$ligne['heure_debut'], $ligne['heure_fin']], 'ni −30 ni valeur mixte : 0 / +60');
    }

    public function test_le_rappel_sandwich_a_un_horaire_fixe_le_matin(): void
    {
        $this->creneauComplet();
        FauxSettings::definir(['offset_rappel_sandwich_debut' => 120, 'offset_rappel_sandwich_fin' => 180]);

        $ligne = $this->ligne($this->builder()->build(self::VENDREDI, 1)['creneaux'][0]['evenements_speciaux'], 'rappel_sandwich');

        $this->assertSame(['08:00', '08:15'], [$ligne['heure_debut'], $ligne['heure_fin']], 'les décalages ne s\'appliquent pas');
    }

    // ══ Événements dépendants ═════════════════════════════════════════════

    public function test_le_rappel_sandwich_suit_amana_food_et_l_assistance_suit_l_entree(): void
    {
        $entree = $this->personne('Entree');
        $amana = $this->personne('Amana');
        $this->creneauComplet($entree, $amana);

        $creneau = $this->builder()->build(self::VENDREDI, 1)['creneaux'][0];

        $this->assertSame($amana->email, $this->ligne($creneau['evenements_speciaux'], 'rappel_sandwich')['email']);
        $this->assertSame($entree->email, $this->ligne($creneau['evenements_speciaux'], 'assistance_amana_food')['email']);
        $this->assertNull($this->ligne($creneau['evenements_sociaux'], 'annonce_cours')['assigne']);
        $this->assertNull($this->ligne($creneau['evenements_sociaux'], 'message_bot')['assigne']);
    }

    public function test_une_tache_bloquee_disparait_avec_son_evenement_dependant(): void
    {
        $creneau = $this->creneauComplet();
        $this->bloquer($creneau, 'amana_food');

        $entry = $this->builder()->build(self::VENDREDI, 1)['creneaux'][0];

        $this->assertNotContains('amana_food', $this->codes($entry['taches']));
        $this->assertSame(['assistance_amana_food'], $this->codes($entry['evenements_speciaux']), 'plus de rappel sandwich');
        $this->assertContains('entree', $this->codes($entry['taches']));
    }

    public function test_bloquer_l_entree_retire_l_assistance_amana_food(): void
    {
        $creneau = $this->creneauComplet();
        $this->bloquer($creneau, 'entree');

        $entry = $this->builder()->build(self::VENDREDI, 1)['creneaux'][0];

        $this->assertSame(['rappel_sandwich'], $this->codes($entry['evenements_speciaux']));
    }

    // ══ buildForCreation / buildPourDate ══════════════════════════════════

    public function test_build_for_creation_donne_le_meme_creneau_que_build(): void
    {
        $creneau = $this->creneauComplet($this->personne());

        $this->assertSame(
            $this->builder()->build(self::VENDREDI, 1)['creneaux'],
            $this->builder()->buildForCreation($creneau->fresh())['creneaux'],
        );
    }

    public function test_build_pour_date_renvoie_null_sans_creneau(): void
    {
        $this->assertNull($this->builder()->buildPourDate(self::VENDREDI));
    }

    public function test_build_pour_date_omet_les_evenements_sociaux(): void
    {
        $this->creneauComplet();

        $entry = $this->builder()->buildPourDate(self::VENDREDI);

        $this->assertArrayNotHasKey('evenements_sociaux', $entry);
        $this->assertSame(self::VENDREDI, $entry['date']);
        $this->assertArrayHasKey('taches', $entry);
    }

    // ══ buildForAnnulationCours ═══════════════════════════════════════════

    public function test_annulation_cours_ajoute_l_evenement_social_d_annulation(): void
    {
        $creneau = $this->creneauComplet();

        $payload = $this->builder()->buildForAnnulationCours($creneau);

        $sociaux = $payload['creneaux'][0]['evenements_sociaux'];
        $this->assertSame(['annonce_cours', 'message_bot', 'annulation_cours'], $this->codes($sociaux));
        $this->assertNull($this->ligne($sociaux, 'annulation_cours')['assigne']);
        $this->assertSame('Mosquée Test', $payload['lieu']);
    }

    // ══ buildForReassignation / buildForEchange ═══════════════════════════

    public function test_reassignation_de_l_entree_renvoie_la_ligne_et_l_assistance(): void
    {
        $jean = $this->personne();
        $creneau = $this->creneauComplet($jean);

        $entry = $this->builder()->buildForReassignation($creneau, $this->taches['entree'])['creneaux'][0];

        $this->assertSame($creneau->id, $entry['id_planning']);
        $this->assertSame(['entree'], $this->codes($entry['taches']));
        $this->assertSame($jean->email, $entry['taches'][0]['email']);
        $this->assertSame(['assistance_amana_food'], $this->codes($entry['evenements_speciaux']));
        $this->assertSame($jean->email, $entry['evenements_speciaux'][0]['email']);
    }

    public function test_reassignation_d_amana_food_renvoie_aussi_le_rappel_sandwich(): void
    {
        $creneau = $this->creneauComplet(null, $this->personne());

        $entry = $this->builder()->buildForReassignation($creneau, $this->taches['amana_food'])['creneaux'][0];

        $this->assertSame(['rappel_sandwich'], $this->codes($entry['evenements_speciaux']));
    }

    public function test_reassignation_d_une_autre_tache_n_a_pas_d_evenement_dependant(): void
    {
        $this->assigner($this->personne(), self::VENDREDI, 'salle');
        $creneau = $this->creneauLe(self::VENDREDI);

        $entry = $this->builder()->buildForReassignation($creneau, $this->taches['salle'])['creneaux'][0];

        $this->assertSame(['salle'], $this->codes($entry['taches']));
        $this->assertArrayNotHasKey('evenements_speciaux', $entry);
    }

    public function test_echange_renvoie_les_deux_creneaux_dans_l_ordre_des_arguments(): void
    {
        $premier = $this->personne('Premier');
        $second = $this->personne('Second');
        $this->assigner($premier, '2026-10-02', 'entree');
        $this->assigner($second, '2026-10-09', 'entree');
        $c1 = $this->creneauLe('2026-10-02');
        $c2 = $this->creneauLe('2026-10-09');

        $payload = $this->builder()->buildForEchange($c2, $this->taches['entree'], $c1, $this->taches['entree']);

        $this->assertSame(['2026-10-09', '2026-10-02'], array_column($payload['creneaux'], 'date'));
        $this->assertSame($second->email, $payload['creneaux'][0]['taches'][0]['email']);
        $this->assertSame($premier->email, $payload['creneaux'][1]['taches'][0]['email']);
    }

    // ══ Suppressions ══════════════════════════════════════════════════════

    public function test_desassignation_ne_renvoie_que_de_quoi_supprimer_l_evenement(): void
    {
        $creneau = $this->creneauComplet($this->personne());
        FauxSettings::definir(['calendar_entree' => 'cal_entree@group.calendar.google.com']);

        $entry = $this->builder()->buildForUnassignation($creneau, $this->taches['entree'])['creneaux'][0];

        $this->assertSame(['code', 'nom', 'heure_debut', 'heure_fin', 'calendar_ids'], array_keys($entry['taches'][0]), 'ni assigné, ni email, ni description, ni couleur');
        $this->assertSame(['cal_entree@group.calendar.google.com'], $entry['taches'][0]['calendar_ids']);
        $this->assertSame(['assistance_amana_food'], $this->codes($entry['evenements_speciaux']));
    }

    public function test_desassignation_d_une_tache_sans_dependant(): void
    {
        $creneau = $this->creneauComplet();

        $entry = $this->builder()->buildForUnassignation($creneau, $this->taches['cours'])['creneaux'][0];

        $this->assertArrayNotHasKey('evenements_speciaux', $entry);
    }

    public function test_suppression_d_un_creneau_liste_tout_ce_qui_existe_dans_google(): void
    {
        $creneau = $this->creneauComplet();

        $entry = $this->builder()->buildForDeleteCreneau($creneau)['creneaux'][0];

        $this->assertSame(['entree', 'mektaba', 'salle', 'amana_food', 'cours'], $this->codes($entry['taches']));
        $this->assertSame(['rappel_sandwich', 'assistance_amana_food'], $this->codes($entry['evenements_speciaux']));
        $this->assertSame(['annonce_cours', 'message_bot'], $this->codes($entry['evenements_sociaux']));
        $this->assertSame(['08:00', '08:15'], [$entry['evenements_speciaux'][0]['heure_debut'], $entry['evenements_speciaux'][0]['heure_fin']]);
        $this->assertArrayNotHasKey('assigne', $entry['taches'][0]);
    }

    public function test_suppression_d_un_creneau_saute_ce_qu_un_evenement_a_bloque(): void
    {
        $creneau = $this->creneauComplet();
        $this->bloquer($creneau, 'amana_food', 'entree');

        $entry = $this->builder()->buildForDeleteCreneau($creneau)['creneaux'][0];

        $this->assertSame(['mektaba', 'salle', 'cours'], $this->codes($entry['taches']), 'jamais créés dans Google, rien à supprimer');
        $this->assertSame([], $entry['evenements_speciaux']);
        $this->assertSame(['annonce_cours', 'message_bot'], $this->codes($entry['evenements_sociaux']));
    }
}
