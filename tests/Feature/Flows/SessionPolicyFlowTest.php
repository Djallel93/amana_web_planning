<?php
// tests/Feature/Flows/SessionPolicyFlowTest.php
//
// Durée de session : déconnexion après N minutes d'INACTIVITÉ (paramètre admin
// `session_lifetime`) ou, si « Rester connecté jusqu'à minuit » est coché, au prochain
// minuit (Europe/Paris). Voir App\Http\Middleware\ApplySessionPolicy et
// App\Services\SessionPolicy.
//
// Les sessions sont fabriquées avec withSession() : actingAs() ne passe pas par le
// formulaire de connexion, donc n'écrit aucun état de session — on le pose à la main.
//
// Repères : « maintenant » = mardi 2026-10-06 10:00 UTC (12:00 à Paris, heure d'été) ;
// le prochain minuit à Paris est le 2026-10-07 00:00 (= 2026-10-06 22:00 UTC).

declare(strict_types=1);

namespace Tests\Feature\Flows;

use Amana\Shared\Models\AuditLog;
use App\Http\Middleware\ApplySessionPolicy;
use App\Models\Personne;
use App\Services\SessionPolicy;
use Carbon\Carbon;
use Database\Factories\PersonneFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ConnecteParRole;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\Support\FauxSettings;
use Tests\TestCase;

class SessionPolicyFlowTest extends TestCase
{
    use ConnecteParRole;
    use RefreshesBothDatabases;

    private const MINUIT_PARIS = '2026-10-07 00:00:00';

