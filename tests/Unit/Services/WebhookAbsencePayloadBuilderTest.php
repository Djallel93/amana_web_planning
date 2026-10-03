<?php
// tests/Unit/Services/WebhookAbsencePayloadBuilderTest.php
//
// Le seul accès externe est Setting::get('calendar_absence') : on pré-remplit
// son cache (voir FauxSettings). La personne est posée par setRelation().

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Helpers\GoogleCalendarColors;
use App\Models\Absence;
use App\Models\Personne;
use App\Services\WebhookAbsencePayloadBuilder;
use PHPUnit\Framework\TestCase;
use Tests\Support\FauxSettings;

class WebhookAbsencePayloadBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        FauxSettings::reinitialiser();
        parent::tearDown();
    }

    private function absence(array $attributs = [], ?Personne $personne = null, bool $sansPersonne = false): Absence
    {
        $absence = (new Absence())->setRawAttributes(array_replace([
            'id' => 42,
            'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-10',
            'raison' => 'Vacances',
            'google_calendar_id' => null,
            'google_event_id' => null,
        ], $attributs));

        $absence->setRelation('personne', $sansPersonne ? null : ($personne ?? (new Personne())->setRawAttributes(['prenom' => 'Jean', 'nom' => 'Dupont'])));

        return $absence;
    }

    public function test_upsert_payload_complet(): void
    {
        FauxSettings::definir(['calendar_absence' => 'absences@group.calendar.google.com']);

        $payload = (new WebhookAbsencePayloadBuilder())->buildUpsert($this->absence());

        $this->assertSame(['absence' => [
            'id_absence' => 42,
            'nom' => 'Absence — Jean Dupont',
            'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-10',
            'description' => 'Vacances',
            'couleur' => GoogleCalendarColors::ABSENCE,
            'calendar_id' => 'absences@group.calendar.google.com',
        ]], $payload);
    }

    public function test_upsert_couleur_fixe_grise(): void
    {
        FauxSettings::definir(['calendar_absence' => 'x@group.calendar.google.com']);

        $payload = (new WebhookAbsencePayloadBuilder())->buildUpsert($this->absence());

        $this->assertSame('8', $payload['absence']['couleur']);
    }

    public function test_upsert_sans_calendrier_configure_omet_calendar_id(): void
    {
        foreach ([null, ''] as $valeur) {
            FauxSettings::definir(['calendar_absence' => $valeur]);

            $payload = (new WebhookAbsencePayloadBuilder())->buildUpsert($this->absence());

            $this->assertFalse(array_key_exists('calendar_id', $payload['absence']), 'valeur ' . var_export($valeur, true));
        }
    }

    public function test_upsert_sans_raison_omet_la_description(): void
    {
        FauxSettings::definir(['calendar_absence' => 'x@group.calendar.google.com']);

        foreach ([null, ''] as $raison) {
            $payload = (new WebhookAbsencePayloadBuilder())->buildUpsert($this->absence(['raison' => $raison]));

            $this->assertFalse(array_key_exists('description', $payload['absence']), 'raison ' . var_export($raison, true));
        }
    }

    public function test_upsert_personne_supprimee_ou_inconnue(): void
    {
        FauxSettings::definir(['calendar_absence' => 'x@group.calendar.google.com']);

        $payload = (new WebhookAbsencePayloadBuilder())->buildUpsert($this->absence([], null, sansPersonne: true));

        $this->assertSame('Absence — Personne inconnue', $payload['absence']['nom']);
    }

    public function test_delete_ne_porte_que_l_id_et_le_calendrier_deja_synchronise(): void
    {
        $synchronisee = $this->absence(['google_calendar_id' => 'absences@group.calendar.google.com', 'google_event_id' => 'evt1']);
        $jamaisSynchronisee = $this->absence();

        $builder = new WebhookAbsencePayloadBuilder();

        $this->assertSame(['absence' => ['id_absence' => 42, 'calendar_id' => 'absences@group.calendar.google.com']], $builder->buildDelete($synchronisee));
        $this->assertSame(['absence' => ['id_absence' => 42]], $builder->buildDelete($jamaisSynchronisee), 'calendar_id null est retiré');
    }

    public function test_delete_ne_lit_pas_le_parametre_de_calendrier_courant(): void
    {
        // Si le calendrier des absences a changé depuis la synchronisation, on supprime
        // dans l'ANCIEN calendrier (celui enregistré sur l'absence), pas dans le nouveau.
        FauxSettings::definir(['calendar_absence' => 'nouveau@group.calendar.google.com']);

        $payload = (new WebhookAbsencePayloadBuilder())->buildDelete($this->absence(['google_calendar_id' => 'ancien@group.calendar.google.com']));

        $this->assertSame('ancien@group.calendar.google.com', $payload['absence']['calendar_id']);
    }

    public function test_has_calendar_sync_suit_le_parametre(): void
    {
        $builder = new WebhookAbsencePayloadBuilder();

        FauxSettings::definir(['calendar_absence' => 'x@group.calendar.google.com']);
        $this->assertTrue($builder->hasCalendarSync());

        FauxSettings::definir(['calendar_absence' => '']);
        $this->assertFalse($builder->hasCalendarSync());

        FauxSettings::definir(['calendar_absence' => null]);
        $this->assertFalse($builder->hasCalendarSync());
    }
}
