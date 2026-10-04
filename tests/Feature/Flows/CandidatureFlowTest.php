<?php
// tests/Feature/Flows/CandidatureFlowTest.php
//
// Parcours complet d'une candidature : formulaire public → compte « En attente » → validation par
// un administrateur (rôle, invitation, partage des calendriers) → création du mot de passe →
// connexion. Les e-mails sont interceptés ; Google Calendar est un double.

declare(strict_types=1);

namespace Tests\Feature\Flows;

use Amana\Shared\Models\AuditLog;
use Amana\Shared\Support\PhoneFr;
use App\Models\CalendrierGoogle;
use App\Models\Personne;
use App\Models\Restriction;
use App\Notifications\CandidatureValideeDejaInscritNotification;
use App\Notifications\CandidatureValideeNotification;
use App\Notifications\NouveauMembreNotification;
use App\Services\GoogleCalendarService;
use App\Services\RoleService;
use Database\Factories\TacheFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ConnecteParRole;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\Support\FauxSettings;
use Tests\Support\GoogleCalendarServiceEnregistre;
use Tests\TestCase;

class CandidatureFlowTest extends TestCase
{
    use ConnecteParRole;
    use CreeDonneesPlanning;
    use RefreshesBothDatabases;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->creerRolesPlanning();
    }

    protected function tearDown(): void
    {
        FauxSettings::reinitialiser();
        parent::tearDown();
    }

    private function formulaire(array $surcharge = []): array
    {
        return array_replace([
            'nom' => 'Benali', 'prenom' => 'Sofia', 'email' => 'sofia.benali@example.test', 'telephone' => '06 12 34 56 78',
        ], $surcharge);
    }

    /** L'URL de réinitialisation est une propriété privée du message (son nom varie selon la notification). */
    private function urlDe(object $notification): string
    {
        foreach ((new \ReflectionObject($notification))->getProperties() as $propriete) {
            $valeur = $propriete->getValue($notification);
            if (is_string($valeur) && str_contains($valeur, '/nouveau-mot-de-passe/')) {
                return $valeur;
            }
        }
        $this->fail('aucune URL de réinitialisation dans ' . $notification::class);
    }

    /** Dépose une candidature et renvoie le compte créé. */
    private function candidater(array $surcharge = []): Personne
    {
        $this->post(route('inscription.submit'), $this->formulaire($surcharge))->assertRedirect(route('login'));

        return Personne::where('email', $this->formulaire($surcharge)['email'])->firstOrFail();
    }

    // ══ Formulaire public ═════════════════════════════════════════════════

    public function test_le_formulaire_s_affiche_pour_un_invite(): void
    {
        $this->get(route('inscription'))->assertOk();
    }

    public function test_un_compte_connecte_est_renvoye_vers_le_planning(): void
    {
        $this->connecterEn('membre');

        $this->get(route('inscription'))->assertRedirect(route('planning.index'));
        $this->post(route('inscription.submit'), $this->formulaire())->assertRedirect(route('planning.index'));
        $this->assertSame(1, Personne::count(), 'rien n\'est créé : seulement le compte connecté');
    }

    public function test_une_candidature_valide_cree_un_compte_en_attente_sans_mot_de_passe(): void
    {
        $this->post(route('inscription.submit'), $this->formulaire())
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $p = Personne::where('email', 'sofia.benali@example.test')->firstOrFail();
        $this->assertSame('En attente', $p->statut);
        $this->assertEmpty($p->password, 'le mot de passe est créé plus tard, via le lien d\'invitation');
        $this->assertSame('06 12 34 56 78', $p->telephone);
        $this->assertNull($p->date_debut_planning, 'la rotation ne commence qu\'à la validation');
        $this->assertFalse($p->isMembre() || $p->isBenevole(), 'aucun rôle tant que non validé');
    }

    public function test_la_candidature_est_journalisee_sans_donnees_personnelles_superflues(): void
    {
        $p = $this->candidater();

        $entree = AuditLog::where('module', 'inscription')->where('action', 'create')->firstOrFail();
        $this->assertSame($p->id, (int) $entree->entity_id);
        $this->assertSame(['email' => $p->email, 'statut' => 'En attente'], $entree->after);
    }

    public function test_la_candidature_enregistre_un_creneau_de_disponibilite_par_tache_active_et_par_jour(): void
    {
        $taches = $this->tachesDeRotation();
        TacheFactory::pourCode('ancienne')->update(['actif' => false]);
        $entree = $taches['entree']->id;
        $salle = $taches['salle']->id;

        $p = $this->candidater(['restrictions' => [$entree => ['Vendredi' => '1', 'Samedi' => '1'], $salle => ['Samedi' => '1']]]);

        $this->assertSame(5 * 2, Restriction::where('id_personne', $p->id)->count(), '5 tâches actives × 2 jours ; la tâche inactive est ignorée');
        $this->assertTrue((bool) Restriction::where(['id_personne' => $p->id, 'id_tache' => $entree, 'jour' => 'Vendredi'])->value('autorise'));
        $this->assertTrue((bool) Restriction::where(['id_personne' => $p->id, 'id_tache' => $salle, 'jour' => 'Samedi'])->value('autorise'));
        $this->assertFalse((bool) Restriction::where(['id_personne' => $p->id, 'id_tache' => $salle, 'jour' => 'Vendredi'])->value('autorise'), 'case non cochée = interdit');
        $this->assertSame(0, Restriction::where('id_personne', $p->id)->where('id_tache', TacheFactory::pourCode('ancienne')->id)->count());
    }

    public function test_sans_aucune_case_cochee_le_candidat_est_indisponible_partout(): void
    {
        $this->tachesDeRotation();

        $p = $this->candidater();

        $this->assertSame(10, Restriction::where('id_personne', $p->id)->count());
        $this->assertSame(0, Restriction::where('id_personne', $p->id)->where('autorise', true)->count());
    }

    public function test_seuls_les_administrateurs_sont_prevenus_d_une_nouvelle_candidature(): void
    {
        $admin = Personne::factory()->admin()->create();
        $gestionnaire = Personne::factory()->gestionnaire()->create();
        $membre = Personne::factory()->membre()->create();

        $p = $this->candidater();

        Notification::assertSentTo($admin, NouveauMembreNotification::class);
        Notification::assertNotSentTo($gestionnaire, NouveauMembreNotification::class);
        Notification::assertNotSentTo($membre, NouveauMembreNotification::class);
        Notification::assertNotSentTo($p, NouveauMembreNotification::class);
    }

    public function test_sans_administrateur_la_candidature_est_quand_meme_enregistree(): void
    {
        $this->candidater();

        Notification::assertNothingSent();
        $this->assertSame(1, Personne::where('statut', 'En attente')->count());
    }

    public function test_un_echec_d_envoi_de_la_notification_n_empeche_pas_l_enregistrement(): void
    {
        Personne::factory()->admin()->create();
        Notification::swap(new class
        {
            public function send(...$args): void
            {
                throw new \RuntimeException('serveur SMTP indisponible');
            }
        });

        $this->post(route('inscription.submit'), $this->formulaire())->assertRedirect(route('login'))->assertSessionHas('success');

        $this->assertNotNull(Personne::where('email', 'sofia.benali@example.test')->first());
    }

    public function test_quand_les_inscriptions_sont_fermees_rien_n_est_cree(): void
    {
        FauxSettings::definir(['inscription_ouverte' => false]);

        $this->post(route('inscription.submit'), $this->formulaire())
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Les inscriptions sont actuellement fermées.');

        $this->assertSame(0, Personne::count());
    }

    public function test_sans_reglage_les_inscriptions_sont_ouvertes(): void
    {
        FauxSettings::definir(['inscription_ouverte' => null]);

        $this->candidater();

        $this->assertSame(1, Personne::count());
    }

    /** @return array<string, array{array, string}> */
    public static function formulairesInvalides(): array
    {
        return [
            'nom manquant' => [['nom' => ''], 'nom'],
            'prénom manquant' => [['prenom' => ''], 'prenom'],
            'email manquant' => [['email' => ''], 'email'],
            'email mal formé' => [['email' => 'pas-un-email'], 'email'],
            'nom trop long' => [['nom' => str_repeat('a', 101)], 'nom'],
            'prénom trop long' => [['prenom' => str_repeat('a', 101)], 'prenom'],
            'téléphone invalide' => [['telephone' => '12345'], 'telephone'],
            'téléphone étranger' => [['telephone' => '+44 20 7946 0958'], 'telephone'],
            'restrictions pas un tableau' => [['restrictions' => 'oui'], 'restrictions'],
        ];
    }

    /** @dataProvider formulairesInvalides */
    #[DataProvider('formulairesInvalides')]
    public function test_un_formulaire_invalide_ne_cree_rien(array $surcharge, string $champ): void
    {
        $this->post(route('inscription.submit'), $this->formulaire($surcharge))->assertSessionHasErrors($champ);

        $this->assertSame(0, Personne::count());
    }

    public function test_le_telephone_est_facultatif_et_accepte_les_formats_francais(): void
    {
        foreach ([null, '0612345678', '+33 6 12 34 56 78', '0033612345678'] as $i => $tel) {
            $this->post(route('inscription.submit'), $this->formulaire(['email' => "t{$i}@example.test", 'telephone' => $tel]))
                ->assertSessionDoesntHaveErrors();
        }
        $this->assertSame(4, Personne::count());
        $this->assertMatchesRegularExpression(PhoneFr::REGEX, '06 12 34 56 78');
    }

    public function test_un_email_deja_utilise_est_refuse_quel_que_soit_le_statut_et_la_casse(): void
    {
        $existant = Personne::factory()->archive()->create(['email' => 'deja.pris@example.test']);

        $this->post(route('inscription.submit'), $this->formulaire(['email' => 'DEJA.PRIS@example.test']))
            ->assertSessionHasErrors(['email' => 'Cette adresse email est déjà utilisée.']);

        $this->assertSame(1, Personne::count());
        $this->assertSame('Archivé', $existant->fresh()->statut, 'le compte archivé n\'est pas réactivé par une nouvelle candidature');
    }

    // ══ Validation par un administrateur ══════════════════════════════════

    public function test_la_liste_ne_montre_que_les_candidatures_en_attente_et_les_roles_attribuables(): void
    {
        $this->connecterEn('admin');
        $attente = Personne::factory()->enAttente()->create();
        Personne::factory()->create();
        Personne::factory()->archive()->create();

        $reponse = $this->get(route('admin.candidatures.index'))->assertOk();

        $this->assertSame([$attente->id], $reponse->viewData('candidatures')->pluck('id')->all());
        $this->assertEqualsCanonicalizing(['admin', 'gestionnaire', 'membre'], $reponse->viewData('roles')->pluck('code')->values()->all(), 'pas de « benevole » dans la liste');
    }

    public function test_valider_active_le_compte_attribue_le_role_et_envoie_l_invitation(): void
    {
        $p = $this->candidater();
        $this->connecterEn('admin');
        $this->travelTo('2026-09-14 10:00:00');

        $this->post(route('admin.candidatures.valider', $p->id), ['role' => 'gestionnaire'])
            ->assertRedirect(route('admin.candidatures.index'))
            ->assertSessionHas('success', "Candidature de {$p->prenom} {$p->nom} validée (rôle : gestionnaire). Email d'invitation envoyé.");

        $p->refresh();
        $this->assertSame('Validé', $p->statut);
        $this->assertSame('2026-09-14', $p->date_debut_planning->toDateString());
        $this->assertSame('gestionnaire', (new RoleService())->currentRoleCode($p));
        Notification::assertSentTo($p, CandidatureValideeNotification::class);
        Notification::assertNotSentTo($p, CandidatureValideeDejaInscritNotification::class);
    }

    /**
     * CARACTÉRISATION : si le rôle choisi n'existe pas dans ref_roles pour l'application
     * « planning » (référentiel non initialisé par PlanningApplicationSeeder, ou rôle supprimé),
     * RoleService::syncRolePlanning() n'attribue rien et ne signale rien. La validation répond
     * pourtant « validée (rôle : gestionnaire) » : le compte est actif mais sans aucun droit.
     */
    public function test_valider_avec_un_role_absent_du_referentiel_annonce_un_role_qui_n_est_pas_attribue(): void
    {
        $p = Personne::factory()->enAttente()->create();
        $this->connecterEn('admin');
        DB::connection('commun')->table('ref_roles')->where('code', 'gestionnaire')->delete();

        $this->post(route('admin.candidatures.valider', $p->id), ['role' => 'gestionnaire'])
            ->assertSessionHas('success', fn(string $m) => str_contains($m, 'rôle : gestionnaire'));

        $this->assertSame('Validé', $p->fresh()->statut);
        $this->assertNull((new RoleService())->currentRoleCode($p), 'aucun rôle réellement attribué');
    }

    public function test_valider_est_journalise_avec_le_role_et_l_etat_precedent(): void
    {
        $p = $this->candidater();
        $this->connecterEn('admin');

        $this->post(route('admin.candidatures.valider', $p->id), ['role' => 'membre']);

        $entree = AuditLog::where('module', 'candidatures')->firstOrFail();
        $this->assertSame('En attente', $entree->before['statut']);
        // assertEquals : la colonne JSON de MySQL ne conserve pas l'ordre des clés.
        $this->assertEquals(['statut' => 'Validé', 'action' => 'validation', 'role' => 'membre', 'deja_mot_de_passe' => false], $entree->after);
    }

    public function test_de_bout_en_bout_candidature_validation_creation_du_mot_de_passe_puis_connexion(): void
    {
        $this->tachesDeRotation();
        $p = $this->candidater(['restrictions' => [TacheFactory::pourCode('entree')->id => ['Vendredi' => '1']]]);
        $admin = $this->connecterEn('admin');

        // 1. L'administrateur valide.
        $this->post(route('admin.candidatures.valider', $p->id), ['role' => 'membre'])->assertSessionHas('success');
        $url = null;
        Notification::assertSentTo($p, CandidatureValideeNotification::class, function ($n) use (&$url) {
            $url = $this->urlDe($n);

            return true;
        });
        $this->assertStringContainsString('/nouveau-mot-de-passe/', $url);
        $this->assertStringContainsString('email=' . urlencode($p->email), $url);

        // 2. Le candidat (plus connecté en admin) suit le lien et choisit son mot de passe.
        $this->post(route('logout'));
        preg_match('#/nouveau-mot-de-passe/([A-Za-z0-9]+)#', $url, $m);
        $this->post(route('password.update'), [
            'token' => $m[1], 'email' => $p->email, 'password' => 'MonMdp-Solide-2026', 'password_confirmation' => 'MonMdp-Solide-2026',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        // 3. Il se connecte, avec son rôle, et n'accède pas aux pages d'administration.
        $this->post(route('login.submit'), ['email' => $p->email, 'password' => 'MonMdp-Solide-2026'])->assertRedirect(route('planning.index'));
        $this->assertAuthenticatedAs($p);
        $this->get(route('bilan.index'))->assertOk();
        $this->get(route('personnes.index'))->assertRedirect(route('planning.index'));

        // 4. Il est désormais dans la rotation.
        $this->assertTrue(Personne::actifAuPlanning()->whereKey($p->id)->exists());
        $this->assertNotSame($admin->id, $p->id);
    }

    public function test_une_personne_qui_a_deja_un_mot_de_passe_recoit_un_lien_de_connexion_directe(): void
    {
        $p = Personne::factory()->enAttente()->create();
        $this->connecterEn('admin');

        $this->post(route('admin.candidatures.valider', $p->id), ['role' => 'membre'])
            ->assertSessionHas('success', "Candidature de {$p->prenom} {$p->nom} validée (rôle : membre). Email de connexion directe envoyé.");

        Notification::assertSentTo($p, CandidatureValideeDejaInscritNotification::class);
        Notification::assertNotSentTo($p, CandidatureValideeNotification::class);
        $this->assertSame(0, DB::table('password_reset_tokens')->count(), 'aucun jeton créé');
    }

    public function test_valider_exige_un_role_parmi_admin_gestionnaire_membre(): void
    {
        $p = $this->candidater();
        $this->connecterEn('admin');

        $this->post(route('admin.candidatures.valider', $p->id), [])->assertSessionHasErrors(['role' => 'Veuillez sélectionner un rôle.']);
        foreach (['benevole', 'superadmin', ''] as $role) {
            $this->post(route('admin.candidatures.valider', $p->id), ['role' => $role])->assertSessionHasErrors('role');
        }

        $this->assertSame('En attente', $p->fresh()->statut);
        Notification::assertNotSentTo($p, CandidatureValideeNotification::class);
    }

    public function test_valider_une_candidature_qui_n_est_plus_en_attente_ne_change_rien(): void
    {
        $this->connecterEn('admin');
        $deja = Personne::factory()->create(['statut' => 'Validé']);

        $this->post(route('admin.candidatures.valider', $deja->id), ['role' => 'admin'])
            ->assertRedirect(route('admin.candidatures.index'))
            ->assertSessionHas('error', 'Cette candidature n\'est plus en attente.');

        Notification::assertNothingSent();
        $this->assertFalse($deja->fresh()->isAdmin(), 'pas d\'élévation de rôle par ce biais');
    }

    public function test_valider_un_compte_inconnu_donne_404(): void
    {
        $this->connecterEn('admin');

        $this->post(route('admin.candidatures.valider', 999999), ['role' => 'membre'])->assertNotFound();
    }

    public function test_un_echec_d_envoi_de_l_invitation_n_annule_pas_la_validation(): void
    {
        $p = $this->candidater();
        $this->connecterEn('admin');
        Notification::swap(new class
        {
            public function send(...$args): void
            {
                throw new \RuntimeException('serveur SMTP indisponible');
            }

            public function sendNow(...$args): void
            {
                throw new \RuntimeException('serveur SMTP indisponible');
            }
        });

        $this->post(route('admin.candidatures.valider', $p->id), ['role' => 'membre'])->assertSessionHas('success');

        $this->assertSame('Validé', $p->fresh()->statut);
    }

    // ── Partage des calendriers Google ────────────────────────────────────

    public function test_valider_partage_les_calendriers_marques_avec_le_bon_niveau_d_acces(): void
    {
        $google = new GoogleCalendarServiceEnregistre();
        $this->app->instance(GoogleCalendarService::class, $google);
        CalendrierGoogle::factory()->partageAvecNouveauxMembres()->create(['calendar_id' => 'planning@group.calendar.google.com']);
        CalendrierGoogle::factory()->create(['calendar_id' => 'non-partage@group.calendar.google.com']);
        CalendrierGoogle::factory()->partageAvecNouveauxMembres()->inactif()->create(['calendar_id' => 'inactif@group.calendar.google.com']);
        $this->connecterEn('admin');

        foreach (['membre' => 'reader', 'gestionnaire' => 'writer', 'admin' => 'owner'] as $role => $acces) {
            $google->partages = [];
            $p = Personne::factory()->enAttente()->create();

            $this->post(route('admin.candidatures.valider', $p->id), ['role' => $role])->assertSessionMissing('warning');

            $this->assertSame([['calendrier' => 'planning@group.calendar.google.com', 'email' => $p->email, 'role' => $acces]], $google->partages, $role);
        }
    }

    public function test_un_partage_refuse_est_signale_sans_bloquer_la_validation(): void
    {
        $this->app->instance(GoogleCalendarService::class, new GoogleCalendarServiceEnregistre(['ko@group.calendar.google.com']));
        CalendrierGoogle::factory()->partageAvecNouveauxMembres()->create(['calendar_id' => 'ko@group.calendar.google.com', 'nom' => 'Calendrier KO']);
        $p = Personne::factory()->enAttente()->create();
        $this->connecterEn('admin');

        $this->post(route('admin.candidatures.valider', $p->id), ['role' => 'membre'])
            ->assertSessionHas('success')
            ->assertSessionHas('warning', fn(string $m) => str_contains($m, 'Calendrier KO'));

        $this->assertSame('Validé', $p->fresh()->statut);
    }

    // ── Refus ─────────────────────────────────────────────────────────────

    public function test_refuser_archive_la_candidature_sans_rien_envoyer(): void
    {
        $p = $this->candidater();
        $this->connecterEn('admin');

        $this->post(route('admin.candidatures.refuser', $p->id))
            ->assertRedirect(route('admin.candidatures.index'))
            ->assertSessionHas('success', "Candidature de {$p->prenom} {$p->nom} refusée.");

        $this->assertSame('Archivé', $p->fresh()->statut);
        Notification::assertNotSentTo($p, CandidatureValideeNotification::class);
        $entree = AuditLog::where('module', 'candidatures')->firstOrFail();
        $this->assertSame('candidature refusée', $entree->after['action']);
    }

    public function test_refuser_une_candidature_qui_n_est_plus_en_attente_ne_change_rien(): void
    {
        $this->connecterEn('admin');
        $valide = Personne::factory()->create();

        $this->post(route('admin.candidatures.refuser', $valide->id))->assertSessionHas('error', 'Cette candidature n\'est plus en attente.');

        $this->assertSame('Validé', $valide->fresh()->statut);
    }

    public function test_un_candidat_refuse_ne_peut_pas_se_connecter_et_l_adresse_reste_prise(): void
    {
        $p = $this->candidater();
        $this->connecterEn('admin');
        $this->post(route('admin.candidatures.refuser', $p->id));
        $this->post(route('logout'));

        $this->post(route('login.submit'), ['email' => $p->email, 'password' => 'nimporte-quoi-1'])->assertSessionHasErrors(['email' => 'Ce compte est archivé.']);
        $this->post(route('inscription.submit'), $this->formulaire())->assertSessionHasErrors('email');
    }

    // ── Renvoi d'invitation ───────────────────────────────────────────────

    public function test_renvoyer_l_invitation_cree_un_nouveau_jeton_pour_un_compte_sans_mot_de_passe(): void
    {
        $p = Personne::factory()->sansMotDePasse()->create();
        $this->connecterEn('admin');

        $this->post(route('admin.candidatures.renvoyer-invitation', $p->id))
            ->assertSessionHas('success', "Invitation renvoyée à {$p->prenom} {$p->nom}.");

        Notification::assertSentTo($p, CandidatureValideeNotification::class);
        $this->assertSame(1, AuditLog::where('module', 'candidatures')->where('after->action', 'invitation renvoyée')->count());
    }

    public function test_renvoyer_l_invitation_a_un_compte_actif_envoie_le_lien_de_connexion(): void
    {
        $p = Personne::factory()->create();
        $this->connecterEn('admin');

        $this->post(route('admin.candidatures.renvoyer-invitation', $p->id))
            ->assertSessionHas('success', "Email de connexion renvoyé à {$p->prenom} {$p->nom}.");

        Notification::assertSentTo($p, CandidatureValideeDejaInscritNotification::class);
    }

    public function test_on_ne_renvoie_pas_d_invitation_a_un_compte_non_valide(): void
    {
        $this->connecterEn('admin');

        foreach (['enAttente', 'suspendu', 'archive'] as $etat) {
            $p = Personne::factory()->{$etat}()->create();
            $this->post(route('admin.candidatures.renvoyer-invitation', $p->id))
                ->assertSessionHas('error', 'Impossible de renvoyer une invitation à un compte non validé.');
            Notification::assertNotSentTo($p, CandidatureValideeNotification::class);
        }
    }
}
