<?php
// tests/Feature/Services/InvitationLinkServiceTest.php
//
// Le service crée un vrai jeton dans password_reset_tokens (broker 'personnes',
// base amana_commun) et renvoie l'URL de la page « nouveau mot de passe » qui
// le porte. La validité du jeton (clic sur le lien → choix du mot de passe)
// est couverte de bout en bout par CandidatureFlowTest.

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Personne;
use App\Services\InvitationLinkService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class InvitationLinkServiceTest extends TestCase
{
    use RefreshesBothDatabases;

    public function test_l_url_porte_le_jeton_et_l_email_de_la_personne(): void
    {
        $p = Personne::factory()->enAttente()->create();

        $url = app(InvitationLinkService::class)->urlDefinitionMotDePasse($p);

        $this->assertStringStartsWith(url('/nouveau-mot-de-passe/'), $url);
        $this->assertStringContainsString('email=' . urlencode($p->email), $url);
    }

    public function test_un_jeton_est_enregistre_pour_la_personne(): void
    {
        $p = Personne::factory()->enAttente()->create();

        $url = app(InvitationLinkService::class)->urlDefinitionMotDePasse($p);

        preg_match('#/nouveau-mot-de-passe/([A-Za-z0-9]+)#', $url, $m);
        $this->assertNotEmpty($m[1] ?? null, 'un jeton figure dans l\'URL');
        $this->assertSame(1, DB::connection('commun')->table('password_reset_tokens')->where('email', $p->email)->count());
    }
}
