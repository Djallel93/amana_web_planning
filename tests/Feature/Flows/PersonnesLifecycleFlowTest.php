<?php
// tests/Feature/Flows/PersonnesLifecycleFlowTest.php
//
// Cycle de vie d'un compte par un administrateur : lister, désactiver, réactiver, supprimer,
// envoyer un lien de réinitialisation. Les droits (admin uniquement) sont dans
// RouteAccessMatrixTest ; ici, ce que chaque action fait.
//
// Création et modification (store / update) ne sont PAS couvertes : leur règle d'e-mail
// `email:rfc,dns` interroge le DNS en direct, ce qui rendrait la suite dépendante du réseau.
// Voir test-suite-findings.md.
//
// Repères : « aujourd'hui » = mercredi 2026-09-30 ; 2026-10-02 est un vendredi.

declare(strict_types=1);

namespace Tests\Feature\Flows;

use Amana\Shared\Models\Application;
use Amana\Shared\Models\AuditLog;
use Amana\Shared\Models\Role;
use Amana\Shared\Notifications\ResetPasswordNotification;
use App\Models\Absence;
use App\Models\CreneauTache;
use App\Models\Personne;
use App\Services\SchedulerMain;
use Database\Factories\PersonneFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ConnecteParRole;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class PersonnesLifecycleFlowTest extends TestCase
{
    use ConnecteParRole;
    use CreeDonneesPlanning;
    use RefreshesBothDatabases;

    private Personne $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-30 09:00:00');
        $this->creerRolesPlanning();
        $this->admin = $this->connecterEn('admin');
    }

    // ── Liste ─────────────────────────────────────────────────────────────

    public function test_la_liste_est_triee_par_nom_puis_prenom(): void
    {
        // Le nom de l'administrateur (aléatoire via Faker) pourrait se classer avant les autres : on le fixe en dernier.
        $this->admin->update(['nom' => 'Zzz', 'prenom' => 'Admin']);
        $b = Personne::factory()->create(['nom' => 'Martin', 'prenom' => 'Zoé']);
        $a = Personne::factory()->create(['nom' => 'Martin', 'prenom' => 'Alice']);
        $c = Personne::factory()->create(['nom' => 'Abadie', 'prenom' => 'Yann']);

        $noms = $this->get(route('personnes.index'))->assertOk()->viewData('personnes')->map(fn($p) => $p->nom . ' ' . $p->prenom)->all();

        $this->assertSame([$c->nom . ' ' . $c->prenom, $a->nom . ' ' . $a->prenom, $b->nom . ' ' . $b->prenom], array_slice($noms, 0, 3));
    }

    /** Nécessite MySQL : `ORDER BY FIELD(ref_roles.code, 'admin', 'gestionnaire', 'membre', 'benevole')`. */
    public function test_les_roles_planning_d_une_personne_sont_ordonnes_du_plus_eleve_au_plus_bas(): void
    {
        $p = Personne::factory()->create();
        foreach (['benevole', 'membre', 'admin', 'gestionnaire'] as $code) { // ordre d'attribution volontairement mélangé
            $p->roles()->attach(PersonneFactory::role($code)->id);
        }

        $vue = $this->get(route('personnes.index'))->viewData('personnes')->firstWhere('id', $p->id);

        $this->assertSame(['admin', 'gestionnaire', 'membre', 'benevole'], $vue->roles->pluck('code')->all());
    }

    public function test_la_liste_n_affiche_que_les_roles_de_l_application_planning(): void
    {
        $p = Personne::factory()->membre()->create();
        $autre = Application::create(['code' => 'autre', 'libelle' => 'Autre', 'actif' => true]);
        $p->roles()->attach(Role::create(['code' => 'admin', 'libelle' => 'Admin autre app', 'id_application' => $autre->id])->id);

        $vue = $this->get(route('personnes.index'))->viewData('personnes')->firstWhere('id', $p->id);

        $this->assertSame(['membre'], $vue->roles->pluck('code')->all());
    }

    public function test_la_liste_montre_tous_les_statuts(): void
    {
        foreach (['enAttente', 'suspendu', 'archive'] as $etat) {
            Personne::factory()->{$etat}()->create();
        }

        $statuts = $this->get(route('personnes.index'))->viewData('personnes')->pluck('statut')->unique()->sort()->values()->all();

        $this->assertSame(['Archivé', 'En attente', 'Suspendu', 'Validé'], $statuts);
    }

    // ── Désactiver ────────────────────────────────────────────────────────

    public function test_desactiver_suspend_un_compte_valide_et_le_journalise(): void
    {
        $p = Personne::factory()->create(['prenom' => 'Nadia', 'nom' => 'Nasri']);

        $this->post(route('personnes.desactiver', $p->id))
            ->assertRedirect(route('personnes.index'))
            ->assertSessionHas('success', "Personne « Nadia {$p->nom} » désactivée. Elle ne peut plus se connecter tant qu'elle n'est pas réactivée.");

        $this->assertSame('Suspendu', $p->fresh()->statut);
        $entree = AuditLog::where('module', 'personnes')->where('action', 'update')->firstOrFail();
        $this->assertSame('Validé', $entree->before['statut']);
        $this->assertSame('Suspendu', $entree->after['statut']);
        $this->assertArrayNotHasKey('password', $entree->before, 'le mot de passe (haché) n\'est jamais journalisé');
        $this->assertArrayNotHasKey('password', $entree->after);
    }

    public function test_un_compte_desactive_ne_peut_plus_se_connecter(): void
    {
        $p = Personne::factory()->create();
        $this->post(route('personnes.desactiver', $p->id));
        $this->post(route('logout'));

        $this->post(route('login.submit'), ['email' => $p->email, 'password' => PersonneFactory::MOT_DE_PASSE])
            ->assertSessionHasErrors(['email' => 'Votre compte a été suspendu. Contactez un administrateur.']);

        $this->assertGuest();
    }

    /** @return array<string, array{string}> */
    public static function statutsNonValides(): array
    {
        return ['en attente' => ['enAttente'], 'suspendu' => ['suspendu'], 'archivé' => ['archive']];
    }

    #[DataProvider('statutsNonValides')]
    public function test_seul_un_compte_valide_peut_etre_desactive(string $etat): void
    {
        $p = Personne::factory()->{$etat}()->create();
        $statut = $p->statut;

        $this->post(route('personnes.desactiver', $p->id))
            ->assertRedirect(route('personnes.index'))
            ->assertSessionHas('error', 'Seule une personne au statut « Validé » peut être désactivée.');

        $this->assertSame($statut, $p->fresh()->statut);
        $this->assertSame(0, AuditLog::where('module', 'personnes')->count());
    }

    public function test_desactiver_une_personne_inconnue_donne_404(): void
    {
        $this->post(route('personnes.desactiver', 999999))->assertNotFound();
        $this->post(route('personnes.reactiver', 999999))->assertNotFound();
        $this->delete(route('personnes.destroy', 999999))->assertNotFound();
        $this->post(route('personnes.reset-link', 999999))->assertNotFound();
    }

    /**
     * CARACTÉRISATION (risque de blocage) : aucune protection — un administrateur peut se
     * désactiver LUI-MÊME, y compris s'il est le dernier administrateur. Il ne pourra plus se
     * reconnecter, et il n'existera plus personne pour le réactiver (hors accès direct à la base
     * ou commande ResetAdminPassword). Sa session en cours, elle, reste valide (voir
     * AccountStateAccessTest).
     */
    public function test_un_administrateur_peut_se_desactiver_lui_meme_meme_s_il_est_le_dernier(): void
    {
        $this->assertSame(1, Personne::adminsDe()->count(), 'un seul administrateur');

        $this->post(route('personnes.desactiver', $this->admin->id))->assertSessionHas('success');

        $this->assertSame('Suspendu', $this->admin->fresh()->statut);
        $this->post(route('logout'));
        $this->post(route('login.submit'), ['email' => $this->admin->email, 'password' => PersonneFactory::MOT_DE_PASSE])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * CARACTÉRISATION : désactiver ne touche pas au planning existant. La personne garde ses
     * créneaux futurs (et ses échanges en attente) ; elle n'est simplement plus retenue lors de la
     * PROCHAINE génération. Pour la sortir du planning déjà généré, il faut le régénérer.
     */
    public function test_desactiver_ne_retire_pas_la_personne_du_planning_deja_genere(): void
    {
        $this->tachesDeRotation();
        $this->personnesValidees(6);
        $this->app->make(SchedulerMain::class)->generateSchedule('2026-10-02', 2);
        $titulaire = Personne::find($this->idPersonneDuCreneau('2026-10-02', 'entree'));
        $avant = CreneauTache::where('id_personne', $titulaire->id)->count();
        $this->assertGreaterThan(0, $avant);

        $this->post(route('personnes.desactiver', $titulaire->id));

        $this->assertSame($avant, CreneauTache::where('id_personne', $titulaire->id)->count(), 'inchangé');

        $this->app->make(SchedulerMain::class)->generateSchedule('2026-10-02', 2);
        $this->assertSame(0, CreneauTache::where('id_personne', $titulaire->id)->count(), 'plus retenue à la génération suivante');
    }

    // ── Réactiver ─────────────────────────────────────────────────────────

    public function test_reactiver_remet_un_compte_suspendu_en_valide_et_il_peut_se_reconnecter(): void
    {
        $p = Personne::factory()->suspendu()->dansLaRotationDepuis('2026-01-05')->create(['prenom' => 'Nadia', 'nom' => 'Nasri']);

        $this->post(route('personnes.reactiver', $p->id))
            ->assertRedirect(route('personnes.index'))
            ->assertSessionHas('success', "Personne « Nadia {$p->nom} » réactivée.");

        $p->refresh();
        $this->assertSame('Validé', $p->statut);
        $this->assertSame('2026-01-05', $p->date_debut_planning->toDateString(), 'la date d\'entrée en rotation est conservée');
        $this->post(route('logout'));
        $this->post(route('login.submit'), ['email' => $p->email, 'password' => PersonneFactory::MOT_DE_PASSE])->assertRedirect(route('planning.index'));
        $this->assertAuthenticatedAs($p);
    }

    #[DataProvider('statutsReactivation')]
    public function test_seul_un_compte_suspendu_peut_etre_reactive(string $etat): void
    {
        $p = $etat === 'valide' ? Personne::factory()->create() : Personne::factory()->{$etat}()->create();
        $statut = $p->statut;

        $this->post(route('personnes.reactiver', $p->id))
            ->assertRedirect(route('personnes.index'))
            ->assertSessionHas('error', 'Seule une personne suspendue peut être réactivée.');

        $this->assertSame($statut, $p->fresh()->statut);
    }

    /** @return array<string, array{string}> */
    public static function statutsReactivation(): array
    {
        return ['validé' => ['valide'], 'en attente' => ['enAttente'], 'archivé' => ['archive']];
    }

    public function test_une_personne_reactivee_redevient_eligible_a_la_generation(): void
    {
        $this->tachesDeRotation();
        $this->personnesValidees(5);
        $retour = Personne::factory()->suspendu()->create(['nom' => 'Aaa']);

        $this->app->make(SchedulerMain::class)->generateSchedule('2026-10-02', 2);
        $this->assertSame(0, CreneauTache::where('id_personne', $retour->id)->count(), 'suspendue : exclue');

        $this->post(route('personnes.reactiver', $retour->id));
        $this->app->make(SchedulerMain::class)->generateSchedule('2026-10-02', 2);

        $this->assertGreaterThan(0, CreneauTache::where('id_personne', $retour->id)->count(), 'réactivée : de nouveau dans la rotation');
    }

    public function test_desactiver_puis_reactiver_laisse_deux_entrees_d_audit(): void
    {
        $p = Personne::factory()->create();

        $this->post(route('personnes.desactiver', $p->id));
        $this->post(route('personnes.reactiver', $p->id));

        $this->assertSame(2, AuditLog::where('module', 'personnes')->where('entity_id', $p->id)->count());
    }

    // ── Supprimer ─────────────────────────────────────────────────────────

    #[DataProvider('statutsSupprimables')]
    public function test_une_candidature_ou_un_compte_archive_peut_etre_supprime(string $etat): void
    {
        $p = Personne::factory()->{$etat}()->create(['prenom' => 'Sana', 'nom' => 'Sadi']);

        $this->delete(route('personnes.destroy', $p->id))
            ->assertRedirect(route('personnes.index'))
            ->assertSessionHas('success', "Personne « Sana {$p->nom} » supprimée.");

        $this->assertNull(Personne::find($p->id));
        $entree = AuditLog::where('module', 'personnes')->where('action', 'delete')->firstOrFail();
        $this->assertSame($p->email, $entree->before['email']);
        $this->assertNull($entree->after);
    }

    /** @return array<string, array{string}> */
    public static function statutsSupprimables(): array
    {
        return ['en attente' => ['enAttente'], 'archivé' => ['archive']];
    }

    /** @return array<string, array{string, string}> */
    public static function statutsInsupprimables(): array
    {
        return ['validé' => ['', 'Validé'], 'suspendu' => ['suspendu', 'Suspendu']];
    }

    #[DataProvider('statutsInsupprimables')]
    public function test_un_compte_valide_ou_suspendu_ne_peut_pas_etre_supprime(string $etat, string $statut): void
    {
        $p = $etat === '' ? Personne::factory()->create() : Personne::factory()->{$etat}()->create();

        $this->delete(route('personnes.destroy', $p->id))
            ->assertRedirect(route('personnes.index'))
            ->assertSessionHas('error', fn(string $m) => str_contains($m, "au statut « {$statut} »") && str_contains($m, 'désactivez-la plutôt'));

        $this->assertNotNull(Personne::find($p->id));
        $this->assertSame(0, AuditLog::where('action', 'delete')->count());
    }

    public function test_supprimer_une_personne_retire_ses_roles(): void
    {
        $p = Personne::factory()->archive()->membre()->create();
        $this->assertSame(1, DB::connection('commun')->table('ref_personnes_roles')->where('id_personne', $p->id)->count());

        $this->delete(route('personnes.destroy', $p->id));

        $this->assertSame(0, DB::connection('commun')->table('ref_personnes_roles')->where('id_personne', $p->id)->count());
    }

    /**
     * CARACTÉRISATION (intégrité) : les tables plan_* ne peuvent pas avoir de clé étrangère vers
     * ref_personnes (autre base). Supprimer un compte archivé laisse donc ses absences et ses
     * affectations passées pointer vers un id qui n'existe plus. Le code les tolère (relation
     * `personne` nulle → traité comme non assigné), mais l'historique perd son auteur : le bilan,
     * les statistiques et les échanges anciens affichent une personne inconnue.
     */
    public function test_supprimer_un_compte_archive_laisse_des_lignes_orphelines_dans_le_planning(): void
    {
        $this->tachesDeRotation();
        $p = Personne::factory()->archive()->create();
        $this->assigner($p, '2026-09-04', 'entree');
        $absence = Absence::factory()->pour($p)->create();

        $this->delete(route('personnes.destroy', $p->id))->assertSessionHas('success');

        $this->assertNull(Personne::find($p->id));
        $this->assertSame(1, CreneauTache::where('id_personne', $p->id)->count(), 'l\'affectation pointe vers une personne supprimée');
        $this->assertNotNull(Absence::find($absence->id));
        $this->assertNull(Absence::find($absence->id)->personne, 'relation vide');
    }

    // ── Lien de réinitialisation envoyé par l'administrateur ──────────────

    public function test_envoyer_un_lien_de_reinitialisation_previent_la_personne_et_l_administrateur(): void
    {
        Notification::fake();
        $p = Personne::factory()->create();

        $this->post(route('personnes.reset-link', $p->id))
            ->assertRedirect()
            ->assertSessionHas('success', "Lien de réinitialisation envoyé à {$p->email} ({$p->prenom} {$p->nom}).");

        Notification::assertSentTo($p, ResetPasswordNotification::class);
    }

    public function test_un_second_envoi_rapproche_est_refuse_sans_nouvel_e_mail(): void
    {
        Notification::fake();
        $p = Personne::factory()->create();
        $this->post(route('personnes.reset-link', $p->id));

        $this->post(route('personnes.reset-link', $p->id))
            ->assertSessionHas('warning', "Un lien vient déjà d'être envoyé à {$p->prenom} {$p->nom} : patientez une minute avant d'en renvoyer un.");

        Notification::assertSentToTimes($p, ResetPasswordNotification::class, 1);
    }
}
