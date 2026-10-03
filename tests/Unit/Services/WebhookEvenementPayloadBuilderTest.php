<?php
// tests/Unit/Services/WebhookEvenementPayloadBuilderTest.php
//
// Les modèles sont construits avec setRawAttributes() (pas d'accès base) et les
// relations tachesBloquees / calendriers sont posées avec setRelation() : le
// builder ne charge une relation que si elle n'est pas déjà chargée.

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Evenement;
use App\Models\EvenementCalendrier;
use App\Models\Tache;
use App\Services\WebhookEvenementPayloadBuilder;
use PHPUnit\Framework\TestCase;

class WebhookEvenementPayloadBuilderTest extends TestCase
{
    /**
     * @param list<string|null> $calendriers google_calendar_id des lignes ref_evenements_calendriers
     * @param list<string>      $tachesBloquees codes de tâches
     */
    private function evenement(array $attributs = [], array $calendriers = ['a@group.calendar.google.com'], array $tachesBloquees = ['amana_food', 'entree']): Evenement
    {
        $evenement = (new Evenement())->setRawAttributes(array_replace([
            'id' => 12,
            'nom' => 'Ramadan',
            'date_debut' => '2027-03-01',
            'date_fin' => '2027-03-30',
            'description' => 'Horaires adaptés',
            'couleur' => '10',
        ], $attributs));

        $evenement->setRelation('calendriers', collect(array_map(
            fn(?string $id) => (new EvenementCalendrier())->setRawAttributes(['google_calendar_id' => $id]),
            $calendriers,
        )));
        $evenement->setRelation('tachesBloquees', collect(array_map(
            fn(string $code) => (new Tache())->setRawAttributes(['code' => $code]),
            $tachesBloquees,
        )));

        return $evenement;
    }

    public function test_upsert_payload_complet(): void
    {
        $payload = (new WebhookEvenementPayloadBuilder())->buildUpsert($this->evenement());

        $this->assertSame(['evenement' => [
            'id_evenement' => 12,
            'nom' => 'Ramadan',
            'date_debut' => '2027-03-01',
            'date_fin' => '2027-03-30',
            'description' => 'Horaires adaptés',
            'couleur' => '10',
            'calendar_ids' => ['a@group.calendar.google.com'],
            'taches_bloquees' => ['amana_food', 'entree'],
        ]], $payload);
    }

    public function test_upsert_retire_la_couleur_quand_elle_est_null_mais_garde_la_description_vide(): void
    {
        $payload = (new WebhookEvenementPayloadBuilder())->buildUpsert($this->evenement(['couleur' => null, 'description' => null]));

        $this->assertArrayNotHasKeyCompat('couleur', $payload['evenement']);
        $this->assertSame('', $payload['evenement']['description'], 'description null → chaîne vide, pas de clé retirée');
    }

    public function test_upsert_conserve_les_listes_vides(): void
    {
        $payload = (new WebhookEvenementPayloadBuilder())->buildUpsert($this->evenement([], [], []));

        $this->assertSame([], $payload['evenement']['calendar_ids']);
        $this->assertSame([], $payload['evenement']['taches_bloquees']);
    }

    public function test_upsert_ignore_les_calendriers_sans_identifiant_google(): void
    {
        $payload = (new WebhookEvenementPayloadBuilder())->buildUpsert($this->evenement([], ['a@group.calendar.google.com', null, '', 'b@group.calendar.google.com']));

        $this->assertSame(['a@group.calendar.google.com', 'b@group.calendar.google.com'], $payload['evenement']['calendar_ids']);
    }

    public function test_delete_ne_porte_que_l_identite_et_les_calendriers(): void
    {
        $payload = (new WebhookEvenementPayloadBuilder())->buildDelete($this->evenement());

        $this->assertSame(['evenement' => [
            'id_evenement' => 12,
            'nom' => 'Ramadan',
            'date_debut' => '2027-03-01',
            'date_fin' => '2027-03-30',
            'calendar_ids' => ['a@group.calendar.google.com'],
        ]], $payload);
    }

    private function assertArrayNotHasKeyCompat(string $cle, array $tableau): void
    {
        $this->assertFalse(array_key_exists($cle, $tableau), "la clé « {$cle} » ne devrait pas être présente");
    }
}
