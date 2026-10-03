<?php
// tests/Feature/Authorization/AbsenceOwnershipTest.php
//
// Contrôles MANUELS d'AbsencesController (il n'y a pas de Policy) : un compte non privilégié ne
// gère que ses propres absences ; admin et gestionnaire gèrent celles de tout le monde.
// Les routes elles-mêmes sont ouvertes à tout compte connecté (voir RouteAccessMatrixTest).
// Bus::fake() : store/update/destroy poussent une synchronisation Google Calendar.

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\Absence;
use App\Models\Personne;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ConnecteParRole;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class AbsenceOwnershipTest extends TestCase
{
    use RefreshesBothDatabases;
    use ConnecteParRole;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
    }

    private function donnees(Personne $personne, array $surcharge = []): array
    {
        return array_replace([
            'id_personne' => $personne->id,
            'date_debut' => '2031-03-03',
            'date_fin' => '2031-03-05',
            'raison' => 'Vacances',
        ], $surcharge);
    }

    /** @return array<string, array{string}> */
    public static function nonPrivilegies(): array
    {
        return ['sans rôle' => ['sans_role'], 'bénévole' => ['benevole'], 'membre' => ['membre']];
    }

    /** @return array<string, array{string}> */
    public static function privilegies(): array
    {
        return ['gestionnaire' => ['gestionnaire'], 'admin' => ['admin']];
    }

    // ── store ─────────────────────────────────────────────────────────────

    #[DataProvider('nonPrivilegies')]
    public function test_un_compte_non_privilegie_peut_declarer_sa_propre_absence(string $persona): void
    {
        $moi = $this->connecterEn($persona);

        $this->post(route('absences.store'), $this->donnees($moi))
            ->assertRedirect(route('absences.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, Absence::where('id_personne', $moi->id)->count());
    }

    #[DataProvider('nonPrivilegies')]
    public function test_un_compte_non_privilegie_ne_peut_pas_declarer_l_absence_de_quelqu_un_d_autre(string $persona): void
    {
        $this->connecterEn($persona);
        $autre = Personne::factory()->create();

        $this->post(route('absences.store'), $this->donnees($autre))
            ->assertRedirect(route('absences.index'))
            ->assertSessionHas('error', 'Vous ne pouvez enregistrer une absence que pour vous-même.');

        $this->assertSame(0, Absence::count());
        Bus::assertNothingDispatched();
    }

    #[DataProvider('privilegies')]
    public function test_gestionnaire_et_admin_peuvent_declarer_l_absence_de_n_importe_qui(string $persona): void
    {
        $this->connecterEn($persona);
        $autre = Personne::factory()->create();

        $this->post(route('absences.store'), $this->donnees($autre))->assertSessionHas('success');

        $this->assertSame(1, Absence::where('id_personne', $autre->id)->count());
    }

    public function test_store_valide_les_donnees(): void
    {
        $moi = $this->connecterEn('membre');

        $this->post(route('absences.store'), $this->donnees($moi, ['date_fin' => '2031-03-01']))->assertSessionHasErrors('date_fin');
        $this->post(route('absences.store'), ['date_debut' => '2031-03-03', 'date_fin' => '2031-03-05'])->assertSessionHasErrors('id_personne');
        $this->post(route('absences.store'), $this->donnees($moi, ['id_personne' => 999999]))->assertSessionHasErrors('id_personne');

        $this->assertSame(0, Absence::count());
    }

    // ── update ────────────────────────────────────────────────────────────

    #[DataProvider('nonPrivilegies')]
    public function test_on_peut_modifier_sa_propre_absence(string $persona): void
    {
        $moi = $this->connecterEn($persona);
        $absence = Absence::factory()->pour($moi)->create();

        $this->putJson(route('absences.update', $absence->id), $this->donnees($moi, ['raison' => 'Changée']))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('Changée', $absence->fresh()->raison);
    }

    #[DataProvider('nonPrivilegies')]
    public function test_on_ne_peut_pas_modifier_l_absence_de_quelqu_un_d_autre(string $persona): void
    {
        $this->connecterEn($persona);
        $autre = Personne::factory()->create();
        $absence = Absence::factory()->pour($autre)->create(['raison' => 'Originale']);

        $this->putJson(route('absences.update', $absence->id), $this->donnees($autre, ['raison' => 'Piratée']))
            ->assertForbidden()
            ->assertJson(['success' => false, 'message' => 'Vous ne pouvez modifier que vos propres absences.']);

        $this->assertSame('Originale', $absence->fresh()->raison);
    }

    public function test_on_ne_peut_pas_reattribuer_sa_propre_absence_a_quelqu_un_d_autre(): void
    {
        $moi = $this->connecterEn('membre');
        $autre = Personne::factory()->create();
        $absence = Absence::factory()->pour($moi)->create();

        $this->putJson(route('absences.update', $absence->id), $this->donnees($autre))
            ->assertForbidden()
            ->assertJson(['message' => 'Vous ne pouvez enregistrer une absence que pour vous-même.']);

        $this->assertSame($moi->id, $absence->fresh()->id_personne);
    }

    #[DataProvider('privilegies')]
    public function test_gestionnaire_et_admin_peuvent_modifier_et_reattribuer_n_importe_quelle_absence(string $persona): void
    {
        $this->connecterEn($persona);
        [$a, $b] = [Personne::factory()->create(), Personne::factory()->create()];
        $absence = Absence::factory()->pour($a)->create();

        $this->putJson(route('absences.update', $absence->id), $this->donnees($b))->assertOk();

        $this->assertSame($b->id, $absence->fresh()->id_personne);
    }

    public function test_modifier_une_absence_inconnue_donne_404(): void
    {
        $moi = $this->connecterEn('admin');

        $this->putJson(route('absences.update', 999999), $this->donnees($moi))->assertNotFound();
    }

    // ── destroy ───────────────────────────────────────────────────────────

    #[DataProvider('nonPrivilegies')]
    public function test_on_peut_supprimer_sa_propre_absence(string $persona): void
    {
        $moi = $this->connecterEn($persona);
        $absence = Absence::factory()->pour($moi)->create();

        $this->delete(route('absences.destroy', $absence->id))
            ->assertRedirect(route('absences.index'))
            ->assertSessionHas('success');

        $this->assertNull(Absence::find($absence->id));
    }

    #[DataProvider('nonPrivilegies')]
    public function test_on_ne_peut_pas_supprimer_l_absence_de_quelqu_un_d_autre(string $persona): void
    {
        $this->connecterEn($persona);
        $absence = Absence::factory()->pour(Personne::factory()->create())->create();

        $this->delete(route('absences.destroy', $absence->id))
            ->assertRedirect(route('absences.index'))
            ->assertSessionHas('error', 'Vous ne pouvez supprimer que vos propres absences.');

        $this->assertNotNull(Absence::find($absence->id));
    }

    #[DataProvider('privilegies')]
    public function test_gestionnaire_et_admin_peuvent_supprimer_n_importe_quelle_absence(string $persona): void
    {
        $this->connecterEn($persona);
        $absence = Absence::factory()->pour(Personne::factory()->create())->create();

        $this->delete(route('absences.destroy', $absence->id))->assertSessionHas('success');

        $this->assertNull(Absence::find($absence->id));
    }

    public function test_supprimer_une_absence_inconnue_donne_404(): void
    {
        $this->connecterEn('admin');

        $this->delete(route('absences.destroy', 999999))->assertNotFound();
    }

    // ── index ─────────────────────────────────────────────────────────────

    public function test_un_compte_non_privilegie_ne_peut_choisir_que_lui_meme_dans_le_formulaire(): void
    {
        $moi = $this->connecterEn('membre');
        Personne::factory()->count(2)->create();

        $reponse = $this->get(route('absences.index'))->assertOk();

        $this->assertSame([$moi->id], $reponse->viewData('personnes')->pluck('id')->all());
    }

    #[DataProvider('privilegies')]
    public function test_gestionnaire_et_admin_choisissent_parmi_toutes_les_personnes_actives(string $persona): void
    {
        $moi = $this->connecterEn($persona);
        $active = Personne::factory()->create();
        Personne::factory()->enAttente()->create();
        Personne::factory()->suspendu()->create();

        $ids = $this->get(route('absences.index'))->assertOk()->viewData('personnes')->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$moi->id, $active->id], $ids);
    }

    /**
     * CARACTÉRISATION : la liste des absences n'est PAS filtrée par propriétaire — tout compte
     * connecté (même sans rôle) voit les absences de tous, motif (`raison`) compris. Si le motif
     * peut être sensible (santé, famille), à restreindre.
     */
    public function test_tout_compte_connecte_voit_les_absences_et_les_motifs_des_autres(): void
    {
        $this->connecterEn('sans_role');
        $autre = Personne::factory()->create();
        $absence = Absence::factory()->pour($autre)->create(['raison' => 'Rendez-vous médical']);

        $reponse = $this->get(route('absences.index'))->assertOk();

        $vue = $reponse->viewData('absences')->firstWhere('id', $absence->id);
        $this->assertNotNull($vue);
        $this->assertSame('Rendez-vous médical', $vue->raison);
    }
}
