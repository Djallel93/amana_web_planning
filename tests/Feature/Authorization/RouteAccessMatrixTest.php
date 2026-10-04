<?php
// tests/Feature/Authorization/RouteAccessMatrixTest.php
//
// Il n'existe AUCUNE classe Policy dans cette application : l'autorisation est faite par
// les middlewares de route (`auth` = EnsureAuthenticated, `role:xxx` = EnsureRole, définis
// dans amana_shared) et par des contrôles manuels dans certains contrôleurs (voir
// AbsenceOwnershipTest, EchangeAuthorizationTest, RestrictionsAuthorizationTest). Ce test est
// donc une MATRICE route × persona, pas un test de Policy.
//
// La matrice ci-dessous est la SPÉCIFICATION écrite du niveau d'accès de chaque route
// (relue à la main contre la politique voulue, pas seulement recopiée du code). Un test de
// complétude échoue si une route est ajoutée (ou retirée) sans mise à jour de la matrice.
//
// Ce qu'on vérifie pour chaque (route, persona) : le contrôle d'accès REFUSE ou LAISSE PASSER.
//   - refus d'un invité      : redirection vers /login + message « Vous devez être connecté… »
//   - refus d'un connecté    : redirection vers l'accueil + message « …permissions nécessaires… »
//   - laisse passer          : la réponse n'est aucun de ces deux refus (le contrôleur a pu répondre
//                              n'importe quoi — 404, validation 302/422… : ce n'est pas l'objet ici)
//
// Niveaux : public (aucune connexion), auth (tout compte connecté, même sans rôle),
// membre / gestionnaire / admin (hiérarchie admin ⊃ gestionnaire ⊃ membre ; « benevole » est un
// rôle à part, SOUS membre : il n'accède à aucune route `role:membre`).

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\CalendrierGoogle;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ConnecteParRole;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class RouteAccessMatrixTest extends TestCase
{
    use ConnecteParRole;
    use RefreshesBothDatabases;

    private const MESSAGE_CONNEXION = 'Vous devez être connecté pour accéder à cette page.';

    private const MESSAGE_PERMISSION = 'Vous n\'avez pas les permissions nécessaires pour accéder à cette page.';

    /**
     * [nom de route, méthode, niveau requis, paramètres d'URL].
     * Les identifiants (999999) n'existent pas : un persona autorisé obtiendra un 404 ou une
     * validation, jamais un effet de bord. « @calendrier » est remplacé par un CalendrierGoogle
     * RÉEL : ces routes utilisent le route-model-binding, résolu AVANT les middlewares d'accès —
     * avec un id inexistant on obtiendrait 404 pour tout le monde et le contrôle d'accès ne serait
     * pas exercé (voir test_le_binding_est_resolu_avant_le_controle_d_acces).
     *
     * @return list<array{string, string, string, array<string, mixed>}>
     */
    public static function matrice(): array
    {
        return [
            ['admin.activite.data', 'GET', 'admin', []],
            ['admin.activite.index', 'GET', 'admin', []],
            ['admin.candidatures.index', 'GET', 'admin', []],
            ['admin.candidatures.refuser', 'POST', 'admin', ['id' => 999999]],
            ['admin.candidatures.renvoyer-invitation', 'POST', 'admin', ['id' => 999999]],
            ['admin.candidatures.valider', 'POST', 'admin', ['id' => 999999]],
            ['admin.journal.data', 'GET', 'admin', []],
            ['admin.journal.index', 'GET', 'admin', []],
            ['diagnostic.mail.index', 'GET', 'admin', []],
            ['diagnostic.mail.tester', 'POST', 'admin', []],
            ['personnes.create', 'GET', 'admin', []],
            ['personnes.desactiver', 'POST', 'admin', ['id' => 999999]],
            ['personnes.destroy', 'DELETE', 'admin', ['id' => 999999]],
            ['personnes.edit', 'GET', 'admin', ['id' => 999999]],
            ['personnes.index', 'GET', 'admin', []],
            ['personnes.reactiver', 'POST', 'admin', ['id' => 999999]],
            ['personnes.reset-link', 'POST', 'admin', ['id' => 999999]],
            ['personnes.store', 'POST', 'admin', []],
            ['personnes.update', 'PUT', 'admin', ['id' => 999999]],
            ['planning.edit.creneau-passe.contexte', 'GET', 'admin', []],
            ['absences.destroy', 'DELETE', 'auth', ['id' => 999999]],
            ['absences.index', 'GET', 'auth', []],
            ['absences.store', 'POST', 'auth', []],
            ['absences.update', 'PUT', 'auth', ['id' => 999999]],
            ['calendriers.index', 'GET', 'auth', []],
            ['echanges.destroy', 'DELETE', 'auth', ['id' => 999999]],
            ['echanges.index', 'GET', 'auth', []],
            ['echanges.slots', 'GET', 'auth', []],
            ['echanges.store', 'POST', 'auth', []],
            ['evenements.index', 'GET', 'auth', []],
            ['guide.index', 'GET', 'auth', []],
            ['mon-planning', 'GET', 'auth', []],
            ['nav-badges.index', 'GET', 'auth', []],
            ['planning.data', 'GET', 'auth', []],
            ['planning.export.form', 'GET', 'auth', []],
            ['planning.export.pdf', 'POST', 'auth', []],
            ['planning.index', 'GET', 'auth', []],
            ['planning.statistics', 'GET', 'auth', []],
            ['profile.edit', 'GET', 'auth', []],
            ['profile.email.confirm', 'GET', 'auth', []],
            ['profile.email.request', 'POST', 'auth', []],
            ['profile.password.update', 'PUT', 'auth', []],
            ['profile.update', 'PUT', 'auth', []],
            ['restrictions.index', 'GET', 'auth', []],
            ['restrictions.update', 'POST', 'auth', []],
            ['admin.echanges.approuver', 'POST', 'gestionnaire', ['id' => 999999]],
            ['admin.echanges.index', 'GET', 'gestionnaire', []],
            ['admin.echanges.refuser', 'POST', 'gestionnaire', ['id' => 999999]],
            ['bilan.data.reset.amana-food', 'POST', 'gestionnaire', []],
            ['bilan.data.reset.presence', 'POST', 'gestionnaire', []],
            ['calendriers-google.destroy', 'DELETE', 'gestionnaire', ['calendrierGoogle' => '@calendrier']],
            ['calendriers-google.store', 'POST', 'gestionnaire', []],
            ['calendriers-google.update', 'PATCH', 'gestionnaire', ['calendrierGoogle' => '@calendrier']],
            ['calendriers-google.verifier', 'POST', 'gestionnaire', ['calendrierGoogle' => '@calendrier']],
            ['evenements.create', 'GET', 'gestionnaire', []],
            ['evenements.destroy', 'DELETE', 'gestionnaire', ['id' => 999999]],
            ['evenements.edit', 'GET', 'gestionnaire', ['id' => 999999]],
            ['evenements.import', 'GET', 'gestionnaire', []],
            ['evenements.import.manuel', 'POST', 'gestionnaire', []],
            ['evenements.import.store', 'POST', 'gestionnaire', []],
            ['evenements.import.template', 'GET', 'gestionnaire', []],
            ['evenements.store', 'POST', 'gestionnaire', []],
            ['evenements.update', 'PUT', 'gestionnaire', ['id' => 999999]],
            ['planning.annulation-cours', 'POST', 'gestionnaire', []],
            ['planning.edit.assignation', 'PATCH', 'gestionnaire', ['creneauId' => 999999, 'tacheId' => 999999]],
            ['planning.edit.create-creneau', 'POST', 'gestionnaire', []],
            ['planning.edit.delete-creneau', 'DELETE', 'gestionnaire', ['id' => 999999]],
            ['planning.edit.personnes', 'GET', 'gestionnaire', []],
            ['planning.edit.unassign', 'DELETE', 'gestionnaire', ['creneauId' => 999999, 'tacheId' => 999999]],
            ['planning.generate', 'POST', 'gestionnaire', []],
            ['planning.generate.form', 'GET', 'gestionnaire', []],
            ['planning.overlap.cancel', 'POST', 'gestionnaire', []],
            ['planning.preview', 'POST', 'gestionnaire', []],
            ['planning.rollback', 'POST', 'gestionnaire', []],
            ['planning.rollback.dismiss', 'POST', 'gestionnaire', []],
            ['settings.index', 'GET', 'gestionnaire', []],
            ['settings.update', 'POST', 'gestionnaire', []],
            ['bilan.data.show', 'GET', 'membre', []],
            ['bilan.data.store.amana-food', 'POST', 'membre', []],
            ['bilan.data.store.presence', 'POST', 'membre', []],
            ['bilan.index', 'GET', 'membre', []],
            ['bilan.statistiques', 'GET', 'membre', []],
            ['bilan.statistiques.data', 'GET', 'membre', []],
            ['echanges.accepter', 'GET', 'public', ['token' => str_repeat('a', 64)]],
            ['echanges.refuser', 'GET', 'public', ['token' => str_repeat('a', 64)]],
            ['inscription', 'GET', 'public', []],
            ['inscription.submit', 'POST', 'public', []],
            ['login', 'GET', 'public', []],
            ['login.submit', 'POST', 'public', []],
            ['logout', 'POST', 'public', []],
            ['password.email', 'POST', 'public', []],
            ['password.request', 'GET', 'public', []],
            ['password.reset', 'GET', 'public', ['token' => str_repeat('a', 64)]],
            ['password.update', 'POST', 'public', []],
        ];
    }

    /** @return array<string, array{string, string, string, array, string}> */
    public static function casRouteEtPersona(): array
    {
        $cas = [];
        foreach (self::matrice() as [$nom, $methode, $niveau, $parametres]) {
            foreach (array_keys(self::PERSONAS) as $persona) {
                $cas["{$methode} {$nom} — {$persona}"] = [$nom, $methode, $niveau, $parametres, $persona];
            }
        }

        return $cas;
    }

    /** « connexion » | « permission » | null (accès laissé passer). */
    private function refus(TestResponse $reponse): ?string
    {
        if (!$reponse->isRedirection()) {
            return null;
        }
        $erreur = session('error');
        if ($erreur === self::MESSAGE_CONNEXION && $reponse->headers->get('Location') === route('login')) {
            return 'connexion';
        }
        if ($erreur === self::MESSAGE_PERMISSION && $reponse->headers->get('Location') === route(config('amana-shared.home_route'))) {
            return 'permission';
        }

        return null;
    }

    #[DataProvider('casRouteEtPersona')]
    public function test_le_controle_d_acces_refuse_ou_laisse_passer_selon_le_persona(string $nom, string $methode, string $niveau, array $parametres, string $persona): void
    {
        $this->connecterEn($persona);
        if (in_array('@calendrier', $parametres, true)) {
            $parametres = array_map(fn($v) => $v === '@calendrier' ? CalendrierGoogle::factory()->create()->id : $v, $parametres);
        }

        $reponse = $this->call($methode, route($nom, $parametres));

        $attendu = $this->personaAutorise($persona, $niveau)
            ? null
            : ($persona === 'guest' ? 'connexion' : 'permission');

        $this->assertSame(
            $attendu,
            $this->refus($reponse),
            "{$methode} {$nom} (niveau « {$niveau} ») pour « {$persona} » : statut {$reponse->getStatusCode()}",
        );
    }

    /**
     * CARACTÉRISATION : le route-model-binding ({calendrierGoogle}) est résolu par le middleware
     * SubstituteBindings, AVANT `auth` / `role`. Avec un id inexistant, n'importe qui — invité
     * compris — reçoit un 404 ; avec un id existant, il reçoit la redirection de connexion. Un
     * visiteur non connecté peut donc distinguer les ids de calendriers qui existent. Faible
     * gravité (ids séquentiels, aucune donnée exposée) mais l'ordre est probablement involontaire.
     */
    public function test_le_binding_est_resolu_avant_le_controle_d_acces(): void
    {
        $existant = CalendrierGoogle::factory()->create();

        $this->delete(route('calendriers-google.destroy', 999999))->assertNotFound();
        $this->delete(route('calendriers-google.destroy', $existant->id))->assertRedirect(route('login'));
    }

    // ── Complétude ────────────────────────────────────────────────────────

    public function test_toutes_les_routes_nommees_de_l_application_figurent_dans_la_matrice(): void
    {
        $reelles = collect(Route::getRoutes()->getRoutes())
            ->map(fn($route) => $route->getName())
            ->filter()
            ->reject(fn(string $nom) => str_starts_with($nom, 'storage.')) // routes du framework (disque local)
            ->unique()->sort()->values()->all();
        $declarees = collect(self::matrice())->pluck(0)->unique()->sort()->values()->all();

        $this->assertSame([], array_values(array_diff($reelles, $declarees)), 'routes SANS niveau d\'accès déclaré dans la matrice');
        $this->assertSame([], array_values(array_diff($declarees, $reelles)), 'entrées de la matrice qui ne correspondent à aucune route');
    }

    public function test_les_seules_routes_sans_nom_sont_l_accueil_et_le_health_check(): void
    {
        $sansNom = collect(Route::getRoutes()->getRoutes())
            ->filter(fn($route) => $route->getName() === null)
            ->map(fn($route) => $route->uri())->sort()->values()->all();

        $this->assertSame(['/', 'up'], $sansNom, 'une nouvelle route sans nom échappe à la matrice : la nommer');
    }

    public function test_l_accueil_redirige_vers_le_planning_meme_pour_un_invite(): void
    {
        $this->get('/')->assertRedirect(route('planning.index'));
    }

    // ── Les deux refus, précisément ───────────────────────────────────────

    public function test_un_invite_est_renvoye_vers_la_connexion_avec_un_message(): void
    {
        $this->get(route('planning.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', self::MESSAGE_CONNEXION);
    }

    public function test_un_connecte_sans_le_role_est_renvoye_vers_l_accueil_avec_un_message(): void
    {
        $this->connecterEn('membre');

        $this->get(route('personnes.index'))
            ->assertRedirect(route('planning.index'))
            ->assertSessionHas('error', self::MESSAGE_PERMISSION);
    }

    public function test_le_refus_est_une_redirection_jamais_un_403(): void
    {
        // Choix de conception de EnsureRole : pas de 403. Un client JSON (fetch) reçoit donc un 302,
        // pas une erreur exploitable — voir la note dans test-suite-findings.md.
        $this->connecterEn('membre');

        $this->getJson(route('personnes.index'))->assertStatus(302);
    }

    public function test_un_benevole_n_a_acces_a_aucune_route_reservee_aux_membres(): void
    {
        $this->connecterEn('benevole');

        foreach (['bilan.index', 'bilan.data.show', 'bilan.statistiques', 'bilan.statistiques.data'] as $nom) {
            $this->get(route($nom))->assertRedirect(route('planning.index'));
        }
    }

    public function test_les_routes_de_remise_a_zero_du_bilan_exigent_gestionnaire_meme_pour_un_membre(): void
    {
        $this->connecterEn('membre');

        $this->post(route('bilan.data.reset.amana-food'))->assertRedirect(route('planning.index'));
        $this->post(route('bilan.data.reset.presence'))->assertRedirect(route('planning.index'));
    }
}
