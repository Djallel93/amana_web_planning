<?php
// tests/Feature/Console/CommandesPlanningTest.php
//
// Les commandes qui enveloppent un service (amana:expire-echanges) ou lisent la configuration
// (amana:diagnostic-calendriers), et la définition du planificateur (routes/console.php) :
// un changement d'heure, de fréquence ou de fuseau y est un changement de comportement en production.
// (Les commandes de rappels sont dans RappelServiceTest, ResetAdminPassword dans ResetAdminPasswordCommandTest.)

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\CalendrierGoogle;
use App\Models\Echange;
use App\Notifications\Echanges\EchangeExpireNotification;
use App\Services\GoogleCalendarService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\Support\FauxSettings;
use Tests\Support\GoogleCalendarServiceEnregistre;
use Tests\TestCase;

class CommandesPlanningTest extends TestCase
{
    use RefreshesBothDatabases;

    private const CODES = ['entree', 'mektaba', 'salle', 'amana_food', 'cours', 'rappel_sandwich', 'assistance_amana_food', 'annonce_cours', 'message_bot', 'annulation_cours', 'absence'];

    protected function tearDown(): void
    {
        FauxSettings::reinitialiser();
        parent::tearDown();
    }

    // ══ amana:expire-echanges ═════════════════════════════════════════════

    public function test_expire_echanges_sans_rien_a_faire(): void
    {
        $this->assertSame(0, Artisan::call('amana:expire-echanges'));

        $sortie = Artisan::output();
        $this->assertStringContainsString('Vérification des échanges expirés', $sortie);
        $this->assertStringContainsString('Aucun échange expiré.', $sortie);
    }

    public function test_expire_echanges_delegue_au_service_et_annonce_le_nombre(): void
    {
        Notification::fake();
        $perime = Echange::factory()->lienExpire()->create();
        $valide = Echange::factory()->create();

        $this->assertSame(0, Artisan::call('amana:expire-echanges'));

        $this->assertStringContainsString('1 échange(s) expiré(s) — notifications envoyées.', Artisan::output());
        $this->assertSame(Echange::STATUT_EXPIRE, $perime->fresh()->statut);
        $this->assertSame(Echange::STATUT_EN_ATTENTE, $valide->fresh()->statut);
        Notification::assertSentTo($perime->demandeur, EchangeExpireNotification::class);
    }

    public function test_expire_echanges_est_idempotent(): void
    {
        Notification::fake();
        Echange::factory()->lienExpire()->create();

        Artisan::call('amana:expire-echanges');
        Artisan::call('amana:expire-echanges');

        $this->assertStringContainsString('Aucun échange expiré.', Artisan::output());
        Notification::assertSentTimes(EchangeExpireNotification::class, 1);
    }

    // ══ Planificateur ═════════════════════════════════════════════════════

    /** @return array<string, array{string, string, string}> */
    public static function tachesPlanifiees(): array // [nom, expression cron, fuseau]
    {
        return [
            'expiration des échanges' => ['amana:expire-echanges', '0 1 * * *', 'UTC'], // fuseau de l'application : volontairement pas Paris (voir routes/console.php)
            'rappels quotidiens' => ['amana:rappels-quotidiens', '0 8 * * *', 'Europe/Paris'],
            'rappels imminents' => ['amana:rappels-imminents', '*/15 * * * *', 'Europe/Paris'],
        ];
    }

    #[DataProvider('tachesPlanifiees')]
    public function test_la_planification_de_chaque_commande(string $nom, string $expression, string $fuseau): void
    {
        $evenement = collect($this->app->make(Schedule::class)->events())->first(fn($e) => $e->description === $nom);

        $this->assertNotNull($evenement, "« {$nom} » n'est pas planifiée");
        $this->assertSame($expression, $evenement->expression);
        $this->assertSame($fuseau, (string) $evenement->timezone);
        $this->assertStringContainsString($nom, $evenement->command);
    }

    public function test_seules_ces_trois_commandes_sont_planifiees(): void
    {
        $noms = collect($this->app->make(Schedule::class)->events())->pluck('description')->sort()->values()->all();

        $this->assertSame(['amana:expire-echanges', 'amana:rappels-imminents', 'amana:rappels-quotidiens'], $noms);
    }

