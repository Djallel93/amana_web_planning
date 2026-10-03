<?php
// tests/Unit/Helpers/GoogleCalendarColorsTest.php
//
// Ce ne sont que des constantes, mais ce sont des constantes qui partent chez
// Google : un colorId hors 1..11 est rejeté par l'API à l'envoi, pas à
// l'écriture du code. On verrouille donc la cohérence interne du tableau.

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\GoogleCalendarColors;
use PHPUnit\Framework\TestCase;

class GoogleCalendarColorsTest extends TestCase
{
    public function test_la_palette_couvre_exactement_les_onze_couleurs_google(): void
    {
        $this->assertSame(array_map('strval', range(1, 11)), array_map('strval', array_keys(GoogleCalendarColors::PALETTE)));
    }

    public function test_chaque_couleur_a_un_nom_et_un_hex_valide(): void
    {
        foreach (GoogleCalendarColors::PALETTE as $id => $couleur) {
            $this->assertNotSame('', $couleur['nom'], "couleur {$id} : nom");
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $couleur['hex'], "couleur {$id} : hex");
        }
    }

    public function test_les_couleurs_de_taches_et_d_absence_existent_dans_la_palette(): void
    {
        $this->assertArrayHasKey(GoogleCalendarColors::ABSENCE, GoogleCalendarColors::PALETTE);

        foreach (GoogleCalendarColors::TACHES as $code => $colorId) {
            $this->assertIsString($colorId, "{$code} : le colorId doit être une chaîne (payload Google)");
            $this->assertArrayHasKey($colorId, GoogleCalendarColors::PALETTE, "{$code} → colorId inconnu de Google");
        }
    }

    public function test_les_cinq_taches_principales_et_les_evenements_derives_ont_une_couleur(): void
    {
        foreach (['entree', 'mektaba', 'salle', 'amana_food', 'cours', 'rappel_sandwich', 'assistance_amana_food', 'annonce_cours', 'message_bot', 'annulation_cours'] as $code) {
            $this->assertArrayHasKey($code, GoogleCalendarColors::TACHES, $code);
        }
    }

    public function test_les_cinq_taches_principales_ont_des_couleurs_distinctes(): void
    {
        $couleurs = array_map(fn(string $c) => GoogleCalendarColors::TACHES[$c], ['entree', 'mektaba', 'salle', 'amana_food', 'cours']);

        $this->assertCount(5, array_unique($couleurs));
    }

    public function test_un_evenement_derive_se_distingue_de_la_tache_dont_il_depend(): void
    {
        $this->assertNotSame(GoogleCalendarColors::TACHES['rappel_sandwich'], GoogleCalendarColors::TACHES['amana_food']);
        $this->assertNotSame(GoogleCalendarColors::TACHES['assistance_amana_food'], GoogleCalendarColors::TACHES['entree']);
        $this->assertNotSame(GoogleCalendarColors::TACHES['annulation_cours'], GoogleCalendarColors::TACHES['amana_food']);
    }
}
