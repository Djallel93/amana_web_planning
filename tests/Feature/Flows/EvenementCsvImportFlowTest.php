<?php
// tests/Feature/Flows/EvenementCsvImportFlowTest.php
//
// Import d'événements : page, fichier modèle, contrôles du fichier envoyé et saisie manuelle en
// masse. Les tests de l'import CSV lui-même sont dans EvenementCsvImportAttenduTest.
//
// Séparateur « ; », sous-séparateur « | », une ligne = un événement, tout ou rien.
// Bus::fake() : la synchronisation Google Calendar n'est jamais déclenchée pour de vrai.

declare(strict_types=1);

namespace Tests\Feature\Flows;

use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\Evenement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\PrepareImportEvenements;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class EvenementCsvImportFlowTest extends TestCase
{
    use PrepareImportEvenements;
    use RefreshesBothDatabases;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerImport();
    }

    // ══ Page et modèle ════════════════════════════════════════════════════

    public function test_la_page_d_import_liste_les_taches_actives_et_les_couleurs(): void
    {
        $reponse = $this->get(route('evenements.import'))->assertOk();

        $this->assertSame(self::CODES_TACHES, $reponse->viewData('taches')->pluck('code')->all());
        $this->assertCount(11, $reponse->viewData('couleurs'));
        $this->assertSame(['id' => '5', 'nom' => 'Banane'], $reponse->viewData('couleurs')[4]);
    }

    public function test_le_modele_telechargeable_est_un_csv_avec_son_en_tete(): void
    {
        $reponse = $this->get(route('evenements.import.template'))->assertOk();

        $this->assertStringStartsWith('text/csv', $reponse->headers->get('Content-Type'));
        $this->assertStringContainsString('modele-import-evenements.csv', $reponse->headers->get('Content-Disposition'));
        $this->assertStringStartsWith(self::ENTETE, $reponse->getContent());
    }

    // ══ Cas réels de fichiers Excel ═══════════════════════════════════════

    /**
     * CARACTÉRISATION : « CSV UTF-8 » d'Excel commence par un BOM (EF BB BF). Il se colle au
     * premier nom de colonne (« \u{FEFF}nom »), qui n'est donc plus reconnu : un fichier
     * parfaitement valide est refusé avec « Colonnes obligatoires manquantes : nom ». Retirer le
     * BOM avant d'analyser l'en-tête corrigerait cela.
     */
    public function test_un_fichier_avec_bom_utf8_est_rejete_a_tort(): void
    {
        $resultat = $this->valider("\xEF\xBB\xBF" . self::ENTETE . "A;2026-12-01;2026-12-02;;;;\n");

        $this->assertSame([['ligne' => 1, 'erreurs' => ['Colonnes obligatoires manquantes : nom.']]], $resultat['errors']);
    }

    // ══ Fichier envoyé ════════════════════════════════════════════════════

    public function test_il_faut_envoyer_un_fichier_csv_de_moins_de_2_mo(): void
    {
        $this->post(route('evenements.import.store'), [])->assertSessionHasErrors(['csv' => 'Veuillez sélectionner un fichier CSV.']);
        $this->post(route('evenements.import.store'), ['csv' => UploadedFile::fake()->create('photo.png', 10, 'image/png')])
            ->assertSessionHasErrors(['csv' => 'Le fichier doit être au format CSV.']);
        $this->post(route('evenements.import.store'), ['csv' => UploadedFile::fake()->create('gros.csv', 2049, 'text/csv')])
            ->assertSessionHasErrors(['csv' => 'Le fichier ne doit pas dépasser 2 Mo.']);

        $this->assertSame(0, Evenement::count());
    }

    // ══ Saisie manuelle en masse ══════════════════════════════════════════

    public function test_la_saisie_manuelle_cree_les_evenements(): void
    {
        $this->post(route('evenements.import.manuel'), ['rows' => [$this->ligneManuelle(), $this->ligneManuelle(['nom' => 'Autre', 'taches' => [], 'calendar_ids' => []])]])
            ->assertRedirect(route('evenements.index'))
            ->assertSessionHas('success', '2 événement(s) importé(s) avec succès.');

        $manuel = Evenement::where('nom', 'Manuel')->firstOrFail();
        $this->assertSame(['entree'], $manuel->tachesBloquees->pluck('code')->all());
        $this->assertSame('4', (string) $manuel->couleur);
        $this->assertSame(['general@group.calendar.google.com'], $manuel->calendriers->pluck('google_calendar_id')->all());
        Bus::assertDispatchedTimes(SynchroniserGoogleCalendar::class, 1, 'seul l\'événement relié à un calendrier est synchronisé');
    }

    public function test_la_saisie_manuelle_valide_ses_lignes(): void
    {
        $this->post(route('evenements.import.manuel'), [])->assertSessionHasErrors(['rows' => 'Ajoutez au moins un événement.']);
        $this->post(route('evenements.import.manuel'), ['rows' => [$this->ligneManuelle(['nom' => ''])]])->assertSessionHasErrors(['rows.0.nom' => 'Le nom est obligatoire.']);
        $this->post(route('evenements.import.manuel'), ['rows' => [$this->ligneManuelle(['couleur' => '12'])]])->assertSessionHasErrors('rows.0.couleur');
        $this->post(route('evenements.import.manuel'), ['rows' => [$this->ligneManuelle(['taches' => [999999]])]])->assertSessionHasErrors('rows.0.taches.0');
        $this->post(route('evenements.import.manuel'), ['rows' => [$this->ligneManuelle(['date_debut' => 'demain'])]])->assertSessionHasErrors('rows.0.date_debut');

        $this->assertSame(0, Evenement::count());
    }

    public function test_la_saisie_manuelle_refuse_une_fin_anterieure_au_debut(): void
    {
        $this->post(route('evenements.import.manuel'), ['rows' => [$this->ligneManuelle(['date_debut' => '2026-12-10', 'date_fin' => '2026-12-01'])]])
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertSame(0, Evenement::count());
    }

    public function test_une_panne_pendant_la_saisie_manuelle_n_en_laisse_aucun(): void
    {
        $n = 0;
        Evenement::creating(function () use (&$n) {
            if (++$n === 2) {
                throw new \RuntimeException('panne simulée sur le 2e événement');
            }
        });

        try {
            $this->post(route('evenements.import.manuel'), ['rows' => [$this->ligneManuelle(['nom' => 'A']), $this->ligneManuelle(['nom' => 'B']), $this->ligneManuelle(['nom' => 'C'])]])
                ->assertRedirect(route('evenements.import'))
                ->assertSessionHas('error', "Échec de l'import : panne simulée sur le 2e événement");
        } finally {
            Evenement::flushEventListeners();
        }

        $this->assertSame(0, Evenement::count(), 'le 1er événement a été annulé avec les autres');
        Bus::assertNothingDispatched();
    }
}
