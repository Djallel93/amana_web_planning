<?php
// tests/Feature/Flows/PasswordResetFlowTest.php
//
// Mot de passe oublié → e-mail avec jeton → nouveau mot de passe → connexion. Les e-mails sont
// interceptés (Notification::fake()) ; le jeton est relu dans l'URL du bouton du message.

declare(strict_types=1);

namespace Tests\Feature\Flows;

use Amana\Shared\Notifications\PasswordChangedNotification;
use Amana\Shared\Notifications\ResetPasswordNotification;
use App\Models\Personne;
use Database\Factories\PersonneFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshesBothDatabases;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** Demande un lien pour `$p` et renvoie le jeton lu dans le message envoyé. */
    private function demanderEtLireLeJeton(Personne $p): string
    {
        $this->post(route('password.email'), ['email' => $p->email])->assertSessionHas('success');

        $jeton = null;
        Notification::assertSentTo($p, ResetPasswordNotification::class, function ($n) use ($p, &$jeton) {
            // `url` est une propriété privée du message : pas d'accesseur public.
            $url = (new \ReflectionProperty($n, 'url'))->getValue($n);
            preg_match('#/nouveau-mot-de-passe/([A-Za-z0-9]+)#', $url, $m);
            $jeton = $m[1] ?? null;

            return true;
        });
        $this->assertNotNull($jeton, 'le message contient un lien avec jeton');

        return $jeton;
    }

    private function reinitialiser(Personne $p, string $jeton, string $mdp = 'NouveauMdp-2026', array $surcharge = [])
    {
        return $this->post(route('password.update'), array_replace([
            'token' => $jeton, 'email' => $p->email, 'password' => $mdp, 'password_confirmation' => $mdp,
        ], $surcharge));
    }

    // ── Demande de lien ───────────────────────────────────────────────────

    public function test_les_formulaires_s_affichent_pour_un_invite_et_renvoient_un_connecte_a_l_accueil(): void
    {
        $this->get(route('password.request'))->assertOk()->assertViewIs('amana-shared::auth.forgot-password');
        $this->actingAs(Personne::factory()->create())->get(route('password.request'))->assertRedirect(route('planning.index'));
    }

    public function test_une_adresse_connue_recoit_le_lien(): void
    {
        $p = Personne::factory()->create();

        $this->post(route('password.email'), ['email' => $p->email])
            ->assertSessionHas('success', 'Un lien de réinitialisation a été envoyé à votre adresse email.');

        Notification::assertSentToTimes($p, ResetPasswordNotification::class, 1);
    }

    public function test_une_adresse_inconnue_ne_recoit_rien(): void
    {
        $this->post(route('password.email'), ['email' => 'inconnu@example.test'])
            ->assertSessionHas('success', 'Si cette adresse est connue, un lien vous a été envoyé.');

        Notification::assertNothingSent();
    }

    /**
     * CARACTÉRISATION (énumération de comptes) : le message de succès n'est PAS le même pour une
     * adresse connue (« Un lien … a été envoyé ») et inconnue (« Si cette adresse est connue… »), et
     * une seconde demande rapprochée sur une adresse connue déclenche un message de limitation
     * qu'une adresse inconnue n'obtient jamais. Ces deux différences révèlent l'existence d'un compte.
     */
    public function test_les_reponses_different_selon_que_l_adresse_existe(): void
    {
        $p = Personne::factory()->create();

        $this->post(route('password.email'), ['email' => $p->email])
            ->assertSessionHas('success', 'Un lien de réinitialisation a été envoyé à votre adresse email.');
        $this->post(route('password.email'), ['email' => 'inconnu@example.test'])
            ->assertSessionHas('success', 'Si cette adresse est connue, un lien vous a été envoyé.');

        $this->post(route('password.email'), ['email' => $p->email])->assertSessionHasErrors(['email' => 'Veuillez patienter avant de demander un nouveau lien.']);
        $this->post(route('password.email'), ['email' => 'inconnu@example.test'])->assertSessionDoesntHaveErrors('email');
    }

    public function test_une_seconde_demande_rapprochee_est_limitee_et_n_envoie_rien(): void
    {
        $p = Personne::factory()->create();
        $this->post(route('password.email'), ['email' => $p->email]);

        $this->post(route('password.email'), ['email' => $p->email])
            ->assertSessionHasErrors(['email' => 'Veuillez patienter avant de demander un nouveau lien.']);

        Notification::assertSentToTimes($p, ResetPasswordNotification::class, 1);
    }

    public function test_la_demande_est_a_nouveau_possible_apres_le_delai(): void
    {
        $p = Personne::factory()->create();
        $this->post(route('password.email'), ['email' => $p->email]);
        $this->travel((int) config('auth.passwords.personnes.throttle', 60) + 1)->seconds();

        $this->post(route('password.email'), ['email' => $p->email])->assertSessionHas('success');

        Notification::assertSentToTimes($p, ResetPasswordNotification::class, 2);
    }

    public function test_la_demande_valide_l_adresse(): void
    {
        $this->post(route('password.email'), [])->assertSessionHasErrors(['email' => 'L\'adresse email est obligatoire.']);
        $this->post(route('password.email'), ['email' => 'pas-un-email'])->assertSessionHasErrors(['email' => 'Format d\'email invalide.']);
    }

    // ── Nouveau mot de passe ──────────────────────────────────────────────

    public function test_la_page_de_reinitialisation_reprend_le_jeton_et_l_adresse_du_lien(): void
    {
        $this->get(route('password.reset', ['token' => 'abc123', 'email' => 'a@example.test']))
            ->assertOk()
            ->assertViewIs('amana-shared::auth.reset-password')
            ->assertViewHas('token', 'abc123')
            ->assertViewHas('email', 'a@example.test');
    }

    public function test_de_bout_en_bout_demande_reinitialisation_puis_connexion_avec_le_nouveau_mot_de_passe(): void
    {
        $p = Personne::factory()->create();
        $jeton = $this->demanderEtLireLeJeton($p);

        $this->reinitialiser($p, $jeton, 'NouveauMdp-2026')
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.');

        $this->assertTrue(Hash::check('NouveauMdp-2026', $p->fresh()->password));
        $this->post(route('login.submit'), ['email' => $p->email, 'password' => PersonneFactory::MOT_DE_PASSE])
            ->assertSessionHasErrors('email');
        $this->post(route('login.submit'), ['email' => $p->email, 'password' => 'NouveauMdp-2026'])->assertRedirect(route('planning.index'));
        $this->assertAuthenticatedAs($p);
    }

    public function test_un_message_de_confirmation_est_envoye_a_l_adresse_du_compte(): void
    {
        $p = Personne::factory()->create();
        $this->reinitialiser($p, $this->demanderEtLireLeJeton($p));

        Notification::assertSentOnDemand(PasswordChangedNotification::class, fn($n, $canaux, AnonymousNotifiable $dest) => $dest->routes['mail'] === $p->email);
    }

    public function test_un_jeton_ne_sert_qu_une_fois(): void
    {
        $p = Personne::factory()->create();
        $jeton = $this->demanderEtLireLeJeton($p);
        $this->reinitialiser($p, $jeton, 'PremierMdp-2026')->assertSessionHas('success');

        $this->reinitialiser($p, $jeton, 'DeuxiemeMdp-2026')
            ->assertSessionHasErrors(['email' => 'Ce lien de réinitialisation est invalide ou a expiré.']);

        $this->assertTrue(Hash::check('PremierMdp-2026', $p->fresh()->password));
    }

    public function test_un_jeton_inconnu_ou_pour_une_autre_adresse_est_refuse(): void
    {
        $p = Personne::factory()->create();
        $autre = Personne::factory()->create();
        $jeton = $this->demanderEtLireLeJeton($p);

        $this->reinitialiser($p, 'jeton-invente')->assertSessionHasErrors(['email' => 'Ce lien de réinitialisation est invalide ou a expiré.']);
        $this->reinitialiser($autre, $jeton)->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(PersonneFactory::MOT_DE_PASSE, $p->fresh()->password), 'rien n\'a changé');
        $this->assertTrue(Hash::check(PersonneFactory::MOT_DE_PASSE, $autre->fresh()->password));
    }

    public function test_un_jeton_expire_apres_la_duree_configuree(): void
    {
        $p = Personne::factory()->create();
        $jeton = $this->demanderEtLireLeJeton($p);
        $this->travel((int) config('auth.passwords.personnes.expire', 60) + 1)->minutes();

        $this->reinitialiser($p, $jeton)->assertSessionHasErrors(['email' => 'Ce lien de réinitialisation est invalide ou a expiré.']);

        $this->assertTrue(Hash::check(PersonneFactory::MOT_DE_PASSE, $p->fresh()->password));
    }

    public function test_le_nouveau_mot_de_passe_est_valide(): void
    {
        $p = Personne::factory()->create();
        $jeton = $this->demanderEtLireLeJeton($p);

        $this->reinitialiser($p, $jeton, 'court')->assertSessionHasErrors(['password' => 'Le mot de passe doit contenir au moins 8 caractères.']);
        $this->reinitialiser($p, $jeton, 'NouveauMdp-2026', ['password_confirmation' => 'different-de-lautre'])
            ->assertSessionHasErrors(['password' => 'La confirmation ne correspond pas au mot de passe.']);
        $this->post(route('password.update'), [])->assertSessionHasErrors(['token', 'email', 'password']);

        $this->assertTrue(Hash::check(PersonneFactory::MOT_DE_PASSE, $p->fresh()->password));
    }

    public function test_une_erreur_de_validation_ne_consomme_pas_le_jeton(): void
    {
        $p = Personne::factory()->create();
        $jeton = $this->demanderEtLireLeJeton($p);
        $this->reinitialiser($p, $jeton, 'court');

        $this->reinitialiser($p, $jeton, 'NouveauMdp-2026')->assertSessionHas('success');
    }

    public function test_un_compte_invite_sans_mot_de_passe_le_cree_par_ce_meme_parcours(): void
    {
        $p = Personne::factory()->sansMotDePasse()->create();
        $jeton = $this->demanderEtLireLeJeton($p);

        $this->reinitialiser($p, $jeton, 'MonPremierMdp-2026')->assertSessionHas('success');

        $this->post(route('login.submit'), ['email' => $p->email, 'password' => 'MonPremierMdp-2026'])->assertRedirect(route('planning.index'));
    }
}