    // ══ amana:diagnostic-calendriers ══════════════════════════════════════

    /** @param array<string, ?string> $valeurs code => calendar_id (null = non configuré) */
    private function configurer(array $valeurs): void
    {
        FauxSettings::definir(array_combine(
            array_map(fn($c) => "calendar_{$c}", array_keys($valeurs)),
            array_values($valeurs),
        ));
    }

    private function tousConfigures(string $calendarId = 'cal@group.calendar.google.com'): array
    {
        return array_fill_keys(self::CODES, $calendarId);
    }

    public function test_le_diagnostic_reussit_quand_tous_les_codes_ont_un_calendrier(): void
    {
        CalendrierGoogle::factory()->create(['nom' => 'Calendrier Général', 'calendar_id' => 'cal@group.calendar.google.com']);
        $this->configurer($this->tousConfigures());

        $this->assertSame(0, Artisan::call('amana:diagnostic-calendriers'));

        $sortie = Artisan::output();
        $this->assertStringContainsString('Tous les codes ont un calendrier configuré.', $sortie);
        $this->assertStringContainsString('Calendrier Général : cal@group.calendar.google.com', $sortie);
        $this->assertStringContainsString('✅ Calendrier Général', $sortie);
    }

    public function test_le_diagnostic_echoue_et_liste_les_codes_sans_calendrier(): void
    {
        $this->configurer(['entree' => null, 'salle' => '', 'cours' => 'cal@group.calendar.google.com'] + $this->tousConfigures());

        $this->assertSame(1, Artisan::call('amana:diagnostic-calendriers'));

        $sortie = Artisan::output();
        $this->assertStringContainsString('Sans calendrier configuré : Entrée, Salle', $sortie);
        $this->assertStringContainsString('❌ aucun événement créé', $sortie);
        $this->assertStringContainsString('relancer la génération du planning', $sortie);
    }

    public function test_un_calendrier_configure_mais_absent_du_registre_est_signale(): void
    {
        $this->configurer($this->tousConfigures('inconnu@group.calendar.google.com'));

        $this->assertSame(0, Artisan::call('amana:diagnostic-calendriers'), 'ce n\'est qu\'un avertissement : le code de sortie reste 0');

        $this->assertStringContainsString('absent du registre', Artisan::output());
    }

    public function test_un_registre_vide_est_annonce(): void
    {
        $this->configurer($this->tousConfigures());

        Artisan::call('amana:diagnostic-calendriers');

        $this->assertStringContainsString('(vide)', Artisan::output());
    }

    public function test_sans_l_option_api_google_n_est_pas_interroge(): void
    {
        $this->app->instance(GoogleCalendarService::class, new GoogleCalendarServiceEnregistre(erreursAcces: ['cal@group.calendar.google.com' => 404]));
        $this->configurer($this->tousConfigures());

        Artisan::call('amana:diagnostic-calendriers');

        $this->assertStringNotContainsString('introuvable', Artisan::output());
    }

    public function test_l_option_api_signale_une_api_non_configuree(): void
    {
        $this->configurer($this->tousConfigures());

        Artisan::call('amana:diagnostic-calendriers', ['--api' => true]); // double « interdit » de Tests\TestCase

        $this->assertStringContainsString('API non configurée', Artisan::output());
    }

    /** @return array<string, array{?int, string}> */
    public static function reponsesGoogle(): array
    {
        return [
            'accessible' => [null, 'accès OK'],
            '404' => [404, 'introuvable ou non partagé avec le compte de service'],
            '403' => [403, 'partagé mais droits insuffisants'],
            'autre code' => [500, 'erreur Google 500'],
        ];
    }

    #[DataProvider('reponsesGoogle')]
    public function test_l_option_api_interprete_la_reponse_de_google(?int $code, string $message): void
    {
        $google = new GoogleCalendarServiceEnregistre(erreursAcces: $code === null ? [] : ['cal@group.calendar.google.com' => $code]);
        $this->app->instance(GoogleCalendarService::class, $google);
        $this->configurer($this->tousConfigures());

        Artisan::call('amana:diagnostic-calendriers', ['--api' => true]);

        $this->assertStringContainsString($message, Artisan::output());
    }
}
