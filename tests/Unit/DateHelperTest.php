<?php

namespace Tests\Unit;

use App\Helpers\DateHelper;
use Carbon\Carbon;
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
}
