<?php
// tests/Feature/Flows/PlanningGenerationNotificationFlowTest.php
//
// L'e-mail « planning généré » aux admins et gestionnaires, de bout en bout (vrai générateur, vrai
// rendu du gabarit, transport « array ») sur les TROIS chemins qui écrivent le planning :
//   - génération manuelle (PlanningController::generate) ;
//   - régénération suite à une absence (AbsenceRegenerationService) ;
//   - régénération suite à un événement (EvenementRegenerationService).
// Mais jamais sur un aperçu, une requête invalide ou une génération refusée — et un envoi en panne
// ne fait jamais échouer la génération.
//
// Repères : « aujourd'hui » = mercredi 2026-09-30 ; 2026-10-02 est un vendredi.
// Destinataires : « admin@ » et « gestionnaire@ » ; « membre@ » ne doit jamais rien recevoir.

declare(strict_types=1);

namespace Tests\Feature\Flows;

use App\Models\Absence;
use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Personne;
use App\Services\SchedulerMain;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\Support\TransportMailCasse;
use Tests\TestCase;

class PlanningGenerationNotificationFlowTest extends TestCase
{
    use CreeDonneesPlanning;
    use RefreshesBothDatabases;

    private const VENDREDI = '2026-10-02';

    private Personne $gestionnaire;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->travelTo('2026-09-30 09:00:00');
        $this->creerRolesPlanning();
        $this->tachesDeRotation();
        $this->personnesValidees(6);
        Personne::factory()->admin()->create(['prenom' => 'Adam', 'nom' => 'Admin', 'email' => 'admin@example.test']);
        Personne::factory()->membre()->create(['prenom' => 'Marie', 'nom' => 'Membre', 'email' => 'membre@example.test']);
        $this->gestionnaire = Personne::factory()->gestionnaire()->create(['prenom' => 'Gina', 'nom' => 'Gestion', 'email' => 'gestionnaire@example.test']);
        $this->actingAs($this->gestionnaire);
    }

    private function generer(array $surcharge = [])
    {
        return $this->post(route('planning.generate'), array_replace(['date_debut' => self::VENDREDI, 'semaines' => 2], $surcharge));
    }

    /** Planning déjà généré SANS passer par le contrôleur : aucun e-mail pour cette préparation. */
    private function planningDejaGenere(): void
    {
        $this->app->make(SchedulerMain::class)->generateSchedule(self::VENDREDI, 3);
        Bus::fake();
        $this->viderMails();
    }

    /** @return list<string> */
    private function destinataires(): array
    {
        return $this->mails()->pluck('to')->sort()->values()->all();
    }

    private function texte(array $mail): string
    {
        $sansStyle = preg_replace('#<(style|head)[^>]*>.*?</\1>#s', '', $mail['html']);

        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($sansStyle), ENT_QUOTES | ENT_HTML5)));
    }

    private function personneAyantUneTacheLe(string $date): Personne
    {
        $id = CreneauTache::where('id_planning', $this->creneauLe($date)->id)->whereNotNull('id_personne')->value('id_personne');

        return Personne::findOrFail($id);
    }

    // ══ Génération manuelle ═══════════════════════════════════════════════

    public function test_la_generation_manuelle_previent_les_admins_et_les_gestionnaires(): void
    {
        $this->generer()->assertSessionHas('success');

        $this->assertSame(['admin@example.test', 'gestionnaire@example.test'], $this->destinataires(), 'ni le membre ni les autres comptes');
        $mail = $this->mails()->first();
        $this->assertStringContainsString('Planning généré', $mail['subject']);
        $this->assertStringContainsString('2 oct.', $mail['subject']);
        $this->assertStringContainsString('10 oct. 2026', $mail['subject']);
        // Le modèle met le nom en majuscules (voir tests/README.md) : on compare à la valeur relue.
        $this->assertStringContainsString('Gina GESTION', $this->texte($mail), 'qui a déclenché la génération');
    }

    public function test_l_email_reprend_le_resultat_reel_de_la_generation(): void
    {
        $this->generer();

        $texte = $this->texte($this->mails()->first());
        $this->assertMatchesRegularExpression('/\b4 jours générés\b/', $texte, '2 semaines = 2 vendredis + 2 samedis');
        $this->assertStringContainsString('Vendredi 2 octobre → samedi 10 octobre 2026', $texte);
    }

    public function test_un_apercu_n_envoie_rien(): void
    {
        $this->post(route('planning.preview'), ['date_debut' => self::VENDREDI, 'semaines' => 2]);

        $this->assertSame(0, Creneau::count());
        $this->assertCount(0, $this->mails());
    }

    public function test_une_requete_invalide_n_envoie_rien(): void
    {
        $this->generer(['semaines' => 0])->assertSessionHasErrors('semaines');
        $this->generer(['date_debut' => '2026-09-13'])->assertSessionHasErrors('date_debut');

        $this->assertCount(0, $this->mails());
    }

    public function test_une_generation_non_confirmee_n_envoie_rien_et_une_generation_confirmee_envoie_a_nouveau(): void
    {
        $this->generer();
        $this->assertCount(2, $this->mails());

        $this->generer()->assertSessionHas('warning'); // des créneaux existent déjà : confirmation demandée
        $this->assertCount(2, $this->mails(), 'rien de plus tant que ce n\'est pas confirmé');

        $this->generer(['confirmed' => '1'])->assertSessionHas('success');
        $this->assertCount(4, $this->mails());
    }

    public function test_une_generation_deja_en_cours_est_refusee_sans_rien_envoyer(): void
    {
        Cache::lock('verrou:planning-generation', 60)->get();

        $this->generer()
            ->assertRedirect(route('planning.generate.form'))
            ->assertSessionHas('warning', fn(string $m) => str_contains($m, 'déjà en cours'));

        $this->assertSame(0, Creneau::count());
        $this->assertCount(0, $this->mails());
    }

    public function test_le_verrou_de_generation_est_relache_apres_la_generation(): void
    {
        $this->generer()->assertSessionHas('success');

        $this->assertTrue(Cache::lock('verrou:planning-generation', 5)->get());
    }

    public function test_un_envoi_en_panne_ne_fait_pas_echouer_la_generation(): void
    {
        TransportMailCasse::activer();

        $this->generer()->assertSessionHas('success')->assertSessionMissing('error');

        $this->assertGreaterThan(0, Creneau::count(), 'le planning est bien généré malgré la panne d\'e-mail');
    }

    // ══ Régénération suite à une absence ══════════════════════════════════

    public function test_une_absence_qui_regenere_le_planning_previent_les_admins_sans_reveler_la_raison(): void
    {
        $this->planningDejaGenere();
        $absente = $this->personneAyantUneTacheLe(self::VENDREDI);

        $this->post(route('absences.store'), ['id_personne' => $absente->id, 'date_debut' => self::VENDREDI, 'date_fin' => '2026-10-03', 'raison' => 'Rendez-vous médical confidentiel'])
            ->assertSessionHas('success');

        $this->assertSame(['admin@example.test', 'gestionnaire@example.test'], $this->destinataires());
        $mail = $this->mails()->first();
        $this->assertStringContainsString('Planning régénéré', $mail['subject']);
        $this->assertStringContainsString("suite à l'absence de {$absente->prenom} {$absente->nom}", $this->texte($mail));
        $this->assertStringNotContainsString('confidentiel', $mail['html'], 'la raison d\'une absence est une donnée sensible');
    }

    public function test_une_absence_sans_effet_sur_le_planning_n_envoie_rien(): void
    {
        $this->planningDejaGenere();

        $this->post(route('absences.store'), ['id_personne' => $this->gestionnaire->id, 'date_debut' => '2027-01-04', 'date_fin' => '2027-01-08'])
            ->assertSessionHas('success');

        $this->assertSame(1, Absence::count());
        $this->assertCount(0, $this->mails());
    }

    public function test_une_double_soumission_d_absence_n_envoie_qu_un_seul_jeu_d_emails(): void
    {
        $this->planningDejaGenere();
        $absente = $this->personneAyantUneTacheLe(self::VENDREDI);
        $donnees = ['id_personne' => $absente->id, 'date_debut' => self::VENDREDI, 'date_fin' => '2026-10-03'];

        $this->post(route('absences.store'), $donnees);
        $this->post(route('absences.store'), $donnees)->assertSessionHasErrors('date_debut');

        $this->assertCount(2, $this->mails(), 'deux destinataires, une seule régénération — pas quatre e-mails');
    }

    public function test_une_regeneration_automatique_bloquee_par_une_generation_en_cours_n_envoie_rien(): void
    {
        $this->planningDejaGenere();
        config(['planning.verrou.attente_regeneration_auto' => 0]); // pas d'attente : on ne veut pas dormir en test
        Cache::lock('verrou:planning-generation', 60)->get();
        $absente = $this->personneAyantUneTacheLe(self::VENDREDI);

        $this->post(route('absences.store'), ['id_personne' => $absente->id, 'date_debut' => self::VENDREDI, 'date_fin' => '2026-10-03'])
            ->assertSessionHas('success', fn(string $m) => str_contains($m, 'a échoué') && str_contains($m, 'une autre génération du planning est en cours'));

        $this->assertSame(1, Absence::count(), 'l\'absence reste enregistrée');
        $this->assertCount(0, $this->mails());
    }

    // ══ Régénération suite à un événement ═════════════════════════════════

    public function test_un_evenement_qui_regenere_le_planning_previent_les_admins(): void
    {
        $this->planningDejaGenere();

        $this->post(route('evenements.store'), ['nom' => 'Ramadan', 'date_debut' => self::VENDREDI, 'date_fin' => '2026-10-03'])
            ->assertSessionHas('success');

        $this->assertSame(['admin@example.test', 'gestionnaire@example.test'], $this->destinataires());
        $mail = $this->mails()->first();
        $this->assertStringContainsString('Planning régénéré', $mail['subject']);
        $this->assertStringContainsString("suite à l'événement « Ramadan »", $this->texte($mail));
    }

    public function test_un_evenement_sans_effet_sur_le_planning_n_envoie_rien(): void
    {
        $this->planningDejaGenere();

        $this->post(route('evenements.store'), ['nom' => 'Lointain', 'date_debut' => '2027-06-01', 'date_fin' => '2027-06-02'])
            ->assertSessionHas('success');

        $this->assertCount(0, $this->mails());
    }
}
