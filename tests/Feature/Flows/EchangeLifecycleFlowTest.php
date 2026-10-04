<?php
// tests/Feature/Flows/EchangeLifecycleFlowTest.php
//
// Cycle de vie d'un échange de bout en bout, comme le vivent les utilisateurs : demande
// (connecté) → e-mail à la cible → clic sur un lien à jeton (NON connecté) → échange effectif.
// La machine à états est détaillée dans EchangeServiceTest ; l'identité et les droits dans
// EchangeAuthorizationTest. Notification::fake() / Bus::fake() : ni e-mail ni appel Google.
//
// Repères : « aujourd'hui » = lundi 2026-09-14 ; A tient l'entrée du 02/10, B celle du 09/10.

declare(strict_types=1);

namespace Tests\Feature\Flows;

use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\Echange;
use App\Models\Personne;
use App\Notifications\Echanges\EchangeAccepteNotification;
use App\Notifications\Echanges\EchangeAnnuleNotification;
use App\Notifications\Echanges\EchangeDemandeNotification;
use App\Notifications\Echanges\EchangeRefuseNotification;
use Database\Factories\TacheFactory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\ConnecteParRole;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class EchangeLifecycleFlowTest extends TestCase
{
    use ConnecteParRole;
    use CreeDonneesPlanning;
    use RefreshesBothDatabases;

    private Personne $a;

    private Personne $b;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Bus::fake();
        $this->travelTo('2026-09-14 10:00:00');
        $this->creerRolesPlanning();
        $this->a = Personne::factory()->membre()->create(['prenom' => 'Alice', 'nom' => 'Alpha']);
        $this->b = Personne::factory()->membre()->create(['prenom' => 'Bilal', 'nom' => 'Bravo']);
        $this->assigner($this->a, '2026-10-02', 'entree');
        $this->assigner($this->b, '2026-10-09', 'entree');
    }

    private function titulaire(string $date): ?int
    {
        return $this->idPersonneDuCreneau($date, 'entree');
    }

    /** A demande l'échange (via HTTP, connectée), puis se déconnecte. */
    private function demander(): Echange
    {
        $entree = TacheFactory::pourCode('entree')->id;
        $this->actingAs($this->a)->postJson(route('echanges.store'), [
            'creneau_demandeur_id' => $this->creneauLe('2026-10-02')->id, 'tache_demandeur_id' => $entree,
            'creneau_cible_id' => $this->creneauLe('2026-10-09')->id, 'tache_cible_id' => $entree,
            'personne_cible_id' => $this->b->id,
        ])->assertOk()->assertJson(['success' => true]);
        $this->post(route('logout'));
        Notification::fake();
        Bus::fake();

        return Echange::firstOrFail();
    }

    // ══ Parcours nominal ══════════════════════════════════════════════════

    public function test_demande_acceptation_par_lien_puis_creneaux_echanges(): void
    {
        $echange = $this->demander();
        $this->assertSame($this->a->id, $this->titulaire('2026-10-02'), 'rien n\'a bougé à la simple demande');

        // B clique le lien reçu par e-mail, sans être connecté.
        $this->get(route('echanges.accepter', $echange->token_accept))
            ->assertOk()
            ->assertViewIs('echanges.token-result')
            ->assertViewHas('success', true)
            ->assertViewHas('action', 'accepte');

        $this->assertSame($this->b->id, $this->titulaire('2026-10-02'), 'B prend le créneau de A');
        $this->assertSame($this->a->id, $this->titulaire('2026-10-09'), 'A prend celui de B');
        $this->assertSame(Echange::STATUT_ACCEPTE, $echange->fresh()->statut);
        Notification::assertSentTo($this->a, EchangeAccepteNotification::class);
        Notification::assertSentTo($this->b, EchangeAccepteNotification::class);
        Bus::assertDispatchedTimes(SynchroniserGoogleCalendar::class, 1);
    }

    public function test_la_demande_previent_la_cible_et_le_message_contient_les_deux_liens(): void
    {
        $entree = TacheFactory::pourCode('entree')->id;
        $this->actingAs($this->a)->postJson(route('echanges.store'), [
            'creneau_demandeur_id' => $this->creneauLe('2026-10-02')->id, 'tache_demandeur_id' => $entree,
            'creneau_cible_id' => $this->creneauLe('2026-10-09')->id, 'tache_cible_id' => $entree,
            'personne_cible_id' => $this->b->id,
        ])->assertOk();
        $echange = Echange::firstOrFail();

        Notification::assertSentTo($this->b, EchangeDemandeNotification::class, function ($n) use ($echange) {
            $html = (string) $n->toMail($this->b)->render();

            return str_contains($html, route('echanges.accepter', $echange->token_accept))
                && str_contains($html, route('echanges.refuser', $echange->token_refuse));
        });
        Notification::assertNotSentTo($this->a, EchangeDemandeNotification::class);
    }

    public function test_refus_par_lien_ne_change_aucun_creneau_et_previent_le_demandeur(): void
    {
        $echange = $this->demander();

        $this->get(route('echanges.refuser', $echange->token_refuse))
            ->assertOk()
            ->assertViewHas('success', true)
            ->assertViewHas('action', 'refuse');

        $this->assertSame($this->a->id, $this->titulaire('2026-10-02'));
        $this->assertSame($this->b->id, $this->titulaire('2026-10-09'));
        $this->assertSame(Echange::STATUT_REFUSE, $echange->fresh()->statut);
        Notification::assertSentTo($this->a, EchangeRefuseNotification::class);
        Bus::assertNothingDispatched();
    }

    public function test_annulation_par_le_demandeur_rend_les_liens_inoperants(): void
    {
        $echange = $this->demander();
        $this->actingAs($this->a)->deleteJson(route('echanges.destroy', $echange->id))->assertOk();
        $this->post(route('logout'));

        Notification::assertSentTo($this->b, EchangeAnnuleNotification::class);
        $this->get(route('echanges.accepter', $echange->token_accept))
            ->assertViewHas('success', false)
            ->assertViewHas('action', 'erreur')
            ->assertViewHas('message', fn(string $m) => str_contains($m, 'plus en attente'));
        $this->assertSame($this->a->id, $this->titulaire('2026-10-02'), 'le lien d\'une demande annulée n\'échange rien');
    }

    public function test_un_second_clic_sur_le_meme_lien_n_echange_pas_a_nouveau(): void
    {
        $echange = $this->demander();
        $this->get(route('echanges.accepter', $echange->token_accept))->assertViewHas('action', 'accepte');

        $this->get(route('echanges.accepter', $echange->token_accept))
            ->assertViewHas('success', false)
            ->assertViewHas('action', 'erreur');

        $this->assertSame($this->b->id, $this->titulaire('2026-10-02'), 'toujours échangé UNE fois, pas rétabli');
        Notification::assertSentToTimes($this->a, EchangeAccepteNotification::class, 1);
    }

    public function test_apres_le_refus_le_lien_d_acceptation_ne_marche_plus(): void
    {
        $echange = $this->demander();
        $this->get(route('echanges.refuser', $echange->token_refuse));

        $this->get(route('echanges.accepter', $echange->token_accept))->assertViewHas('action', 'erreur');

        $this->assertSame($this->a->id, $this->titulaire('2026-10-02'));
    }

    public function test_un_lien_expire_affiche_le_message_et_ne_change_rien(): void
    {
        $echange = $this->demander();
        $this->travelTo('2026-10-03 08:00:00'); // lendemain du créneau de A

        $this->get(route('echanges.accepter', $echange->token_accept))
            ->assertViewHas('success', false)
            ->assertViewHas('action', 'erreur')
            ->assertViewHas('message', 'Ce lien a expiré.');

        $this->assertSame(Echange::STATUT_EXPIRE, $echange->fresh()->statut);
        $this->assertSame($this->a->id, $this->titulaire('2026-10-02'));
    }

    public function test_un_jeton_inconnu_affiche_lien_invalide(): void
    {
        $this->get(route('echanges.accepter', str_repeat('z', 64)))
            ->assertViewHas('action', 'invalide')
            ->assertViewHas('message', 'Ce lien est invalide ou a déjà été utilisé.');
        $this->get(route('echanges.refuser', str_repeat('z', 64)))->assertViewHas('action', 'invalide');
    }

    /**
     * CARACTÉRISATION (sécurité) : accepter ou refuser se fait par un simple GET, sans connexion
     * ni confirmation. Tout ce qui « visite » un lien sans être la cible — antivirus / passerelle
     * de messagerie qui analyse les URL, aperçu de lien d'une messagerie instantanée, navigateur
     * qui précharge — EXÉCUTE l'échange (ou le refuse) à la place du destinataire. Le jeton est
     * la seule protection. Usage conventionnel : GET affiche une page de confirmation, POST agit.
     */
    public function test_un_simple_get_sans_connexion_execute_l_echange(): void
    {
        $echange = $this->demander();
        $this->assertGuest();

        $this->get(route('echanges.accepter', $echange->token_accept));

        $this->assertSame(Echange::STATUT_ACCEPTE, $echange->fresh()->statut);
        $this->assertSame($this->b->id, $this->titulaire('2026-10-02'));
    }

    // ══ Validation par un administrateur ══════════════════════════════════

    public function test_un_gestionnaire_peut_approuver_la_demande_a_la_place_de_la_cible(): void
    {
        $echange = $this->demander();
        $chef = Personne::factory()->gestionnaire()->create();

        $this->actingAs($chef)->post(route('admin.echanges.approuver', $echange->id))->assertSessionHas('success');

        $this->assertSame($this->b->id, $this->titulaire('2026-10-02'));
        $this->assertSame($this->a->id, $this->titulaire('2026-10-09'));
        $this->assertSame($chef->id, (int) $echange->fresh()->approuve_par);
        Notification::assertSentTo($this->a, EchangeAccepteNotification::class);
        Notification::assertSentTo($this->b, EchangeAccepteNotification::class);
        Bus::assertDispatchedTimes(SynchroniserGoogleCalendar::class, 1);
    }

    public function test_apres_l_approbation_le_lien_de_la_cible_ne_marche_plus(): void
    {
        $echange = $this->demander();
        $this->actingAs(Personne::factory()->gestionnaire()->create())->post(route('admin.echanges.approuver', $echange->id));
        $this->post(route('logout'));

        $this->get(route('echanges.accepter', $echange->token_accept))->assertViewHas('action', 'erreur');
        $this->assertSame($this->b->id, $this->titulaire('2026-10-02'), 'pas de double échange');
    }

    public function test_apres_le_refus_par_lien_un_gestionnaire_ne_peut_plus_approuver(): void
    {
        $echange = $this->demander();
        $this->get(route('echanges.refuser', $echange->token_refuse));

        $this->actingAs(Personne::factory()->gestionnaire()->create())
            ->post(route('admin.echanges.approuver', $echange->id))->assertSessionHas('error');

        $this->assertSame($this->a->id, $this->titulaire('2026-10-02'));
    }

    // ══ Créneaux proposés à l'échange ═════════════════════════════════════

    public function test_le_selecteur_propose_les_creneaux_des_autres_pour_la_meme_tache(): void
    {
        $this->assigner(Personne::factory()->create(['prenom' => 'Carine', 'nom' => 'Charlie']), '2026-10-16', 'entree');
        $this->assigner($this->b, '2026-10-23', 'salle'); // autre tâche : exclu
        $creneauA = $this->creneauLe('2026-10-02')->id;

        $reponse = $this->actingAs($this->a)->getJson(route('echanges.slots', ['creneau_id' => $creneauA, 'tache_id' => TacheFactory::pourCode('entree')->id]))
            ->assertOk();

        $reponse->assertJsonCount(2)->assertJsonStructure([['creneau_id', 'tache_id', 'personne_id', 'personne_nom', 'date', 'date_label', 'jour', 'tache_libelle']]);
        $this->assertSame(['2026-10-09', '2026-10-16'], array_column($reponse->json(), 'date'));
        $this->assertSame($this->b->id, $reponse->json('0.personne_id'));
        $this->assertSame('Bilal ' . $this->b->nom, $reponse->json('0.personne_nom'));
        $this->assertSame('vendredi 9 octobre 2026', $reponse->json('0.date_label'));
    }

    public function test_le_selecteur_valide_ses_parametres(): void
    {
        $this->actingAs($this->a)->getJson(route('echanges.slots'))->assertUnprocessable()->assertJsonValidationErrors(['creneau_id', 'tache_id']);
        $this->actingAs($this->a)->getJson(route('echanges.slots', ['creneau_id' => 999999, 'tache_id' => 999999]))
            ->assertUnprocessable()->assertJsonValidationErrors(['creneau_id', 'tache_id']);
    }

    public function test_apres_l_echange_le_selecteur_reflete_les_nouveaux_titulaires(): void
    {
        $echange = $this->demander();
        $this->get(route('echanges.accepter', $echange->token_accept));
        $creneauA = $this->creneauLe('2026-10-02')->id;

        // Le créneau du 02/10 est désormais à B ; A (qui a maintenant celui du 09/10) voit le 02/10 proposé.
        $dates = $this->actingAs($this->a)
            ->getJson(route('echanges.slots', ['creneau_id' => $this->creneauLe('2026-10-09')->id, 'tache_id' => TacheFactory::pourCode('entree')->id]))
            ->json();

        $this->assertSame(['2026-10-02'], array_column($dates, 'date'));
        $this->assertSame($this->b->id, $dates[0]['personne_id']);
        $this->assertNotNull($creneauA);
    }

    // ══ Historique visible par chacun ═════════════════════════════════════

    public function test_la_liste_des_echanges_montre_le_statut_a_jour_aux_deux_parties(): void
    {
        $echange = $this->demander();
        $this->get(route('echanges.accepter', $echange->token_accept));

        foreach ([$this->a, $this->b] as $personne) {
            $vus = $this->actingAs($personne)->get(route('echanges.index'))->assertOk()->viewData('echanges');
            $this->assertSame([Echange::STATUT_ACCEPTE], $vus->pluck('statut')->all());
        }
    }
}
