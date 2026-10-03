<?php
// tests/Feature/Flows/EvenementCsvImportFlowTest.php
//
// Import d'événements : page, fichier modèle, contrôles du fichier envoyé, saisie manuelle en
// masse, et — en fin de fichier — la CARACTÉRISATION des deux défauts qui rendent l'import CSV
// inutilisable aujourd'hui. Les tests du comportement attendu de l'import CSV sont dans
// EvenementCsvImportAttenduTest (groupe « bug-connu », exclu du lancement par défaut).
//
// Séparateur « ; », sous-séparateur « | », une ligne = un événement, tout ou rien.
// Bus::fake() : la synchronisation Google Calendar n'est jamais déclenchée pour de vrai.

declare(strict_types=1);

namespace Tests\Feature\Flows;

use Amana\Shared\Models\AuditLog;
use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\Evenement;
use App\Services\SchedulerMain;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\PrepareImportEvenements;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class EvenementCsvImportFlowTest extends TestCase
{
    use RefreshesBothDatabases;
    use PrepareImportEvenements;

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

    // ══ CARACTÉRISATIONS — l'import CSV est aujourd'hui inutilisable ══════
    // Le comportement ATTENDU est décrit dans EvenementCsvImportAttenduTest (groupe « bug-connu »).

    /**
     * CARACTÉRISATION (défaut bloquant) : EvenementCsvImporter::validate() lit l'en-tête avec
     * fgetcsv() puis boucle avec `foreach ($handle …)`. Un foreach sur un SplFileObject commence
     * par rewind() : la ligne d'en-tête est donc relue COMME UNE LIGNE DE DONNÉES. « date_debut »
     * n'étant pas une date, TOUT fichier — même parfait — est rejeté avec une erreur « ligne 2 »,
     * et le contrôleur n'importe rien (tout ou rien). Aucun CSV ne peut être importé.
     * Correctif minimal : sauter la première itération (ou boucler avec valid()/current()/next()).
     */
    public function test_tout_fichier_csv_est_rejete_a_cause_de_sa_propre_ligne_d_en_tete(): void
    {
        $this->importer(self::ENTETE . "Ramadan;2026-12-01;2026-12-30;;;;\n")
            ->assertRedirect(route('evenements.import'))
            ->assertSessionHas('error', "1 ligne(s) invalide(s) — aucun événement n'a été importé. Corrigez le fichier et réessayez.")
            ->assertSessionHas('import_errors', fn(array $e) => $e[0]['ligne'] === 2
                && in_array('La date de début « date_debut » est invalide (format attendu : AAAA-MM-JJ).', $e[0]['erreurs'], true));

        $this->assertSame(0, Evenement::count());
    }

    /**
     * CARACTÉRISATION (défaut) : resoudreCouleur() est déclarée `?string` mais renvoie la clé du
     * tableau de la palette — un ENTIER en PHP (les clés '1'…'11' deviennent des int) — quand la
     * couleur est donnée par son nom (« Tomate »). Avec declare(strict_types=1) c'est un TypeError :
     * erreur 500. Or le fichier modèle téléchargeable utilise justement « Tomate ». Un numéro
     * (« 5 ») ne passe pas par ce chemin et fonctionne. Correctif : `return (string) $id;`.
     */
    public function test_une_couleur_donnee_par_son_nom_provoque_une_erreur_500(): void
    {
        $this->importer(self::ENTETE . "Ramadan;2026-12-01;2026-12-30;;Tomate;;\n")->assertStatus(500);

        $this->assertSame(0, Evenement::count());
    }

    public function test_le_modele_telechargeable_declenche_la_meme_erreur_500(): void
    {
        $this->importer($this->get(route('evenements.import.template'))->getContent())->assertStatus(500);
    }
}
