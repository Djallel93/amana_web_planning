<?php
// tests/Feature/Authorization/RateLimitTest.php
//
// Les limites posées par `throttle:N,1` dans routes/web.php. Le limiteur est par IP et par
// route ; chaque test repart d'un cache vide (application recréée).

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\Personne;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ConnecteParRole;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshesBothDatabases;
    use ConnecteParRole;

    /** Nombre de requêtes qui passent, puis la suivante reçoit 429. */
    private function assertLimite(int $autorisees, callable $requete): void
    {
        for ($i = 1; $i <= $autorisees; $i++) {
            $this->assertNotSame(429, $requete()->getStatusCode(), "requête n°{$i} : ne doit pas être limitée");
        }
        $this->assertSame(429, $requete()->getStatusCode(), 'la requête suivante doit être limitée');
    }

    public function test_la_connexion_est_limitee_a_dix_tentatives_par_minute(): void
    {
        $this->assertLimite(10, fn() => $this->post(route('login.submit'), ['email' => 'x@example.test', 'password' => 'mauvais-mdp']));
    }

    public function test_l_inscription_est_limitee_a_cinq_envois_par_minute(): void
    {
        Notification::fake();

        $this->assertLimite(5, fn() => $this->post(route('inscription.submit'), []));
    }

    public function test_l_affichage_du_formulaire_d_inscription_est_limite_a_vingt_par_minute(): void
    {
        $this->assertLimite(20, fn() => $this->get(route('inscription')));
    }

    public function test_les_liens_a_jeton_sont_limites_a_dix_par_minute(): void
    {
        $this->assertLimite(10, fn() => $this->get(route('echanges.accepter', str_repeat('a', 64))));
    }

    /**
     * CARACTÉRISATION : `throttle:N,1` (forme numérique) construit sa clé à partir de l'IP (ou de
     * l'utilisateur connecté) SEULEMENT — pas de la route. Toutes les routes ainsi limitées
     * partagent donc UN compteur par IP, chacune le comparant à SON plafond. Conséquences :
     * épuiser les liens à jeton bloque aussi /connexion ; deux personnes derrière la même IP
     * publique (wifi de la mosquée) se partagent le budget de /connexion, /inscription et des
     * liens d'échange. Corrigeable avec un préfixe : `throttle:10,1,login`.
     */
    public function test_les_compteurs_de_throttle_sont_partages_entre_routes(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get(route('echanges.accepter', str_repeat('a', 64)));
        }

        $this->post(route('login.submit'), ['email' => 'x@example.test', 'password' => 'mauvais-mdp'])->assertStatus(429);
        $this->get(route('echanges.refuser', str_repeat('a', 64)))->assertStatus(429);
    }

    public function test_le_lien_de_reinitialisation_admin_est_limite_a_cinq_par_minute(): void
    {
        Notification::fake();
        $this->connecterEn('admin');
        $cible = Personne::factory()->create();

        $this->assertLimite(5, fn() => $this->post(route('personnes.reset-link', $cible->id)));
    }

    public function test_le_changement_d_email_du_profil_est_limite_a_cinq_par_minute(): void
    {
        Notification::fake();
        $this->connecterEn('membre');

        $this->assertLimite(5, fn() => $this->post(route('profile.email.request'), []));
    }

    public function test_le_changement_de_mot_de_passe_du_profil_est_limite_a_cinq_par_minute(): void
    {
        $this->connecterEn('membre');

        $this->assertLimite(5, fn() => $this->put(route('profile.password.update'), []));
    }

    /**
     * CARACTÉRISATION (effet visible) : la barre latérale interroge /nav-badges toutes les 45 s
     * ET à chaque navigation Inertia. Pour un utilisateur connecté, cette route partage son
     * compteur avec le changement de mot de passe et d'e-mail (plafond : 5 par minute).
     * Après cinq pages parcourues en une minute, changer son mot de passe répond 429.
     */
    public function test_la_navigation_consomme_le_budget_du_changement_de_mot_de_passe(): void
    {
        $this->connecterEn('membre');

        for ($i = 0; $i < 5; $i++) {
            $this->get(route('nav-badges.index'))->assertOk();
        }

        $this->put(route('profile.password.update'), [])->assertStatus(429);
    }

    /**
     * CARACTÉRISATION : les deux routes de « mot de passe oublié » n'ont AUCUN throttle
     * (contrairement à /login). Rien n'empêche d'envoyer des milliers de courriels de
     * réinitialisation à une adresse, ni de sonder quelles adresses existent. À vérifier aussi
     * côté infrastructure (WAF / reverse proxy).
     */
    public function test_le_mot_de_passe_oublie_n_est_pas_limite(): void
    {
        Notification::fake();

        for ($i = 0; $i < 25; $i++) {
            $this->assertNotSame(429, $this->post(route('password.email'), ['email' => 'quelquun@example.test'])->getStatusCode());
            $this->assertNotSame(429, $this->post(route('password.update'), [])->getStatusCode());
        }
    }
}
