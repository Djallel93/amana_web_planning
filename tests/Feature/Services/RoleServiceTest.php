<?php
// tests/Feature/Services/RoleServiceTest.php
//
// Nécessite MySQL/MariaDB : planningRoles() et currentRoleCode() trient avec
// orderByRaw("FIELD(...)"), absent de SQLite.

declare(strict_types=1);

namespace Tests\Feature\Services;

use Amana\Shared\Models\Application;
use Amana\Shared\Models\Role;
use App\Models\Personne;
use App\Services\RoleService;
use Carbon\Carbon;
use Database\Factories\PersonneFactory;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class RoleServiceTest extends TestCase
{
    use RefreshesBothDatabases;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): RoleService
    {
        return new RoleService();
    }

    /** Crée les rôles planning dans un ordre volontairement mélangé, pour que l'ordre de sortie ne vienne pas de l'ordre d'insertion. */
    private function creerRolesMelanges(): Application
    {
        $app = Application::create(['code' => 'planning', 'libelle' => 'AMANA Planning', 'actif' => true]);
        foreach (['benevole', 'admin', 'membre', 'gestionnaire', 'lecteur'] as $code) {
            Role::create(['code' => $code, 'libelle' => ucfirst($code), 'id_application' => $app->id]);
        }

        return $app;
    }

    private function codesRolesPlanning(Personne $personne): array
    {
        return DB::connection('commun')->table('ref_personnes_roles')
            ->join('ref_roles', 'ref_roles.id', '=', 'ref_personnes_roles.id_role')
            ->join('ref_applications', 'ref_applications.id', '=', 'ref_roles.id_application')
            ->where('ref_personnes_roles.id_personne', $personne->id)
            ->where('ref_applications.code', 'planning')
            ->orderBy('ref_roles.code')
            ->pluck('ref_roles.code')->all();
    }

    // ── planningRoles ─────────────────────────────────────────────────────

    public function test_planning_roles_est_trie_du_plus_eleve_au_plus_bas_quel_que_soit_l_ordre_d_insertion(): void
    {
        $this->creerRolesMelanges();

        $this->assertSame(
            ['admin', 'gestionnaire', 'membre', 'benevole'],
            $this->service()->planningRoles()->pluck('code')->all(),
        );
    }

    public function test_planning_roles_ecarte_les_roles_hors_hierarchie_et_ceux_des_autres_applications(): void
    {
        $this->creerRolesMelanges(); // 'lecteur' n'est pas dans la hiérarchie
        $autre = Application::create(['code' => 'autre', 'libelle' => 'Autre', 'actif' => true]);
        Role::create(['code' => 'admin', 'libelle' => 'Admin de l\'autre app', 'id_application' => $autre->id]);

        $roles = $this->service()->planningRoles();

        $this->assertCount(4, $roles);
        $this->assertNotContains('lecteur', $roles->pluck('code')->all());
        $this->assertSame([$this->service()->planningApp()->id], $roles->pluck('id_application')->unique()->values()->all());
    }

    public function test_planning_roles_est_vide_sans_application_planning(): void
    {
        $this->assertTrue($this->service()->planningRoles()->isEmpty());
        $this->assertNull($this->service()->planningApp());
    }

    // ── currentRoleCode ───────────────────────────────────────────────────

    public function test_current_role_code_renvoie_le_role_planning_de_la_personne(): void
    {
        $this->assertSame('gestionnaire', $this->service()->currentRoleCode(Personne::factory()->gestionnaire()->create()));
    }

    public function test_current_role_code_renvoie_le_plus_eleve_quand_il_y_en_a_plusieurs(): void
    {
        $this->creerRolesMelanges();
        $personne = Personne::factory()->create();
        foreach (['benevole', 'admin', 'membre'] as $code) {
            $personne->roles()->attach(Role::where('code', $code)->first()->id);
        }

        $this->assertSame('admin', $this->service()->currentRoleCode($personne));
    }

    public function test_current_role_code_est_null_sans_role_planning(): void
    {
        $this->creerRolesMelanges();

        $this->assertNull($this->service()->currentRoleCode(Personne::factory()->create()));
    }

    public function test_current_role_code_ignore_les_roles_des_autres_applications(): void
    {
        $this->creerRolesMelanges();
        $autre = Application::create(['code' => 'autre', 'libelle' => 'Autre', 'actif' => true]);
        $roleAutre = Role::create(['code' => 'admin', 'libelle' => 'Admin autre app', 'id_application' => $autre->id]);
        $personne = Personne::factory()->create();
        $personne->roles()->attach($roleAutre->id);

        $this->assertNull($this->service()->currentRoleCode($personne));
    }

    // ── syncRolePlanning ──────────────────────────────────────────────────

    public function test_sync_remplace_le_role_planning_et_date_l_attribution(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 10:00:00'));
        $personne = Personne::factory()->membre()->create();
        PersonneFactory::role('admin');

        $this->service()->syncRolePlanning($personne, 'admin');

        $this->assertSame(['admin'], $this->codesRolesPlanning($personne), 'membre remplacé, pas cumulé');
        $this->assertSame('2026-09-14', DB::connection('commun')->table('ref_personnes_roles')
            ->where('id_personne', $personne->id)->value('date_attribution'));
    }

    public function test_sync_conserve_les_roles_des_autres_applications(): void
    {
        $personne = Personne::factory()->membre()->create();
        PersonneFactory::role('admin');
        $autre = Application::create(['code' => 'autre', 'libelle' => 'Autre', 'actif' => true]);
        $roleAutre = Role::create(['code' => 'lecteur', 'libelle' => 'Lecteur', 'id_application' => $autre->id]);
        $personne->roles()->attach($roleAutre->id);

        $this->service()->syncRolePlanning($personne, 'admin');

        $this->assertSame(
            ['admin', 'lecteur'],
            $personne->roles()->pluck('ref_roles.code')->sort()->values()->all(),
        );
    }

    public function test_sync_ne_touche_pas_aux_roles_des_autres_personnes(): void
    {
        [$a, $b] = [Personne::factory()->membre()->create(), Personne::factory()->membre()->create()];
        PersonneFactory::role('admin');

        $this->service()->syncRolePlanning($a, 'admin');

        $this->assertSame(['membre'], $this->codesRolesPlanning($b));
    }

    public function test_sync_est_idempotent(): void
    {
        $personne = Personne::factory()->membre()->create();
        PersonneFactory::role('admin');

        $this->service()->syncRolePlanning($personne, 'admin');
        $this->service()->syncRolePlanning($personne, 'admin');

        $this->assertSame(['admin'], $this->codesRolesPlanning($personne));
    }

    public function test_sync_sans_application_planning_ne_fait_rien(): void
    {
        $personne = Personne::factory()->create();

        $this->service()->syncRolePlanning($personne, 'admin');

        $this->assertSame(0, DB::connection('commun')->table('ref_personnes_roles')->count());
    }

    /**
     * CARACTÉRISATION : un code de rôle inconnu supprime d'abord tous les rôles planning
     * de la personne, puis n'insère rien. Elle se retrouve sans aucun rôle planning
     * (donc sans accès). Si c'est indésirable, valider le code AVANT la suppression.
     */
    public function test_sync_avec_un_code_inconnu_retire_le_role_sans_en_donner_de_nouveau(): void
    {
        $personne = Personne::factory()->admin()->create();

        $this->service()->syncRolePlanning($personne, 'inexistant');

        $this->assertSame([], $this->codesRolesPlanning($personne));
    }
}
