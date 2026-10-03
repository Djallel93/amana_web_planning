<?php
// tests/Feature/Console/ResetAdminPasswordCommandTest.php
//
// amana:reset-admin — l'outil de dernier recours quand plus personne ne peut se connecter. Lancé
// ici avec --no-interaction : les confirmations prennent leur valeur par défaut (oui). Les refus
// interactifs (« Voulez-vous créer… ? » / « Confirmer… ? » → non) ne sont pas testés : ils exigent
// l'assistant PendingCommand de Laravel, qui dépend de Mockery (absent de ce projet).

declare(strict_types=1);

namespace Tests\Feature\Console;

use Amana\Shared\Notifications\PasswordChangedNotification;
use App\Models\Personne;
use App\Services\RoleService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class ResetAdminPasswordCommandTest extends TestCase
{
    use RefreshesBothDatabases;
    use CreeDonneesPlanning;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function lancer(array $options = []): int
    {
        return Artisan::call('amana:reset-admin', $options + ['--no-interaction' => true]);
    }

    private function role(Personne $p): ?string
    {
        return (new RoleService())->currentRoleCode($p);
    }

    public function test_un_compte_existant_recoit_le_mot_de_passe_fourni_et_redevient_valide(): void
    {
        $this->creerRolesPlanning();
        $admin = Personne::factory()->suspendu()->create(['email' => 'admin@amana.fr']);

        $this->assertSame(0, $this->lancer(['--password' => 'NouveauMdp-2026']));

        $admin->refresh();
        $this->assertTrue(Hash::check('NouveauMdp-2026', $admin->password));
        $this->assertSame('Validé', $admin->statut, 'un compte suspendu est réactivé');
        $this->assertNotNull($admin->email_verified_at);
        $sortie = Artisan::output();
        $this->assertStringContainsString('Mot de passe mis à jour avec succès.', $sortie);
        $this->assertStringContainsString('(fourni manuellement)', $sortie);
        $this->assertStringNotContainsString('NouveauMdp-2026', $sortie, 'le mot de passe fourni n\'est pas réaffiché');
    }

    public function test_l_adresse_par_defaut_est_admin_amana_fr(): void
    {
        $this->creerRolesPlanning();
        $admin = Personne::factory()->create(['email' => 'admin@amana.fr']);
        $autre = Personne::factory()->create(['email' => 'autre@example.test']);
        $mdpAutre = $autre->password;

        $this->lancer(['--password' => 'NouveauMdp-2026']);

        $this->assertTrue(Hash::check('NouveauMdp-2026', $admin->fresh()->password));
        $this->assertSame($mdpAutre, $autre->fresh()->password, 'les autres comptes ne sont pas touchés');
    }

    public function test_l_option_email_cible_un_autre_compte(): void
    {
        $this->creerRolesPlanning();
        $autre = Personne::factory()->create(['email' => 'responsable@example.test']);

        $this->lancer(['--email' => 'responsable@example.test', '--password' => 'NouveauMdp-2026']);

        $this->assertTrue(Hash::check('NouveauMdp-2026', $autre->fresh()->password));
    }

    public function test_le_role_admin_est_attribue_s_il_manque_et_conserve_sinon(): void
    {
        $this->creerRolesPlanning();
        $sansRole = Personne::factory()->create(['email' => 'admin@amana.fr']);

        $this->lancer(['--password' => 'NouveauMdp-2026']);
        $this->assertSame('admin', $this->role($sansRole));
        $this->assertStringContainsString('Rôle admin attribué.', Artisan::output());

        $this->lancer(['--password' => 'AutreMdp-2026-bis']);
        $this->assertSame('admin', $this->role($sansRole));
        $this->assertStringContainsString('Rôle admin déjà attribué — inchangé.', Artisan::output());
    }

    public function test_un_compte_inexistant_est_cree_avec_le_role_admin(): void
    {
        $this->creerRolesPlanning();

        $this->assertSame(0, $this->lancer(['--email' => 'nouveau@example.test', '--password' => 'NouveauMdp-2026']));

        $cree = Personne::where('email', 'nouveau@example.test')->firstOrFail();
        $this->assertSame(['Amana', 'Validé'], [$cree->prenom, $cree->statut], 'prénom « AMANA » mis en forme par le modèle');
        $this->assertTrue(Hash::check('NouveauMdp-2026', $cree->password));
        $this->assertSame('admin', $this->role($cree));
        $this->assertStringContainsString('Aucun compte trouvé avec l\'email : nouveau@example.test', Artisan::output());
    }

    public function test_un_mot_de_passe_genere_est_affiche_une_seule_fois_et_assez_solide(): void
    {
        $this->creerRolesPlanning();
        $admin = Personne::factory()->create(['email' => 'admin@amana.fr']);

        $this->assertSame(0, $this->lancer());

        $sortie = Artisan::output();
        $this->assertStringContainsString('(généré automatiquement)', $sortie);
        $this->assertSame(1, preg_match('/Mot de passe : (\S+)/', $sortie, $m), 'le mot de passe généré figure dans la sortie');
        $genere = $m[1];
        $this->assertTrue(Hash::check($genere, $admin->fresh()->password));
        $this->assertSame(14, strlen($genere));
        $this->assertMatchesRegularExpression('/[A-Z]/', $genere);
        $this->assertMatchesRegularExpression('/[a-z]/', $genere);
        $this->assertMatchesRegularExpression('/[0-9]/', $genere);
        $this->assertMatchesRegularExpression('/[!@#$%^&*]/', $genere);
    }

    public function test_deux_executions_generent_deux_mots_de_passe_differents(): void
    {
        $this->creerRolesPlanning();
        Personne::factory()->create(['email' => 'admin@amana.fr']);

        $this->lancer();
        preg_match('/Mot de passe : (\S+)/', Artisan::output(), $premier);
        $this->lancer();
        preg_match('/Mot de passe : (\S+)/', Artisan::output(), $second);

        $this->assertNotSame($premier[1], $second[1]);
    }

    public function test_un_mot_de_passe_trop_court_est_refuse_sans_rien_modifier(): void
    {
        $admin = Personne::factory()->create(['email' => 'admin@amana.fr']);
        $ancien = $admin->password;

        $this->assertSame(1, $this->lancer(['--password' => 'court']));

        $this->assertStringContainsString('Le mot de passe doit contenir au moins 8 caractères.', Artisan::output());
        $this->assertSame($ancien, $admin->fresh()->password);
    }

    public function test_un_mot_de_passe_trop_court_ne_cree_pas_le_compte(): void
    {
        $this->assertSame(1, $this->lancer(['--email' => 'nouveau@example.test', '--password' => '1234567']));

        $this->assertNull(Personne::where('email', 'nouveau@example.test')->first());
    }

    public function test_huit_caracteres_suffisent(): void
    {
        Personne::factory()->create(['email' => 'admin@amana.fr']);

        $this->assertSame(0, $this->lancer(['--password' => '12345678']));
    }

    public function test_les_sessions_ouvertes_du_compte_sont_invalidees_et_pas_celles_des_autres(): void
    {
        $this->creerRolesPlanning();
        $admin = Personne::factory()->create(['email' => 'admin@amana.fr']);
        $autre = Personne::factory()->create();
        foreach ([['a-1', $admin->id], ['a-2', $admin->id], ['b-1', $autre->id], ['x-1', null]] as [$id, $uid]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $uid, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => time()]);
        }

        $this->lancer(['--password' => 'NouveauMdp-2026']);

        $this->assertSame(['b-1', 'x-1'], DB::table('sessions')->orderBy('id')->pluck('id')->all());
        $this->assertStringContainsString('Sessions actives invalidées', Artisan::output());
    }

    public function test_un_message_de_confirmation_est_envoye_au_compte(): void
    {
        $this->creerRolesPlanning();
        $admin = Personne::factory()->create(['email' => 'admin@amana.fr']);

        $this->lancer(['--password' => 'NouveauMdp-2026']);

        Notification::assertSentOnDemand(PasswordChangedNotification::class, fn($n, $canaux, $dest) => $dest->routes['mail'] === $admin->email);
    }

    /**
     * CARACTÉRISATION : quand l'application « planning » n'est pas enregistrée dans
     * ref_applications (base jamais initialisée), la commande saute silencieusement l'attribution
     * du rôle. Elle annonce « Mot de passe mis à jour avec succès » et affiche les identifiants,
     * mais le compte n'a AUCUN rôle : la personne se connecte et n'a accès à aucune page
     * d'administration — l'outil de récupération ne récupère rien, sans l'avertir.
     */
    public function test_sans_application_planning_le_compte_est_cree_sans_role_et_sans_avertissement(): void
    {
        $this->assertSame(0, $this->lancer(['--email' => 'nouveau@example.test', '--password' => 'NouveauMdp-2026']));

        $cree = Personne::where('email', 'nouveau@example.test')->firstOrFail();
        $this->assertNull($this->role($cree));
        $this->assertFalse($cree->isAdmin());
        $sortie = Artisan::output(); // lu une seule fois : la lecture vide le tampon
        $this->assertStringNotContainsString('Rôle admin', $sortie);
        $this->assertStringContainsString('Mot de passe mis à jour avec succès.', $sortie);
    }
}
