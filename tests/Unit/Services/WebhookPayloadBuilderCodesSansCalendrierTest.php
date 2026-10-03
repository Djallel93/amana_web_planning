<?php
// tests/Unit/Services/WebhookPayloadBuilderCodesSansCalendrierTest.php
//
// WebhookPayloadBuilder::codesSansCalendrier() est statique et pure. Les
// build*() de la même classe lisent Tache::all() et les créneaux : elles sont
// testées avec la base (tests/Feature/Services).

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\WebhookPayloadBuilder;
use PHPUnit\Framework\TestCase;

class WebhookPayloadBuilderCodesSansCalendrierTest extends TestCase
{
    private function ligne(string $code, array $calendarIds): array
    {
        return ['code' => $code, 'calendar_ids' => $calendarIds];
    }

    public function test_liste_les_codes_sans_calendrier_dans_l_ordre_du_payload_sans_doublon(): void
    {
        $payload = ['creneaux' => [
            ['taches' => [$this->ligne('entree', []), $this->ligne('salle', ['s@x'])]],
            ['taches' => [$this->ligne('entree', []), $this->ligne('amana_food', [])]],
        ]];

        $this->assertSame(['entree', 'amana_food'], WebhookPayloadBuilder::codesSansCalendrier($payload));
    }

    public function test_parcourt_taches_speciaux_et_sociaux(): void
    {
        $payload = ['creneaux' => [[
            'taches' => [$this->ligne('entree', [])],
            'evenements_speciaux' => [$this->ligne('rappel_sandwich', [])],
            'evenements_sociaux' => [$this->ligne('annonce_cours', []), $this->ligne('message_bot', ['b@x'])],
        ]]];

        $this->assertSame(['entree', 'rappel_sandwich', 'annonce_cours'], WebhookPayloadBuilder::codesSansCalendrier($payload));
    }

    public function test_des_identifiants_vides_comptent_comme_pas_de_calendrier(): void
    {
        $payload = ['creneaux' => [['taches' => [$this->ligne('entree', ['', null])]]]];

        $this->assertSame(['entree'], WebhookPayloadBuilder::codesSansCalendrier($payload));
    }

    public function test_ligne_sans_calendar_ids_ou_sans_code(): void
    {
        $payload = ['creneaux' => [['taches' => [
            ['code' => 'entree'],                 // pas de clé calendar_ids → sans calendrier
            ['calendar_ids' => []],               // pas de code → ignorée
            ['code' => '', 'calendar_ids' => []], // code vide → ignorée
        ]]]];

        $this->assertSame(['entree'], WebhookPayloadBuilder::codesSansCalendrier($payload));
    }

    public function test_payload_vide_ou_entierement_configure(): void
    {
        $this->assertSame([], WebhookPayloadBuilder::codesSansCalendrier([]));
        $this->assertSame([], WebhookPayloadBuilder::codesSansCalendrier(['creneaux' => []]));
        $this->assertSame([], WebhookPayloadBuilder::codesSansCalendrier(['creneaux' => [['taches' => [$this->ligne('entree', ['a@x'])]]]]));
    }
}
