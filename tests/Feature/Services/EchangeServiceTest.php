<?php
// tests/Feature/Services/EchangeServiceTest.php
//
// Machine à états des échanges : en_attente → accepte | refuse | annule | expire.
// Google Calendar et les e-mails ne sont jamais atteints : Notification::fake()
// et Bus::fake() (la synchronisation part dans SynchroniserGoogleCalendar).
//
// Repères : « A » = demandeur, « B » = cible. Les dates sont fixées avec
// Carbon::setTestNow() au 2026-09-14 ; les créneaux des factories (2031-…) sont donc futurs.

declare(strict_types=1);

namespace Tests\Feature\Services;

use Amana\Shared\Models\AuditLog;
use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Echange;
use App\Models\Personne;
use App\Models\Tache;
use App\Notifications\Echanges\EchangeAccepteNotification;
use App\Notifications\Echanges\EchangeAnnuleNotification;
use App\Notifications\Echanges\EchangeDemandeNotification;
use App\Notifications\Echanges\EchangeExpireNotification;
use App\Notifications\Echanges\EchangeRefuseNotification;
use App\Services\EchangeService;
use Carbon\Carbon;
use Database\Factories\TacheFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use ReflectionProperty;
use RuntimeException;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class EchangeServiceTest extends TestCase
{
    use CreeDonneesPlanning;
    use RefreshesBothDatabases;

    private Personne $a;

    private Personne $b;

    private Tache $entree;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-14 10:00:00'));
        Notification::fake();
        Bus::fake();

        $this->a = Personne::factory()->create(['nom' => 'Alpha', 'prenom' => 'A']);
        $this->b = Personne::factory()->create(['nom' => 'Bravo', 'prenom' => 'B']);
        $this->entree = TacheFactory::pourCode('entree');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): EchangeService
    {
        return $this->app->make(EchangeService::class);
    }

    /** A tient l'entrée du 2026-10-02, B celle du 2026-10-09. @return array{Creneau, Creneau} */
    private function deuxSlots(): array
    {
        $this->assigner($this->a, '2026-10-02', 'entree');
        $this->assigner($this->b, '2026-10-09', 'entree');

        return [$this->creneauLe('2026-10-02'), $this->creneauLe('2026-10-09')];
    }

    private function demander(Creneau $cA, Creneau $cB): Echange
    {
        return $this->service()->creerDemande($this->a->id, $cA->id, $this->entree->id, $this->b->id, $cB->id, $this->entree->id);
    }

    private function titulaire(Creneau $creneau): ?int
    {
        return $this->idPersonneDuCreneau($creneau->date->toDateString(), 'entree');
    }

    private function propriete(object $objet, string $nom): mixed
    {
        return (new ReflectionProperty($objet, $nom))->getValue($objet);
    }

    private function audits(string $action): int
    {
        return AuditLog::where('module', 'echanges')->where('action', $action)->count();
    }

    // ══ creerDemande ══════════════════════════════════════════════════════

    public function test_creer_demande_enregistre_une_demande_en_attente_avec_deux_tokens_distincts(): void
    {
        [$cA, $cB] = $this->deuxSlots();

        $echange = $this->demander($cA, $cB);

        $this->assertSame(Echange::STATUT_EN_ATTENTE, $echange->statut);
        $this->assertSame([$this->a->id, $cA->id, $this->entree->id], [$echange->id_personne_demandeur, $echange->id_creneau_demandeur, $echange->id_tache_demandeur]);
        $this->assertSame([$this->b->id, $cB->id, $this->entree->id], [$echange->id_personne_cible, $echange->id_creneau_cible, $echange->id_tache_cible]);
        $this->assertSame(64, strlen($echange->token_accept));
        $this->assertSame(64, strlen($echange->token_refuse));
        $this->assertNotSame($echange->token_accept, $echange->token_refuse);
        $this->assertNull($echange->approuve_par);
    }

    public function test_la_demande_expire_a_la_fin_de_la_journee_du_creneau_du_demandeur(): void
    {
        [$cA, $cB] = $this->deuxSlots();

        $echange = $this->demander($cA, $cB);

        $this->assertSame('2026-10-02 23:59:59', $echange->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_creer_demande_notifie_la_cible_et_journalise(): void
    {
        [$cA, $cB] = $this->deuxSlots();

        $echange = $this->demander($cA, $cB);

        Notification::assertSentTo($this->b, EchangeDemandeNotification::class);
        Notification::assertNotSentTo($this->a, EchangeDemandeNotification::class);
        $this->assertSame(1, $this->audits('create'));
        $this->assertSame($echange->id, (int) AuditLog::where('module', 'echanges')->value('entity_id'));
    }

    public function test_creer_demande_ne_touche_pas_aux_creneaux(): void
    {
        [$cA, $cB] = $this->deuxSlots();

        $this->demander($cA, $cB);

        $this->assertSame($this->a->id, $this->titulaire($cA));
        $this->assertSame($this->b->id, $this->titulaire($cB));
    }

    public function test_creer_demande_refuse_un_creneau_qui_n_appartient_pas_au_demandeur(): void
    {
        [$cA, $cB] = $this->deuxSlots();

        $this->expectException(ModelNotFoundException::class);
        $this->service()->creerDemande($this->b->id, $cA->id, $this->entree->id, $this->b->id, $cB->id, $this->entree->id);
    }

    public function test_creer_demande_refuse_un_creneau_qui_n_appartient_pas_a_la_cible(): void
    {
        [$cA, $cB] = $this->deuxSlots();

        $this->expectException(ModelNotFoundException::class);
        $this->service()->creerDemande($this->a->id, $cA->id, $this->entree->id, $this->a->id, $cB->id, $this->entree->id);
    }

    public function test_creer_demande_refuse_un_creneau_deja_passe(): void
    {
        $this->assigner($this->a, '2026-09-11', 'entree');
        $this->assigner($this->b, '2026-10-09', 'entree');

        try {
            $this->demander($this->creneauLe('2026-09-11'), $this->creneauLe('2026-10-09'));
            $this->fail('RuntimeException attendue');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('déjà passé', $e->getMessage());
        }
        $this->assertSame(0, Echange::count());
        Notification::assertNothingSent();
    }

    public function test_creer_demande_refuse_le_creneau_du_jour_meme(): void
    {
        // « Passé » se juge sur la date à minuit : le créneau d'aujourd'hui l'est déjà.
        $this->assigner($this->a, '2026-09-14', 'entree');
        $this->assigner($this->b, '2026-10-09', 'entree');

        $this->expectException(RuntimeException::class);
        $this->demander($this->creneauLe('2026-09-14'), $this->creneauLe('2026-10-09'));
    }

    public function test_le_creneau_de_la_cible_peut_etre_passe(): void
    {
        // Seul le créneau du demandeur est contrôlé.
        $this->assigner($this->a, '2026-10-02', 'entree');
        $this->assigner($this->b, '2026-09-11', 'entree');

        $echange = $this->demander($this->creneauLe('2026-10-02'), $this->creneauLe('2026-09-11'));

        $this->assertTrue($echange->exists);
    }

    public function test_creer_demande_refuse_quand_le_creneau_du_demandeur_est_deja_engage(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $this->assigner(Personne::factory()->create(), '2026-10-16', 'entree');
        $this->demander($cA, $cB); // A ↔ B en attente

        try {
            $this->service()->creerDemande($this->a->id, $cA->id, $this->entree->id, $this->b->id, $this->creneauLe('2026-10-16')->id, $this->entree->id);
            $this->fail('RuntimeException attendue');
        } catch (RuntimeException) {
        }
        $this->assertSame(1, Echange::count());
    }

    public function test_creer_demande_refuse_quand_le_creneau_de_la_cible_est_deja_engage(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $troisieme = Personne::factory()->create();
        $this->assigner($troisieme, '2026-10-16', 'entree');
        $this->demander($cA, $cB); // le slot de B est engagé (en tant que cible)

        $this->expectException(RuntimeException::class);
        $this->service()->creerDemande($troisieme->id, $this->creneauLe('2026-10-16')->id, $this->entree->id, $this->b->id, $cB->id, $this->entree->id);
    }

    /**
     * CARACTÉRISATION d'un trou : le contrôle « déjà en cours » compare le slot du demandeur
     * à ceux des demandeurs existants, et le slot de la cible à ceux des cibles existantes.
     * Il ne croise jamais les deux. Le slot de B, déjà engagé comme CIBLE dans A↔B, peut
     * donc être proposé de nouveau par B comme slot de DEMANDEUR : deux échanges en attente
     * portent alors sur le même créneau. Si c'est corrigé, ce test doit passer à « refusé ».
     */
    public function test_un_slot_deja_cible_peut_etre_repropose_comme_slot_de_demandeur(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $troisieme = Personne::factory()->create();
        $this->assigner($troisieme, '2026-10-16', 'entree');
        $this->demander($cA, $cB);

        $second = $this->service()->creerDemande($this->b->id, $cB->id, $this->entree->id, $troisieme->id, $this->creneauLe('2026-10-16')->id, $this->entree->id);

        $this->assertTrue($second->exists);
        $this->assertSame(2, Echange::enAttente()->count());
    }

    public function test_les_echanges_termines_ne_bloquent_pas_une_nouvelle_demande(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        foreach (['accepte', 'refuse', 'annule', 'expire'] as $etat) {
            Echange::factory()->{$etat}()->create([
                'id_personne_demandeur' => $this->a->id, 'id_creneau_demandeur' => $cA->id, 'id_tache_demandeur' => $this->entree->id,
                'id_personne_cible' => $this->b->id, 'id_creneau_cible' => $cB->id, 'id_tache_cible' => $this->entree->id,
            ]);
        }

        $echange = $this->demander($cA, $cB);

        $this->assertTrue($echange->isEnAttente());
    }

    // ══ accepterParToken ══════════════════════════════════════════════════

    public function test_accepter_echange_les_deux_creneaux_et_marque_l_echange_accepte(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);

        $resultat = $this->service()->accepterParToken($echange->token_accept);

        $this->assertSame(Echange::STATUT_ACCEPTE, $resultat->statut);
        $this->assertNull($resultat->approuve_par, 'accepté par la cible elle-même, pas par un admin');
        $this->assertSame($this->b->id, $this->titulaire($cA), 'B prend le créneau de A');
        $this->assertSame($this->a->id, $this->titulaire($cB), 'A prend le créneau de B');
        $this->assertSame(Echange::STATUT_ACCEPTE, Echange::find($echange->id)->statut);
    }

    public function test_accepter_notifie_les_deux_parties_avec_leur_role(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);

        $this->service()->accepterParToken($echange->token_accept);

        Notification::assertSentTo($this->a, EchangeAccepteNotification::class, fn($n) => $this->propriete($n, 'role') === 'demandeur');
        Notification::assertSentTo($this->b, EchangeAccepteNotification::class, fn($n) => $this->propriete($n, 'role') === 'cible');
    }

    public function test_accepter_declenche_la_synchronisation_google_calendar(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);
        Bus::assertNotDispatched(SynchroniserGoogleCalendar::class, 'rien ne part à la simple demande');

        $this->service()->accepterParToken($echange->token_accept);

        Bus::assertDispatchedTimes(SynchroniserGoogleCalendar::class, 1);
    }

    public function test_accepter_journalise_l_echange_execute(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);

        $this->service()->accepterParToken($echange->token_accept);

        $this->assertSame(1, $this->audits('update'));
        $apres = AuditLog::where('module', 'echanges')->where('action', 'update')->first()->after;
        $this->assertSame('swap_executed', $apres['action']);
    }

    public function test_accepter_avec_un_token_inconnu_ne_change_rien(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $this->demander($cA, $cB);

        try {
            $this->service()->accepterParToken(str_repeat('x', 64));
            $this->fail('ModelNotFoundException attendue');
        } catch (ModelNotFoundException) {
        }
        $this->assertSame($this->a->id, $this->titulaire($cA));
    }

    public function test_le_token_de_refus_n_accepte_pas_l_echange(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);

        $this->expectException(ModelNotFoundException::class);
        $this->service()->accepterParToken($echange->token_refuse);
    }

    public function test_un_lien_d_acceptation_ne_sert_qu_une_fois(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);
        $this->service()->accepterParToken($echange->token_accept);

        try {
            $this->service()->accepterParToken($echange->token_accept);
            $this->fail('RuntimeException attendue');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('plus en attente', $e->getMessage());
        }
        $this->assertSame($this->b->id, $this->titulaire($cA), 'pas de double échange qui remettrait tout comme avant');
    }

    public function test_accepter_un_echange_deja_refuse_ou_annule_est_impossible(): void
    {
        foreach (['refuse', 'annule', 'expire'] as $etat) {
            $echange = Echange::factory()->{$etat}()->create();

            try {
                $this->service()->accepterParToken($echange->token_accept);
                $this->fail("RuntimeException attendue pour {$etat}");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('plus en attente', $e->getMessage());
            }
        }
    }

    public function test_un_lien_perime_marque_l_echange_expire_sans_rien_echanger_et_le_persiste(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);
        Carbon::setTestNow(Carbon::parse('2026-10-03 08:00:00')); // lendemain du créneau de A

        try {
            $this->service()->accepterParToken($echange->token_accept);
            $this->fail('RuntimeException attendue');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('expiré', $e->getMessage());
        }

        $this->assertSame(Echange::STATUT_EXPIRE, Echange::find($echange->id)->statut, 'l\'expiration est enregistrée (pas annulée avec l\'exception)');
        $this->assertSame($this->a->id, $this->titulaire($cA));
        $this->assertSame($this->b->id, $this->titulaire($cB));
        Bus::assertNotDispatched(SynchroniserGoogleCalendar::class);
        Notification::assertNotSentTo($this->a, EchangeAccepteNotification::class);
    }

    public function test_le_lien_reste_valable_jusqu_a_la_derniere_seconde_du_jour_du_creneau(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);
        Carbon::setTestNow(Carbon::parse('2026-10-02 23:59:00'));

        $resultat = $this->service()->accepterParToken($echange->token_accept);

        $this->assertSame(Echange::STATUT_ACCEPTE, $resultat->statut);
    }

    // ══ executerEchange : atomicité ═══════════════════════════════════════

    public function test_si_l_execution_echoue_en_cours_de_route_aucun_creneau_n_est_echange(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);
        Echange::updating(function () {
            throw new RuntimeException('panne simulée à la mise à jour du statut');
        });

        try {
            $this->service()->accepterParToken($echange->token_accept);
            $this->fail('RuntimeException attendue');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('panne simulée', $e->getMessage());
        } finally {
            Echange::flushEventListeners();
        }

        $this->assertSame($this->a->id, $this->titulaire($cA), 'le premier UPDATE a été annulé');
        $this->assertSame($this->b->id, $this->titulaire($cB), 'le second aussi');
        $this->assertSame(Echange::STATUT_EN_ATTENTE, Echange::find($echange->id)->statut);
        Notification::assertNotSentTo($this->a, EchangeAccepteNotification::class);
        Notification::assertNotSentTo($this->b, EchangeAccepteNotification::class);
        Bus::assertNotDispatched(SynchroniserGoogleCalendar::class);
    }

    /**
     * CARACTÉRISATION : à l'exécution, le service ne revérifie PAS que chaque créneau
     * appartient toujours à la personne prévue. Si le planning a été régénéré / réassigné
     * entre la demande et l'acceptation, l'échange écrase l'affectation actuelle.
     */
    public function test_l_execution_ne_reverifie_pas_les_titulaires_actuels(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);
        $c = Personne::factory()->create();
        CreneauTache::where('id_planning', $cA->id)->where('id_tache', $this->entree->id)->update(['id_personne' => $c->id]); // réassigné à C

        $this->service()->accepterParToken($echange->token_accept);

        $this->assertSame($this->b->id, $this->titulaire($cA), 'l\'affectation de C a été écrasée');
        $this->assertSame($this->a->id, $this->titulaire($cB), 'et A, qui n\'avait plus ce créneau, récupère celui de B');
    }

    // ══ refuserParToken ═══════════════════════════════════════════════════

    public function test_refuser_par_token_ne_change_aucun_creneau_et_previent_le_demandeur(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);

        $resultat = $this->service()->refuserParToken($echange->token_refuse);

        $this->assertSame(Echange::STATUT_REFUSE, $resultat->statut);
        $this->assertSame($this->a->id, $this->titulaire($cA));
        $this->assertSame($this->b->id, $this->titulaire($cB));
        Notification::assertSentTo($this->a, EchangeRefuseNotification::class);
        Notification::assertNotSentTo($this->b, EchangeRefuseNotification::class);
        Bus::assertNotDispatched(SynchroniserGoogleCalendar::class);
    }

    /** CARACTÉRISATION : contrairement à l'acceptation, le refus ne contrôle pas expires_at. */
    public function test_un_lien_de_refus_perime_fonctionne_encore_tant_que_l_echange_est_en_attente(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:00:00')); // bien après l'échéance

        $resultat = $this->service()->refuserParToken($echange->token_refuse);

        $this->assertSame(Echange::STATUT_REFUSE, $resultat->statut);
    }

    public function test_le_token_d_acceptation_ne_refuse_pas_l_echange(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);

        $this->expectException(ModelNotFoundException::class);
        $this->service()->refuserParToken($echange->token_accept);
    }

    public function test_refuser_un_echange_qui_n_est_plus_en_attente_est_impossible(): void
    {
        $echange = Echange::factory()->accepte()->create();

        $this->expectException(RuntimeException::class);
        $this->service()->refuserParToken($echange->token_refuse);
    }

    public function test_refuser_journalise_le_changement_de_statut(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);

        $this->service()->refuserParToken($echange->token_refuse);

        $this->assertSame(1, $this->audits('update'));
    }

    // ══ Décisions d'un administrateur ═════════════════════════════════════

    public function test_un_admin_peut_approuver_et_l_echange_s_execute_avec_son_id(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);
        $admin = Personne::factory()->admin()->create();

        $resultat = $this->service()->approuverParAdmin($echange->id, $admin->id);

        $this->assertSame(Echange::STATUT_ACCEPTE, $resultat->statut);
        $this->assertSame($admin->id, (int) $resultat->approuve_par);
        $this->assertSame($this->b->id, $this->titulaire($cA));
        $this->assertSame($this->a->id, $this->titulaire($cB));
        Bus::assertDispatchedTimes(SynchroniserGoogleCalendar::class, 1);
    }

    public function test_un_admin_peut_approuver_meme_apres_expiration_du_lien(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);
        Carbon::setTestNow(Carbon::parse('2026-10-03 08:00:00'));

        $resultat = $this->service()->approuverParAdmin($echange->id, Personne::factory()->admin()->create()->id);

        $this->assertSame(Echange::STATUT_ACCEPTE, $resultat->statut);
    }

    public function test_approuver_un_echange_deja_traite_ou_inconnu_est_impossible(): void
    {
        $echange = Echange::factory()->refuse()->create();

        try {
            $this->service()->approuverParAdmin($echange->id, 1);
            $this->fail('RuntimeException attendue');
        } catch (RuntimeException) {
        }

        $this->expectException(ModelNotFoundException::class);
        $this->service()->approuverParAdmin(999999, 1);
    }

    public function test_un_admin_peut_refuser_et_son_id_est_enregistre(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);
        $admin = Personne::factory()->admin()->create();

        $resultat = $this->service()->refuserParAdmin($echange->id, $admin->id);

        $this->assertSame(Echange::STATUT_REFUSE, $resultat->statut);
        $this->assertSame($admin->id, (int) $resultat->approuve_par);
        $this->assertSame($this->a->id, $this->titulaire($cA));
        Notification::assertSentTo($this->a, EchangeRefuseNotification::class);
    }

    public function test_refuser_par_admin_un_echange_deja_traite_est_impossible(): void
    {
        $echange = Echange::factory()->annule()->create();

        $this->expectException(RuntimeException::class);
        $this->service()->refuserParAdmin($echange->id, 1);
    }

    // ══ annulerParDemandeur ═══════════════════════════════════════════════

    public function test_le_demandeur_peut_annuler_sa_demande_et_la_cible_est_prevenue(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);

        $resultat = $this->service()->annulerParDemandeur($echange->id, $this->a->id);

        $this->assertSame(Echange::STATUT_ANNULE, $resultat->statut);
        Notification::assertSentTo($this->b, EchangeAnnuleNotification::class);
        Notification::assertNotSentTo($this->a, EchangeAnnuleNotification::class);
        $this->assertSame($this->a->id, $this->titulaire($cA));
    }

    public function test_seul_le_demandeur_peut_annuler_sa_demande(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);

        foreach ([$this->b->id, Personne::factory()->admin()->create()->id] as $intrus) {
            try {
                $this->service()->annulerParDemandeur($echange->id, $intrus);
                $this->fail('RuntimeException attendue');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('propres demandes', $e->getMessage());
            }
        }
        $this->assertSame(Echange::STATUT_EN_ATTENTE, Echange::find($echange->id)->statut);
        Notification::assertNotSentTo($this->b, EchangeAnnuleNotification::class);
    }

    public function test_on_ne_peut_pas_annuler_un_echange_deja_traite(): void
    {
        $echange = Echange::factory()->accepte()->create();

        try {
            $this->service()->annulerParDemandeur($echange->id, $echange->id_personne_demandeur);
            $this->fail('RuntimeException attendue');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('plus être annulé', $e->getMessage());
        }
    }

    public function test_annuler_un_echange_inconnu_leve_une_exception_de_modele(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->service()->annulerParDemandeur(999999, $this->a->id);
    }

    public function test_apres_annulation_les_creneaux_peuvent_etre_proposes_a_nouveau(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $echange = $this->demander($cA, $cB);
        $this->service()->annulerParDemandeur($echange->id, $this->a->id);

        $nouveau = $this->demander($cA, $cB);

        $this->assertTrue($nouveau->isEnAttente());
    }

    // ══ expirerEchanges ═══════════════════════════════════════════════════

    public function test_expirer_echanges_ne_traite_que_les_demandes_en_attente_dont_l_echeance_est_passee(): void
    {
        $perime = Echange::factory()->lienExpire()->create();
        $encoreValide = Echange::factory()->create();                                  // expire dans 3 jours
        $dejaAccepte = Echange::factory()->accepte()->create(['expires_at' => now()->subDay()]);
        $dejaRefuse = Echange::factory()->refuse()->create(['expires_at' => now()->subDay()]);

        $nombre = $this->service()->expirerEchanges();

        $this->assertSame(1, $nombre);
        $this->assertSame(Echange::STATUT_EXPIRE, $perime->fresh()->statut);
        $this->assertSame(Echange::STATUT_EN_ATTENTE, $encoreValide->fresh()->statut);
        $this->assertSame(Echange::STATUT_ACCEPTE, $dejaAccepte->fresh()->statut);
        $this->assertSame(Echange::STATUT_REFUSE, $dejaRefuse->fresh()->statut);
    }

    public function test_expirer_echanges_previent_le_demandeur_uniquement_et_journalise(): void
    {
        $perime = Echange::factory()->lienExpire()->create();

        $this->service()->expirerEchanges();

        Notification::assertSentTo($perime->demandeur, EchangeExpireNotification::class);
        Notification::assertNotSentTo($perime->cible, EchangeExpireNotification::class);
        $this->assertSame(1, $this->audits('update'));
    }

    public function test_expirer_echanges_ne_touche_pas_aux_creneaux(): void
    {
        $perime = Echange::factory()->lienExpire()->create();
        $avant = CreneauTache::orderBy('id_planning')->get(['id_planning', 'id_tache', 'id_personne'])->toArray();

        $this->service()->expirerEchanges();

        $this->assertSame($avant, CreneauTache::orderBy('id_planning')->get(['id_planning', 'id_tache', 'id_personne'])->toArray());
        Bus::assertNotDispatched(SynchroniserGoogleCalendar::class);
    }

    public function test_expirer_echanges_l_echeance_est_strictement_dans_le_passe(): void
    {
        $limite = Echange::factory()->create(['expires_at' => now()]);   // exactement maintenant
        $unPeuPlusTard = Echange::factory()->create(['expires_at' => now()->addSecond()]);
        $unPeuAvant = Echange::factory()->create(['expires_at' => now()->subSecond()]);

        $nombre = $this->service()->expirerEchanges();

        $this->assertSame(1, $nombre);
        $this->assertSame(Echange::STATUT_EN_ATTENTE, $limite->fresh()->statut);
        $this->assertSame(Echange::STATUT_EN_ATTENTE, $unPeuPlusTard->fresh()->statut);
        $this->assertSame(Echange::STATUT_EXPIRE, $unPeuAvant->fresh()->statut);
    }

    public function test_expirer_echanges_sans_rien_a_faire_renvoie_zero(): void
    {
        $this->assertSame(0, $this->service()->expirerEchanges());
        Notification::assertNothingSent();
    }

    public function test_expirer_echanges_est_idempotent(): void
    {
        Echange::factory()->lienExpire()->create();

        $this->assertSame(1, $this->service()->expirerEchanges());
        $this->assertSame(0, $this->service()->expirerEchanges());
    }

    // ══ slotsEchangeables ═════════════════════════════════════════════════

    public function test_slots_echangeables_ne_propose_que_les_creneaux_futurs_des_autres_pour_la_meme_tache(): void
    {
        $this->assigner($this->a, '2026-10-02', 'entree');           // le mien
        $this->assigner($this->b, '2026-10-09', 'entree');           // ✔
        $this->assigner($this->b, '2026-09-11', 'entree');           // passé
        $this->assigner($this->b, '2026-09-14', 'entree');           // aujourd'hui : exclu (strictement futur)
        $this->assigner($this->b, '2026-10-16', 'salle');            // autre tâche
        $this->assigner(null, '2026-10-23', 'entree');               // non assigné
        $this->assigner(Personne::factory()->create(), '2026-10-30', 'entree'); // ✔ (une troisième personne)

        $slots = $this->service()->slotsEchangeables($this->creneauLe('2026-10-02')->id, $this->entree->id, $this->a->id);

        $this->assertSame(
            ['2026-10-09', '2026-10-30'],
            $slots->map(fn($s) => $s->creneau->date->toDateString())->all(),
            'triés par date',
        );
    }

    public function test_slots_echangeables_exclut_les_creneaux_deja_dans_un_echange_en_attente(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $troisieme = Personne::factory()->create();
        $quatrieme = Personne::factory()->create();
        $this->assigner($troisieme, '2026-10-16', 'entree');
        $this->assigner($quatrieme, '2026-10-23', 'entree');
        // Échange en attente A ↔ troisième : engage le 02/10 (demandeur) et le 16/10 (cible).
        $this->service()->creerDemande($this->a->id, $cA->id, $this->entree->id, $troisieme->id, $this->creneauLe('2026-10-16')->id, $this->entree->id);

        $dates = $this->service()->slotsEchangeables($cB->id, $this->entree->id, $this->b->id)
            ->map(fn($s) => $s->creneau->date->toDateString())->all();

        $this->assertNotContains('2026-10-16', $dates, 'engagé dans un échange en attente (côté cible)');
        $this->assertNotContains('2026-10-02', $dates, 'idem côté demandeur');
        $this->assertContains('2026-10-23', $dates);
    }

    public function test_slots_echangeables_les_echanges_termines_ne_masquent_rien(): void
    {
        $this->assigner($this->b, '2026-10-09', 'entree');
        Echange::factory()->refuse()->create([
            'id_personne_cible' => $this->b->id, 'id_creneau_cible' => $this->creneauLe('2026-10-09')->id, 'id_tache_cible' => $this->entree->id,
        ]);

        $slots = $this->service()->slotsEchangeables($this->creneauLe('2026-10-09')->id, $this->entree->id, $this->a->id);

        $this->assertCount(1, $slots);
    }

    /** CARACTÉRISATION : le masquage se fait par CRÉNEAU (toute la journée), pas par couple créneau + tâche. */
    public function test_slots_echangeables_un_echange_en_attente_masque_toute_la_journee_pas_seulement_la_tache(): void
    {
        [$cA, $cB] = $this->deuxSlots();
        $this->assigner(Personne::factory()->create(), '2026-10-09', 'salle'); // même jour que le slot de B, autre tâche
        $this->demander($cA, $cB);

        $salle = TacheFactory::pourCode('salle');
        $slots = $this->service()->slotsEchangeables($cA->id, $salle->id, $this->a->id);

        $this->assertCount(0, $slots, 'la salle du 09/10 est masquée parce que l\'entrée du 09/10 est engagée');
    }

    public function test_slots_echangeables_avec_une_tache_inconnue_leve_une_exception_de_modele(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->service()->slotsEchangeables(1, 999999, $this->a->id);
    }
}
