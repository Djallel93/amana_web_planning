<?php
// tests/Feature/Services/PlanningGenerationNotifierTest.php
//
// Qui est prévenu quand le planning est généré, avec quel contexte, et la garantie qu'un échec
// d'envoi ne remonte jamais à l'appelant. Le contenu rendu de l'e-mail est testé dans
// NotificationsContenuTest ; les trois chemins de génération qui appellent ce service, dans
// PlanningGenerationNotificationFlowTest.

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Personne;
use App\Notifications\PlanningGenereNotification;
use App\Services\PlanningGenerationNotifier;
use Carbon\Carbon;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\Support\TransportMailCasse;
use Tests\TestCase;

class PlanningGenerationNotifierTest extends TestCase
{
    use CreeDonneesPlanning;
    use RefreshesBothDatabases;

    private PlanningGenerationNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-30 09:00:00');
        $this->creerRolesPlanning();
        $this->notifier = new PlanningGenerationNotifier();
    }

    private function resultat(): array
    {
        return ['jours_generes' => 4, 'non_assignes' => 1, 'duree_ms' => 3.0];
    }

    private function generationManuelle(): void
    {
        $this->notifier->notifierGenerationManuelle(Carbon::parse('2026-10-02'), 2, $this->resultat());
    }

    // ── Destinataires ─────────────────────────────────────────────────────

    public function test_previent_les_admins_et_les_gestionnaires_et_eux_seuls(): void
    {
        Notification::fake();
        $admin = Personne::factory()->admin()->create();
        $gestionnaire = Personne::factory()->gestionnaire()->create();
        $membre = Personne::factory()->membre()->create();
        $benevole = Personne::factory()->benevole()->create();
        $sansRole = Personne::factory()->create();

        $this->generationManuelle();

        Notification::assertSentTo([$admin, $gestionnaire], PlanningGenereNotification::class);
        Notification::assertNotSentTo([$membre, $benevole, $sansRole], PlanningGenereNotification::class);
        Notification::assertCount(2);
    }

    public function test_une_personne_admin_et_gestionnaire_ne_recoit_qu_un_email(): void
    {
        Notification::fake();
        $double = Personne::factory()->admin()->gestionnaire()->create();

        $this->generationManuelle();

        Notification::assertSentToTimes($double, PlanningGenereNotification::class, 1);
    }

    public function test_les_comptes_non_valides_ou_sans_adresse_sont_ignores(): void
    {
        Notification::fake();
        $suspendu = Personne::factory()->admin()->suspendu()->create();
        $enAttente = Personne::factory()->gestionnaire()->enAttente()->create();
        $archive = Personne::factory()->admin()->archive()->create();
        $sansAdresse = Personne::factory()->admin()->create(['email' => '']);
        $actif = Personne::factory()->admin()->create();

        $this->generationManuelle();

        Notification::assertSentTo($actif, PlanningGenereNotification::class);
        Notification::assertNotSentTo([$suspendu, $enAttente, $archive, $sansAdresse], PlanningGenereNotification::class);
    }

    public function test_sans_destinataire_rien_n_est_envoye_et_rien_ne_plante(): void
    {
        Notification::fake();

        $this->generationManuelle();

        Notification::assertNothingSent();
    }

    // ── Contexte transmis ─────────────────────────────────────────────────

    public function test_une_regeneration_automatique_transmet_la_periode_et_le_detail(): void
    {
        Personne::factory()->admin()->create(['email' => 'admin@example.test']);

        $this->notifier->notifierRegeneration(PlanningGenereNotification::DECLENCHEUR_ABSENCE, 'l\'absence de Awa Diallo', [
            'resultat' => $this->resultat(),
            'dateDebutRegen' => '2026-10-02',
            'semaines' => 3,
            'regenererDepuis' => Carbon::parse('2026-10-09'),
        ]);

        $mail = $this->mails()->sole();
        $this->assertSame('admin@example.test', $mail['to']);
        $this->assertStringContainsString('Planning régénéré', $mail['subject']);
        // Début = « regenererDepuis » (9 oct.), fin = samedi de la 3e semaine à partir du vendredi 2 oct. (17 oct.).
        $this->assertStringContainsString('9 oct.', $mail['subject']);
        $this->assertStringContainsString('17 oct. 2026', $mail['subject']);
    }

    public function test_la_periode_d_une_generation_manuelle_va_du_vendredi_au_samedi_de_la_derniere_semaine(): void
    {
        Personne::factory()->admin()->create();

        $this->notifier->notifierGenerationManuelle(Carbon::parse('2026-10-02'), 2, $this->resultat());

        $sujet = $this->mails()->sole()['subject'];
        $this->assertStringContainsString('2 oct.', $sujet);
        $this->assertStringContainsString('10 oct. 2026', $sujet, '2 semaines : vendredi 2 → samedi 10');
    }

    // ── L'envoi ne fait jamais échouer l'appelant ─────────────────────────

    public function test_un_echec_d_envoi_est_journalise_et_ne_remonte_pas(): void
    {
        TransportMailCasse::activer();
        // Capture des erreurs journalisées via l'événement du logger Laravel (pas de Mockery :
        // il n'est pas installé dans ce projet, donc ni Log::spy() ni shouldReceive()).
        $erreurs = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$erreurs) {
            if ($e->level === 'error') {
                $erreurs[] = $e->context;
            }
        });
        $admins = Personne::factory()->admin()->count(2)->create();

        $this->generationManuelle(); // ne doit pas lever

        // Un échec par destinataire : le premier en échec n'empêche pas d'essayer le second.
        $this->assertCount(2, $erreurs);
        $this->assertEqualsCanonicalizing($admins->pluck('id')->all(), array_column($erreurs, 'destinataire_id'));
        $this->assertStringContainsString('SMTP indisponible', $erreurs[0]['erreur']);
    }
}
