<?php
// tests/Feature/Authorization/AccountStateAccessTest.php
//
// Le statut d'un compte (En attente / Suspendu / Archivé) est contrôlé à la CONNEXION
// (AuthController::login) — voir LoginFlowTest. Ce fichier regarde l'autre moitié : que se passe-t-il
// pour une session déjà ouverte quand le statut change ensuite ?

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\Personne;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class AccountStateAccessTest extends TestCase
{
    use RefreshesBothDatabases;

    /** @return array<string, array{string}> */
    public static function statutsBloques(): array
    {
        return ['suspendu' => ['suspendu'], 'archivé' => ['archive'], 'en attente' => ['enAttente']];
    }

    /**
     * CARACTÉRISATION (sécurité) : EnsureAuthenticated ne teste que Auth::check(). Un compte
     * suspendu/archivé APRÈS sa connexion garde l'accès complet jusqu'à l'expiration de sa session
     * (ou jusqu'à la déconnexion). Suspendre quelqu'un ne le déconnecte donc pas. Si ce n'est pas
     * voulu : vérifier le statut dans le middleware, ou invalider ses sessions à la suspension.
     */
    #[DataProvider('statutsBloques')]
    public function test_un_compte_dont_le_statut_est_bloque_garde_l_acces_avec_une_session_deja_ouverte(string $etat): void
    {
        $personne = Personne::factory()->{$etat}()->membre()->create();

        $this->actingAs($personne)->get(route('planning.index'))->assertOk();
        $this->actingAs($personne)->get(route('bilan.index'))->assertOk();
    }
}
