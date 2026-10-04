<?php
// tests/Feature/Console/RappelServiceTest.php
//
// RappelService et ses deux commandes (amana:rappels-quotidiens, amana:rappels-imminents). Le
// service lit le planning via WebhookPayloadBuilder::buildPourDate(), envoie une notification par
// ligne assignée et mémorise chaque envoi dans plan_rappels_envoyes (anti-doublon).
//
// Tout est raisonné en heure de Paris (« maintenant » = Carbon::parse('… Europe/Paris')).
// Repère : le vendredi 2026-10-02 ; heure du cours par défaut 20:00.
// Notification::fake() : aucun e-mail ne part.

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Personne;
use App\Models\RappelEnvoye;
use App\Models\Tache;
use App\Notifications\RappelCreneauNotification;
use App\Services\RappelService;
use Carbon\Carbon;
use Database\Factories\TacheFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\Support\FauxSettings;
use Tests\TestCase;

class RappelServiceTest extends TestCase
{
    use CreeDonneesPlanning;
    use RefreshesBothDatabases;

    private const VENDREDI = '2026-10-02';

    private Personne $entree;

    private Personne $amana;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->tachesDeRotation();
        foreach (['rappel_sandwich', 'assistance_amana_food'] as $code) {
            TacheFactory::pourCode($code);
        }
        $this->entree = Personne::factory()->create(['prenom' => 'Elsa', 'nom' => 'Entree']);
        $this->amana = Personne::factory()->create(['prenom' => 'Amine', 'nom' => 'Amana']);
        $this->assigner($this->entree, self::VENDREDI, 'entree');
        $this->assigner($this->amana, self::VENDREDI, 'amana_food');
    }

    protected function tearDown(): void
    {
        FauxSettings::reinitialiser();
        parent::tearDown();
    }

    private function maintenant(string $dateHeure): void
    {
        $this->travelTo(Carbon::parse($dateHeure, 'Europe/Paris'));
    }

    private function service(): RappelService
    {
        return $this->app->make(RappelService::class);
    }

    /** @return array<string, list<string>> personne => codes de tâche rappelés (triés) */
    private function rappelsEnvoyes(): array
    {
        $parPersonne = [];
        Notification::assertSentTimes(RappelCreneauNotification::class, Notification::sent($this->entree, RappelCreneauNotification::class)->count() + Notification::sent($this->amana, RappelCreneauNotification::class)->count());
        foreach (['entree' => $this->entree, 'amana' => $this->amana] as $cle => $personne) {
            $codes = Notification::sent($personne, RappelCreneauNotification::class)
                ->map(fn($n) => (new ReflectionProperty($n, 'item'))->getValue($n)['code'])->sort()->values()->all();
            if ($codes) {
                $parPersonne[$cle] = $codes;
            }
        }

        return $parPersonne;
    }

    // ══ Rappels quotidiens ════════════════════════════════════════════════

    public function test_trois_jours_avant_chaque_personne_assignee_est_rappelee(): void
    {
        $this->maintenant('2026-09-29 08:00:00');

        $resultat = $this->service()->envoyerRappelsQuotidiens();

        // 2 tâches assignées + 2 événements spéciaux qui suivent ces personnes (assistance, rappel sandwich).
        $this->assertSame(['3_jours' => 4, 'jour_j' => 0], $resultat);
        $this->assertSame(['entree' => ['assistance_amana_food', 'entree'], 'amana' => ['amana_food', 'rappel_sandwich']], $this->rappelsEnvoyes());
    }

    public function test_le_jour_j_chaque_personne_assignee_est_rappelee(): void
    {
        $this->maintenant('2026-10-02 08:00:00');

        $resultat = $this->service()->envoyerRappelsQuotidiens();

        $this->assertSame(['3_jours' => 0, 'jour_j' => 4], $resultat);
    }

    public function test_un_jour_sans_creneau_n_envoie_rien(): void
    {
        $this->maintenant('2026-10-20 08:00:00');

        $this->assertSame(['3_jours' => 0, 'jour_j' => 0], $this->service()->envoyerRappelsQuotidiens());
        Notification::assertNothingSent();
    }

    public function test_chaque_envoi_est_memorise_avec_son_type(): void
    {
        $this->maintenant('2026-09-29 08:00:00');

        $this->service()->envoyerRappelsQuotidiens();

        $this->assertSame(4, RappelEnvoye::count());
        $ligne = RappelEnvoye::where('id_personne', $this->entree->id)->where('id_tache', TacheFactory::pourCode('entree')->id)->firstOrFail();
        $this->assertSame('3_jours', $ligne->type_rappel);
        $this->assertSame($this->creneauLe(self::VENDREDI)->id, (int) $ligne->id_planning);
        $this->assertSame(TacheFactory::pourCode('entree')->id, (int) $ligne->id_tache);
    }

    public function test_un_rappel_n_est_jamais_envoye_deux_fois(): void
    {
        $this->maintenant('2026-09-29 08:00:00');
        $this->service()->envoyerRappelsQuotidiens();

        $this->maintenant('2026-09-29 20:00:00'); // même jour, nouvelle exécution
        $secondePasse = $this->service()->envoyerRappelsQuotidiens();

        $this->assertSame(['3_jours' => 0, 'jour_j' => 0], $secondePasse);
        $this->assertSame(4, RappelEnvoye::count());
    }

    public function test_les_trois_types_de_rappel_sont_independants(): void
    {
        $this->maintenant('2026-09-29 08:00:00');
        $this->service()->envoyerRappelsQuotidiens();               // 3 jours avant
        $this->maintenant('2026-10-02 08:00:00');
        $this->service()->envoyerRappelsQuotidiens();               // jour J
        $this->maintenant('2026-10-02 17:05:00');
        $this->service()->envoyerRappelsImminents();                // 3 h avant

        $this->assertSame(4 + 4 + 3, RappelEnvoye::count());
        $this->assertEqualsCanonicalizing(['3_jours', 'jour_j', '3h_avant'], RappelEnvoye::pluck('type_rappel')->unique()->all());
    }

    public function test_une_tache_non_assignee_n_envoie_aucun_rappel(): void
    {
        $this->assigner(null, self::VENDREDI, 'salle');
        $this->maintenant('2026-09-29 08:00:00');

        $this->assertSame(4, $this->service()->envoyerRappelsQuotidiens()['3_jours'], 'la salle non assignée n\'ajoute aucun rappel');
    }

    public function test_les_evenements_speciaux_sont_rappeles_a_la_personne_concernee_quand_leur_tache_existe(): void
    {
        $this->maintenant('2026-10-02 08:00:00');

        $this->service()->envoyerRappelsQuotidiens();

        $this->assertSame(
            ['entree' => ['assistance_amana_food', 'entree'], 'amana' => ['amana_food', 'rappel_sandwich']],
            $this->rappelsEnvoyes(),
            'l\'assistance suit la personne à l\'entrée, le rappel sandwich celle d\'amana_food',
        );
    }

    /**
     * CARACTÉRISATION : un événement spécial dont le code n'existe pas dans ref_taches est ignoré
     * (avertissement dans les logs, aucun e-mail). Ici « rappel_sandwich » et « assistance_amana_food »
     * ne sont pas créés : seuls les rappels de tâches normales partent.
     */
    public function test_un_evenement_special_sans_ligne_dans_ref_taches_est_ignore(): void
    {
        Tache::whereIn('code', ['rappel_sandwich', 'assistance_amana_food'])->delete();
        $this->maintenant('2026-10-02 08:00:00');

        $this->assertSame(2, $this->service()->envoyerRappelsQuotidiens()['jour_j']);
    }

    public function test_un_echec_d_envoi_ne_memorise_rien_et_sera_retente(): void
    {
        $this->maintenant('2026-09-29 08:00:00');
        Notification::swap(new class
        {
            public function send(...$a): void
            {
                throw new \RuntimeException('SMTP indisponible');
            }

            public function sendNow(...$a): void
            {
                throw new \RuntimeException('SMTP indisponible');
            }
        });

        $this->assertSame(['3_jours' => 0, 'jour_j' => 0], $this->service()->envoyerRappelsQuotidiens());
        $this->assertSame(0, RappelEnvoye::count(), 'pas de trace : la prochaine exécution réessaiera');
    }

    public function test_la_date_de_reference_est_celle_de_paris_pas_celle_d_utc(): void
    {
        // 2026-10-01 22:30 UTC = 2026-10-02 00:30 à Paris (CEST) : « aujourd'hui » à Paris est le 02/10.
        $this->travelTo(Carbon::parse('2026-10-01 22:30:00', 'UTC'));

        $this->assertSame(4, $this->service()->envoyerRappelsQuotidiens()['jour_j']);
    }

    // ══ Rappels « 3 h avant » ═════════════════════════════════════════════

    /** @return array<string, array{string, int}> instant (Paris) → nombre de rappels attendus pour l'entrée et amana_food à 20:00 */
    public static function instantsAutourDeLaFenetre(): array
    {
        return [
            'une minute avant la fenêtre' => ['2026-10-02 16:59:00', 0],
            'pile à l\'ouverture (17:00)' => ['2026-10-02 17:00:00', 3],
            'au milieu' => ['2026-10-02 17:07:30', 3],
            'pile à la fermeture (17:15)' => ['2026-10-02 17:15:00', 3],
            'une seconde après' => ['2026-10-02 17:15:01', 0],
            'à l\'heure du cours' => ['2026-10-02 20:00:00', 0],
        ];
    }

    #[DataProvider('instantsAutourDeLaFenetre')]
    public function test_la_fenetre_de_trois_heures_avant_dure_quinze_minutes_bornes_incluses(string $instant, int $attendus): void
    {
        $this->maintenant($instant);

        $this->assertSame($attendus, $this->service()->envoyerRappelsImminents());
    }

    public function test_les_deux_passages_de_la_fenetre_n_envoient_qu_une_fois(): void
    {
        // Le planificateur passe toutes les 15 minutes : 17:00 et 17:15 tombent tous deux dans la fenêtre.
        $this->maintenant('2026-10-02 17:00:00');
        $premier = $this->service()->envoyerRappelsImminents();
        $this->maintenant('2026-10-02 17:15:00');
        $second = $this->service()->envoyerRappelsImminents();

        $this->assertSame([3, 0], [$premier, $second]);
    }

    public function test_le_rappel_sandwich_a_sa_propre_fenetre_le_matin(): void
    {
        $this->maintenant('2026-10-02 05:05:00'); // 08:00 − 3 h

        $this->assertSame(1, $this->service()->envoyerRappelsImminents());
        $this->assertSame(['amana' => ['rappel_sandwich']], $this->rappelsEnvoyes());
    }

    public function test_la_fenetre_suit_l_heure_du_cours_configuree(): void
    {
        FauxSettings::definir(['heure_cours' => '19:00']);

        $this->maintenant('2026-10-02 16:05:00');
        $this->assertSame(3, $this->service()->envoyerRappelsImminents(), '19:00 − 3 h = 16:00');
    }

    public function test_un_cours_tot_le_lendemain_est_rappele_la_veille_au_soir(): void
    {
        FauxSettings::definir(['heure_cours' => '01:00']); // le vendredi à 01:00 : fenêtre = jeudi 22:00–22:15

        $this->maintenant('2026-10-01 22:05:00');

        $this->assertSame(3, $this->service()->envoyerRappelsImminents(), 'le service regarde aussi la date de demain');
    }

    public function test_l_heure_est_celle_de_paris_en_hiver_comme_en_ete(): void
    {
        $this->assigner($this->entree, '2026-12-04', 'entree');
        $this->travelTo(Carbon::parse('2026-12-04 16:05:00', 'UTC')); // = 17:05 à Paris (UTC+1)

        $this->assertSame(2, $this->service()->envoyerRappelsImminents(), 'l\'entrée et l\'assistance qui la suit');
    }

    // ══ Les commandes ═════════════════════════════════════════════════════

    public function test_la_commande_des_rappels_quotidiens_affiche_les_deux_compteurs(): void
    {
        $this->maintenant('2026-09-29 08:00:00');

        $code = Artisan::call('amana:rappels-quotidiens');

        $this->assertSame(0, $code);
        $sortie = Artisan::output();
        $this->assertStringContainsString('3 jours avant : 4 rappel(s) envoyé(s).', $sortie);
        $this->assertStringContainsString('Jour J : 0 rappel(s) envoyé(s).', $sortie);
        $this->assertSame(4, RappelEnvoye::count());
    }

    public function test_la_commande_des_rappels_imminents_annonce_le_nombre_ou_l_absence_d_envoi(): void
    {
        $this->maintenant('2026-10-02 12:00:00');
        $this->assertSame(0, Artisan::call('amana:rappels-imminents'));
        $this->assertStringContainsString('Aucun rappel "3h avant" à envoyer pour le moment.', Artisan::output());

        $this->maintenant('2026-10-02 17:05:00');
        $this->assertSame(0, Artisan::call('amana:rappels-imminents'));
        $this->assertStringContainsString('3 rappel(s) "3h avant" envoyé(s).', Artisan::output());
    }
}