    private Personne $personne;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-06 10:00:00');
        $this->personne = Personne::factory()->create();
    }

    protected function tearDown(): void
    {
        FauxSettings::reinitialiser();
        parent::tearDown();
    }

    private function etatInactivite(int $minutesAuparavant): array
    {
        return [ApplySessionPolicy::SESSION_KEY => [
            'mode' => 'inactivite',
            'derniere_activite' => Carbon::now()->subMinutes($minutesAuparavant)->getTimestamp(),
        ]];
    }

    private function etatMinuit(): array
    {
        return [ApplySessionPolicy::SESSION_KEY => [
            'mode' => 'minuit',
            'expire_a' => Carbon::parse(self::MINUIT_PARIS, 'Europe/Paris')->getTimestamp(),
        ]];
    }

    private function page(array $session)
    {
        return $this->actingAs($this->personne)->withSession($session)->get(route('guide.index'));
    }

    // ── Inactivité ────────────────────────────────────────────────────────

    public function test_une_session_active_depuis_moins_du_delai_reste_ouverte_et_est_rafraichie(): void
    {
        $this->page($this->etatInactivite(119))->assertOk();

        $this->assertAuthenticatedAs($this->personne);
        $this->assertSame(Carbon::now()->getTimestamp(), session(ApplySessionPolicy::SESSION_KEY)['derniere_activite']);
    }

    public function test_une_session_inactive_depuis_plus_du_delai_par_defaut_est_deconnectee(): void
    {
        $this->page($this->etatInactivite(121))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Votre session a expiré. Veuillez vous reconnecter.');

        $this->assertGuest();
    }

    public function test_la_deconnexion_automatique_est_journalisee(): void
    {
        $this->page($this->etatInactivite(121));

        $this->assertSame(1, AuditLog::where('module', 'auth')->where('action', 'logout')->where('after->motif', 'session_expiree')->count());
    }

    public function test_le_delai_vient_du_parametre_admin(): void
    {
        FauxSettings::definir(['session_lifetime' => 10]);

        $this->page($this->etatInactivite(9))->assertOk();
        $this->assertAuthenticatedAs($this->personne);

        $this->page($this->etatInactivite(11))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** @return array<string, array{mixed, int}> valeur lue en base → délai appliqué (minutes) */
    public static function valeursDuParametre(): array
    {
        return [
            'absent → défaut' => [null, 120],
            'valide' => [45, 45],
            'trop petit → plancher' => [1, 5],
            'zéro → plancher' => [0, 5],
            'trop grand → plafond 24 h' => [99999, 1440],
            'texte → défaut' => ['abc', 120],
            'chaîne numérique' => ['30', 30],
        ];
    }

    #[DataProvider('valeursDuParametre')]
    public function test_la_duree_est_bornee_et_a_un_defaut(mixed $valeur, int $attendu): void
    {
        FauxSettings::definir(['session_lifetime' => $valeur]);

        $this->assertSame($attendu, (new SessionPolicy())->dureeInactiviteMinutes());
    }

    public function test_une_session_ouverte_avant_le_deploiement_demarre_en_mode_inactivite(): void
    {
        $this->actingAs($this->personne)->get(route('guide.index'))->assertOk();

        $this->assertSame('inactivite', session(ApplySessionPolicy::SESSION_KEY)['mode']);
        $this->assertSame(Carbon::now()->getTimestamp(), session(ApplySessionPolicy::SESSION_KEY)['derniere_activite']);
    }

    // ── Interrogation de fond (sidebar) ───────────────────────────────────

    public function test_l_interrogation_de_la_sidebar_ne_prolonge_pas_la_session(): void
    {
        $session = $this->etatInactivite(100);
        $avant = $session[ApplySessionPolicy::SESSION_KEY]['derniere_activite'];

        $this->actingAs($this->personne)->withSession($session)->getJson(route('nav-badges.index'))->assertOk();

        $this->assertSame($avant, session(ApplySessionPolicy::SESSION_KEY)['derniere_activite'], 'le polling n\'est pas une activité');
    }

    public function test_l_interrogation_de_la_sidebar_d_une_session_expiree_renvoie_401_json(): void
    {
        $this->actingAs($this->personne)->withSession($this->etatInactivite(130))
            ->getJson(route('nav-badges.index'))
            ->assertStatus(401)
            ->assertJson(['message' => 'Votre session a expiré. Veuillez vous reconnecter.']);

        $this->assertGuest();
    }

    // ── « Rester connecté jusqu'à minuit » ────────────────────────────────

    public function test_une_session_jusqu_a_minuit_n_a_pas_de_delai_d_inactivite(): void
    {
        FauxSettings::definir(['session_lifetime' => 5]);

        $this->page($this->etatMinuit())->assertOk();

        $this->assertAuthenticatedAs($this->personne);
    }

    public function test_une_session_jusqu_a_minuit_expire_au_minuit_de_paris(): void
    {
        $this->travelTo('2026-10-06 21:59:00'); // 23:59 à Paris
        $this->page($this->etatMinuit())->assertOk();

        $this->travelTo('2026-10-06 22:00:01'); // 00:00:01 à Paris
        $this->page($this->etatMinuit())->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_la_connexion_avec_la_case_cochee_pose_une_echeance_a_minuit_et_un_cookie_court(): void
    {
        $reponse = $this->post(route('login.submit'), ['email' => $this->personne->email, 'password' => PersonneFactory::MOT_DE_PASSE, 'remember' => '1']);

        $minuit = Carbon::parse(self::MINUIT_PARIS, 'Europe/Paris')->getTimestamp();
        $etat = session(ApplySessionPolicy::SESSION_KEY);
        $this->assertSame('minuit', $etat['mode']);
        $this->assertSame($minuit, $etat['expire_a']);

        $recaller = collect($reponse->headers->getCookies())->first(fn($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($recaller, 'cookie « se souvenir de moi » émis');
        $this->assertGreaterThan(Carbon::now()->getTimestamp(), $recaller->getExpiresTime());
        $this->assertLessThanOrEqual($minuit + 60, $recaller->getExpiresTime(), 'le cookie ne survit pas à minuit (et non 5 ans)');
    }

    public function test_la_connexion_sans_la_case_pose_le_mode_inactivite_et_aucun_cookie_persistant(): void
    {
        $reponse = $this->post(route('login.submit'), ['email' => $this->personne->email, 'password' => PersonneFactory::MOT_DE_PASSE]);

        $etat = session(ApplySessionPolicy::SESSION_KEY);
        $this->assertSame('inactivite', $etat['mode']);
        $this->assertSame(Carbon::now()->getTimestamp(), $etat['derniere_activite']);
        $this->assertNull(collect($reponse->headers->getCookies())->first(fn($c) => str_starts_with($c->getName(), 'remember_web_')));
    }

    public function test_une_connexion_refusee_ne_pose_aucun_etat(): void
    {
        $this->post(route('login.submit'), ['email' => $this->personne->email, 'password' => 'mauvais-mot-de-passe', 'remember' => '1']);

        $this->assertGuest();
        $this->assertNull(session(ApplySessionPolicy::SESSION_KEY));
    }

    // ── Durée du stockage de session ──────────────────────────────────────

    public function test_le_stockage_de_session_vit_au_moins_24_heures(): void
    {
        $this->assertGreaterThanOrEqual(1440, (int) config('session.lifetime'));
    }

    // ── Paramètre dans /parametres (réservé aux admins, borné) ────────────

    public function test_la_page_parametres_valide_les_bornes_de_la_duree(): void
    {
        foreach (['admin', 'gestionnaire', 'membre', 'benevole'] as $code) {
            PersonneFactory::role($code);
        }
        $this->connecterEn('admin');

        foreach (['2', '1441', 'abc', ''] as $valeur) {
            $this->post(route('settings.update'), ['settings' => ['session_lifetime' => $valeur]])
                ->assertSessionHasErrors('settings.session_lifetime');
        }

        $this->post(route('settings.update'), ['settings' => ['session_lifetime' => '480']])
            ->assertSessionDoesntHaveErrors('settings.session_lifetime');
    }
}
