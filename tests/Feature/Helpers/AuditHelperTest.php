<?php
// tests/Feature/Helpers/AuditHelperTest.php
//
// AuditHelper::applicationId() mémorise son résultat dans une propriété statique
// pour toute la durée du processus (y compris un ÉCHEC). Tests\TestCase la remet à
// zéro avant chaque test ; ces tests vérifient le comportement du cache lui-même.

declare(strict_types=1);

namespace Tests\Feature\Helpers;

use Amana\Shared\Models\Application;
use Amana\Shared\Models\AuditLog;
use App\Helpers\AuditHelper;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class AuditHelperTest extends TestCase
{
    use RefreshesBothDatabases;

    private function enregistrerApplication(string $code = 'planning'): Application
    {
        return Application::create(['code' => $code, 'libelle' => "App {$code}", 'actif' => true]);
    }

    public function test_application_id_renvoie_l_id_de_l_application_planning(): void
    {
        $app = $this->enregistrerApplication();

        $this->assertSame($app->id, AuditHelper::applicationId());
    }

    public function test_application_id_est_null_quand_l_application_n_est_pas_enregistree(): void
    {
        $this->assertNull(AuditHelper::applicationId());
    }

    public function test_application_id_utilise_le_code_de_application_configure(): void
    {
        $this->enregistrerApplication('planning');
        $autre = $this->enregistrerApplication('autre');
        config(['amana-shared.app_code' => 'autre']);

        $this->assertSame($autre->id, AuditHelper::applicationId());
    }

    public function test_un_succes_est_memorise_pas_de_nouvelle_requete(): void
    {
        $app = $this->enregistrerApplication();
        $this->assertSame($app->id, AuditHelper::applicationId());

        Application::where('id', $app->id)->update(['code' => 'renommee']);

        $this->assertSame($app->id, AuditHelper::applicationId(), 'la valeur en cache est réutilisée');
    }

    /**
     * CARACTÉRISATION : un ÉCHEC est mémorisé lui aussi. Si le premier appel du processus a lieu
     * avant l'enregistrement de l'application dans ref_applications, toutes les entrées d'audit
     * suivantes restent sans id_application jusqu'au redémarrage du processus (worker de queue
     * compris), même après avoir passé PlanningApplicationSeeder.
     */
    public function test_un_echec_est_memorise_lui_aussi(): void
    {
        $this->assertNull(AuditHelper::applicationId());

        $this->enregistrerApplication();

        $this->assertNull(AuditHelper::applicationId(), 'reste null : « application introuvable » est en cache');
    }

    public function test_le_cache_est_remis_a_zero_entre_deux_tests_grace_a_la_base_de_test(): void
    {
        // Filet de sécurité : sans Tests\TestCase::reinitialiserCachesStatiques(), le test
        // précédent (échec mémorisé, ou id d'une application annulée par sa transaction)
        // fausserait celui-ci.
        $app = $this->enregistrerApplication();

        $this->assertSame($app->id, AuditHelper::applicationId());
    }

    public function test_log_ecrit_une_entree_complete_dans_la_base_partagee(): void
    {
        $app = $this->enregistrerApplication();

        AuditHelper::log('update', 'personnes', 7, ['statut' => 'En attente'], ['statut' => 'Validé']);

        $entree = AuditLog::firstOrFail();
        $this->assertSame('commun', $entree->getConnectionName());
        $this->assertSame($app->id, $entree->id_application);
        $this->assertSame('update', $entree->action);
        $this->assertSame('personnes', $entree->module);
        $this->assertSame(7, $entree->entity_id);
        $this->assertSame(['statut' => 'En attente'], $entree->before);
        $this->assertSame(['statut' => 'Validé'], $entree->after);
        $this->assertNull($entree->user_id, 'personne d\'authentifié : action système');
    }

    public function test_log_sans_entite_ni_etats_laisse_les_colonnes_a_null(): void
    {
        AuditHelper::log('generate', 'planning');

        $entree = AuditLog::firstOrFail();
        $this->assertNull($entree->entity_id);
        $this->assertNull($entree->before);
        $this->assertNull($entree->after);
        $this->assertNull($entree->id_application, 'application non enregistrée : entrée écrite quand même');
    }

    public function test_log_enregistre_l_utilisateur_authentifie(): void
    {
        Auth::swap(new class
        {
            public function id(): int
            {
                return 42;
            }
        });

        AuditHelper::log('login', 'auth');

        $this->assertSame(42, (int) AuditLog::firstOrFail()->user_id);
    }

    public function test_la_fonction_globale_audit_delegue_a_l_helper(): void
    {
        audit('create', 'absences', 3, null, ['id' => 3]);

        $entree = AuditLog::firstOrFail();
        $this->assertSame(['create', 'absences', 3], [$entree->action, $entree->module, $entree->entity_id]);
        $this->assertSame(['id' => 3], $entree->after);
    }
}
