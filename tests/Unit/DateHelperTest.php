<?php

namespace Tests\Unit;

use App\Helpers\DateHelper;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DateHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_est_passe_compare_a_la_date_du_jour(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-19 12:00:00', 'UTC'));

        $this->assertTrue(DateHelper::estPasse('2026-09-18'));
        $this->assertTrue(DateHelper::estPasse('2026-09-12'));
        $this->assertFalse(DateHelper::estPasse('2026-09-19'), "aujourd'hui n'est pas « passé »");
        $this->assertFalse(DateHelper::estPasse('2026-09-20'));
    }

    public function test_est_passe_suit_la_date_du_jour_a_paris_et_non_utc(): void
    {
        // 23:30 UTC le 18/09 = 01:30 à Paris le 19/09 (UTC+2 en été) : pour les
        // bénévoles, le 18 est déjà hier alors qu'en UTC il est encore aujourd'hui.
        Carbon::setTestNow(Carbon::parse('2026-09-18 23:30:00', 'UTC'));

        $this->assertTrue(DateHelper::estPasse('2026-09-18'));
        $this->assertFalse(DateHelper::estPasse('2026-09-19'));
    }

    public function test_aujourdhui_est_minuit_a_paris(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-18 23:30:00', 'UTC')); // 01:30 le 19/09 à Paris

        $aujourdhui = DateHelper::aujourdhui();

        $this->assertSame('2026-09-19', $aujourdhui->toDateString());
        $this->assertSame('00:00:00', $aujourdhui->format('H:i:s'));
        $this->assertSame(DateHelper::FUSEAU_METIER, $aujourdhui->getTimezone()->getName());
    }

    public function test_est_passe_gere_le_passage_a_l_heure_d_hiver(): void
    {
        // Le 25/10/2026 à 23:30 UTC il est 00:30 à Paris le 26/10 (UTC+1, hiver) :
        // le décalage change (été UTC+2 → hiver UTC+1) mais la règle reste « date de Paris ».
        Carbon::setTestNow(Carbon::parse('2026-10-25 23:30:00', 'UTC'));

        $this->assertTrue(DateHelper::estPasse('2026-10-25'));
        $this->assertFalse(DateHelper::estPasse('2026-10-26'));
    }

    public function test_premier_vendredi_d_un_vendredi_est_lui_meme(): void
    {
        $this->assertSame('2026-09-18', DateHelper::premierVendredi('2026-09-18')->toDateString());
    }

    /** @return array<string, array{string, string}> */
    public static function joursVersLePremierVendredi(): array
    {
        return [
            'samedi → vendredi suivant (6 jours)' => ['2026-09-19', '2026-09-25'],
            'dimanche' => ['2026-09-20', '2026-09-25'],
            'lundi' => ['2026-09-14', '2026-09-18'],
            'jeudi → lendemain' => ['2026-09-17', '2026-09-18'],
            'passage de mois' => ['2026-09-29', '2026-10-02'],
            'passage d\'année' => ['2026-12-28', '2027-01-01'],
        ];
    }

    #[DataProvider('joursVersLePremierVendredi')]
    public function test_premier_vendredi_avance_jusqu_au_vendredi(string $depart, string $attendu): void
    {
        $vendredi = DateHelper::premierVendredi($depart);

        $this->assertSame($attendu, $vendredi->toDateString());
        $this->assertSame(Carbon::FRIDAY, $vendredi->dayOfWeek);
    }

    public function test_premier_vendredi_ramene_a_minuit_et_ignore_l_heure_donnee(): void
    {
        $vendredi = DateHelper::premierVendredi('2026-09-19 15:45:00');

        $this->assertSame('2026-09-25 00:00:00', $vendredi->format('Y-m-d H:i:s'));
    }
}
