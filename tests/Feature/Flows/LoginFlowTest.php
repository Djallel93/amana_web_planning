<?php
// tests/Feature/Flows/LoginFlowTest.php
//
// Connexion / déconnexion de bout en bout (AuthController, amana_shared). Le statut du compte
// n'est contrôlé QU'ici (voir AccountStateAccessTest pour l'autre moitié).

declare(strict_types=1);

namespace Tests\Feature\Flows;

use Amana\Shared\Models\AuditLog;
use App\Models\Personne;
use Database\Factories\PersonneFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class LoginFlowTest extends TestCase
{
    use RefreshesBothDatabases;

    private const MDP = PersonneFactory::MOT_DE_PASSE;

    private function identifiants(Personne $p, ?string $mdp = null): array
    {
        return ['email' => $p->email, 'password' => $mdp ?? self::MDP];
    }

    // ── Formulaire ────────────────────────────────────────────────────────

    public function test_le_formulaire_de_connexion_s_affiche_pour_un_invite(): void
    {
        $this->get(route('login'))->assertOk()->assertViewIs('amana-shared::auth.login');
    }

    public function test_un_compte_deja_connecte_est_renvoye_vers_l_accueil(): void
    {
        $this->actingAs(Personne::factory()->create())->get(route('login'))->assertRedirect(route('planning.index'));
    }

    // ── Succès ────────────────────────────────────────────────────────────

    public function test_une_connexion_reussie_ouvre_la_session_et_souhaite_la_bienvenue(): void
    {
        $p = Personne::factory()->create(['prenom' => 'Khadija']);

        $this->post(route('login.submit'), $this->identifiants($p))
            ->assertRedirect(route('planning.index'))
            ->assertSessionHas('success', 'Bienvenue Khadija !');

        $this->assertAuthenticatedAs($p);
    }

    public function test_une_connexion_reussie_est_journalisee(): void
    {
        $p = Personne::factory()->create();

        $this->post(route('login.submit'), $this->identifiants($p));

        $entree = AuditLog::where('module', 'auth')->where('action', 'login')->firstOrFail();
        $this->assertSame($p->id, (int) $entree->user_id);
    }

    /**
     * CARACTÉRISATION : après connexion on arrive TOUJOURS sur l'accueil, jamais sur la page
     * qu'on avait demandée. AuthController fait `redirect()->intended(...)`, mais
     * EnsureAuthenticated renvoie les invités avec `redirect()->route('login')` au lieu de
     * `redirect()->guest(route('login'))` : l'URL d'origine n'est donc jamais mémorisée et
     * `intended()` retombe sur son défaut. Un lien reçu par e-mail (ex. /mon-planning) fait
     * perdre la destination à quiconque doit d'abord se connecter.
     */
    public function test_apres_connexion_la_page_demandee_n_est_pas_retrouvee(): void
    {
        $p = Personne::factory()->create();

        $this->get(route('mon-planning'))->assertRedirect(route('login'));
        $this->assertNull(session('url.intended'));
        $this->post(route('login.submit'), $this->identifiants($p))->assertRedirect(route('planning.index'));
    }

    public function test_une_url_d_origine_memorisee_est_respectee_par_la_connexion(): void
    {
        $p = Personne::factory()->create();

        $this->withSession(['url.intended' => route('mon-planning')])
            ->post(route('login.submit'), $this->identifiants($p))
            ->assertRedirect(route('mon-planning'));
    }

    public function test_se_souvenir_de_moi_pose_le_cookie_de_longue_duree(): void
    {
        $p = Personne::factory()->create();

        $reponse = $this->post(route('login.submit'), $this->identifiants($p) + ['remember' => '1']);
        $sans = $this->post(route('logout'))->getCookie('dummy');

        $cookies = collect($reponse->headers->getCookies())->map->getName();
        $this->assertTrue($cookies->contains(fn($n) => str_starts_with($n, 'remember_web_')), 'cookie « remember_web_… » attendu');
        $this->assertNull($sans);
    }

    public function test_sans_se_souvenir_de_moi_pas_de_cookie_de_longue_duree(): void
    {
        $p = Personne::factory()->create();

        $reponse = $this->post(route('login.submit'), $this->identifiants($p));

        $this->assertFalse(collect($reponse->headers->getCookies())->map->getName()->contains(fn($n) => str_starts_with($n, 'remember_web_')));
    }

    public function test_la_connexion_ne_depend_pas_du_role(): void
    {
        foreach (['benevole', 'membre', 'gestionnaire', 'admin'] as $role) {
            $p = Personne::factory()->{$role}()->create();

            $this->post(route('login.submit'), $this->identifiants($p))->assertRedirect(route('planning.index'));
            $this->post(route('logout'));
        }
    }

    // ── Échecs ────────────────────────────────────────────────────────────

    public function test_un_mauvais_mot_de_passe_est_refuse(): void
    {
        $p = Personne::factory()->create();

        $this->from(route('login'))->post(route('login.submit'), $this->identifiants($p, 'pas-le-bon-mdp'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Email ou mot de passe incorrect.'])
            ->assertSessionHasInput('email', $p->email);

        $this->assertGuest();
    }

    public function test_un_email_inconnu_recoit_le_meme_message_qu_un_mauvais_mot_de_passe(): void
    {
        $this->post(route('login.submit'), ['email' => 'inconnu@example.test', 'password' => 'quelconque-1'])
            ->assertSessionHasErrors(['email' => 'Email ou mot de passe incorrect.']);

        $this->assertGuest();
    }

    public function test_le_mot_de_passe_n_est_jamais_rendu_dans_le_formulaire_reaffiche(): void
    {
        $p = Personne::factory()->create();

        $this->post(route('login.submit'), $this->identifiants($p, 'pas-le-bon-mdp'))->assertSessionDoesntHaveErrors('password');

        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_la_validation_des_champs(): void
    {
        $this->post(route('login.submit'), [])->assertSessionHasErrors([
            'email' => 'L\'adresse email est obligatoire.',
            'password' => 'Le mot de passe est obligatoire.',
        ]);
        $this->post(route('login.submit'), ['email' => 'pas-un-email', 'password' => 'abcdef'])
            ->assertSessionHasErrors(['email' => 'Format d\'email invalide.']);
        $this->post(route('login.submit'), ['email' => 'a@example.test', 'password' => 'court'])->assertSessionHasErrors('password');
    }

    /** @return array<string, array{string, string}> */
    public static function statutsRefuses(): array
    {
        return [
            'en attente' => ['enAttente', 'Votre candidature est en attente de validation par un administrateur.'],
            'suspendu' => ['suspendu', 'Votre compte a été suspendu. Contactez un administrateur.'],
            'archivé' => ['archive', 'Ce compte est archivé.'],
        ];
    }

    #[DataProvider('statutsRefuses')]
    public function test_un_compte_dont_le_statut_est_bloque_ne_peut_pas_se_connecter_meme_avec_le_bon_mot_de_passe(string $etat, string $message): void
    {
        $p = Personne::factory()->{$etat}()->create();

        $this->post(route('login.submit'), $this->identifiants($p))->assertSessionHasErrors(['email' => $message]);

        $this->assertGuest();
    }

    public function test_un_compte_sans_mot_de_passe_est_invite_a_le_creer(): void
    {
        $p = Personne::factory()->sansMotDePasse()->create();

        $this->post(route('login.submit'), $this->identifiants($p))
            ->assertSessionHasErrors(['email' => 'Vous n\'avez pas encore créé votre mot de passe. Vérifiez vos emails ou contactez un administrateur.']);

        $this->assertGuest();
    }

    /**
     * CARACTÉRISATION (énumération de comptes) : pour une adresse INCONNUE ou un mauvais mot de
     * passe, le message est générique ; mais pour une adresse CONNUE, on distingue « en attente »,
     * « suspendu », « archivé » et « mot de passe pas encore créé » — SANS avoir besoin du mot de
     * passe. Quiconque connaît ou devine une adresse apprend si elle a un compte et son statut.
     */
    public function test_le_statut_d_un_compte_est_revele_sans_connaitre_son_mot_de_passe(): void
    {
        $suspendu = Personne::factory()->suspendu()->create();
        $inconnu = 'personne-ne-connait-cette-adresse@example.test';

        $this->post(route('login.submit'), ['email' => $suspendu->email, 'password' => 'un-mauvais-mdp'])
            ->assertSessionHasErrors(['email' => 'Votre compte a été suspendu. Contactez un administrateur.']);
        $this->post(route('login.submit'), ['email' => $inconnu, 'password' => 'un-mauvais-mdp'])
            ->assertSessionHasErrors(['email' => 'Email ou mot de passe incorrect.']);
    }

    // ── Déconnexion ───────────────────────────────────────────────────────

    public function test_la_deconnexion_ferme_la_session_et_est_journalisee(): void
    {
        $p = Personne::factory()->create();
        $this->post(route('login.submit'), $this->identifiants($p));

        $this->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Vous avez été déconnecté.');

        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('module', 'auth')->where('action', 'logout')->count());
        $this->get(route('planning.index'))->assertRedirect(route('login'));
    }

    public function test_se_deconnecter_sans_etre_connecte_ne_plante_pas(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_la_deconnexion_est_en_post_seulement(): void
    {
        $this->get('/logout')->assertStatus(405);
    }
}
