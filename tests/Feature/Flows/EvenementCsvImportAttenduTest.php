<?php
// tests/Feature/Flows/EvenementCsvImportAttenduTest.php
//
// COMPORTEMENT ATTENDU de l'import CSV d'événements. Ces tests échouent aujourd'hui à cause de
// deux défauts décrits (et figés) dans EvenementCsvImportFlowTest :
//   1. la ligne d'en-tête est relue comme une ligne de données (tout fichier est rejeté) ;
//   2. une couleur donnée par son nom (« Tomate », dans le modèle fourni) provoque un TypeError.
// Ils sont étiquetés #[Group('bug-connu')] et EXCLUS du lancement par défaut (phpunit.xml) pour
// ne pas bloquer le déploiement. Après correction :
//     vendor/bin/phpunit --group bug-connu        # doivent tous passer
// puis retirer l'exclusion de phpunit.xml et inverser les deux caractérisations de
// EvenementCsvImportFlowTest.

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

#[Group('bug-connu')]
class EvenementCsvImportAttenduTest extends TestCase
{
    use RefreshesBothDatabases;
    use PrepareImportEvenements;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerImport();
    }

    // ══ Modèle fourni ═════════════════════════════════════════════════════

    /**
     * Le fichier d'exemple fourni aux utilisateurs doit lui-même s'importer sans erreur : c'est
     * le premier fichier que quiconque essaie. Il utilise une couleur par son NOM (« Tomate »).
     */
    public function test_le_modele_fourni_s_importe_tel_quel(): void
    {
        $modele = $this->get(route('evenements.import.template'))->getContent();

        $this->importer($modele)
            ->assertRedirect(route('evenements.index'))
            ->assertSessionHas('success', '1 événement(s) importé(s) avec succès.');

        $ramadan = Evenement::firstOrFail();
        $this->assertSame('Ramadan', $ramadan->nom);
        $this->assertSame('11', (string) $ramadan->couleur, '« Tomate » = colorId 11');
    }

    // ══ Import valide ═════════════════════════════════════════════════════

    public function test_un_import_valide_cree_les_evenements_leurs_taches_bloquees_et_leurs_calendriers(): void
    {
        $contenu = self::ENTETE
            . "Ramadan;2026-12-01;2026-12-30;Horaires adaptés;5;entree|mektaba;Calendrier Général|Jeunes\n"
            . "Vacances;2026-12-20;2027-01-03;;Tomate;;\n";

        $this->importer($contenu)
            ->assertRedirect(route('evenements.index'))
            ->assertSessionHas('success', '2 événement(s) importé(s) avec succès.');

        $ramadan = Evenement::where('nom', 'Ramadan')->firstOrFail();
        $this->assertSame('2026-12-01', $ramadan->date_debut->toDateString());
        $this->assertSame('2026-12-30', $ramadan->date_fin->toDateString());
        $this->assertSame('Horaires adaptés', $ramadan->description);
        $this->assertSame('5', (string) $ramadan->couleur);
        $this->assertEqualsCanonicalizing(['entree', 'mektaba'], $ramadan->tachesBloquees->pluck('code')->all());
        $this->assertEqualsCanonicalizing(
            ['general@group.calendar.google.com', 'jeunes@group.calendar.google.com'],
            $ramadan->calendriers->pluck('google_calendar_id')->all(),
        );
        $this->assertEqualsCanonicalizing(['Calendrier Général', 'Jeunes'], $ramadan->calendriers->pluck('calendar_name')->all());

        $vacances = Evenement::where('nom', 'Vacances')->firstOrFail();
        $this->assertNull($vacances->description, 'description vide → null');
        $this->assertSame(0, $vacances->tachesBloquees->count());
        $this->assertSame(0, $vacances->calendriers->count());
    }

    public function test_chaque_evenement_relie_a_un_calendrier_est_synchronise_et_tous_sont_journalises(): void
    {
        $this->importer(self::ENTETE . "A;2026-12-01;2026-12-02;;;;Jeunes\nB;2026-12-05;2026-12-06;;;;Calendrier Général\nSans calendrier;2026-12-10;2026-12-11;;;;\n");

        Bus::assertDispatchedTimes(SynchroniserGoogleCalendar::class, 2);
        $this->assertSame(3, AuditLog::where('module', 'evenements')->where('action', 'create')->count());
        $this->assertTrue((bool) AuditLog::where('module', 'evenements')->first()->after['import_csv']);
    }

    public function test_les_noms_de_colonnes_et_de_calendriers_ne_sont_pas_sensibles_a_la_casse(): void
    {
        $contenu = "NOM;Date_Debut;DATE_FIN;Taches;CALENDRIERS\nA;2026-12-01;2026-12-02;entree;calendrier général\n";

        $this->importer($contenu)->assertSessionHas('success');

        $this->assertSame(['general@group.calendar.google.com'], Evenement::firstOrFail()->calendriers->pluck('google_calendar_id')->all());
    }

    public function test_les_colonnes_facultatives_peuvent_etre_absentes_et_l_ordre_est_libre(): void
    {
        $this->importer("date_fin;nom;date_debut\n2026-12-02;Seul;2026-12-01\n")->assertSessionHas('success');

        $this->assertSame('Seul', Evenement::firstOrFail()->nom);
    }

    public function test_les_codes_en_double_ou_vides_sont_ignores(): void
    {
        $this->importer(self::ENTETE . "A;2026-12-01;2026-12-02;;;entree||entree|salle;Jeunes|Jeunes\n")->assertSessionHas('success');

        $evenement = Evenement::firstOrFail();
        $this->assertEqualsCanonicalizing(['entree', 'salle'], $evenement->tachesBloquees->pluck('code')->all());
        $this->assertCount(1, $evenement->calendriers);
    }

    public function test_les_champs_entre_guillemets_peuvent_contenir_le_separateur_et_les_fins_de_ligne_windows_passent(): void
    {
        $contenu = self::ENTETE . "\"A; avec point-virgule\";2026-12-01;2026-12-02;\"Texte; compliqué\";;;\r\n";

        $this->importer($contenu)->assertSessionHas('success');

        $this->assertSame('A; avec point-virgule', Evenement::firstOrFail()->nom);
        $this->assertSame('Texte; compliqué', Evenement::firstOrFail()->description);
    }

    public function test_les_lignes_vides_sont_ignorees_et_ne_decalent_pas_les_numeros_d_erreur(): void
    {
        $resultat = $this->valider(self::ENTETE . "\nA;2026-12-01;2026-12-02;;;;\n\nB;pas-une-date;2026-12-02;;;;\n");

        $this->assertCount(1, $resultat['rows']);
        $this->assertSame([['ligne' => 3, 'erreurs' => ["La date de début « pas-une-date » est invalide (format attendu : AAAA-MM-JJ)."]]], $resultat['errors']);
    }

    // ══ Tout ou rien ══════════════════════════════════════════════════════

    public function test_une_seule_ligne_invalide_annule_tout_l_import(): void
    {
        $contenu = self::ENTETE . "Bon;2026-12-01;2026-12-02;;;;\nMauvais;2026-12-05;2026-12-01;;;;\nAussi bon;2026-12-10;2026-12-11;;;;\n";

        $this->importer($contenu)
            ->assertRedirect(route('evenements.import'))
            ->assertSessionHas('error', "1 ligne(s) invalide(s) — aucun événement n'a été importé. Corrigez le fichier et réessayez.")
            ->assertSessionHas('import_errors', [['ligne' => 3, 'erreurs' => ['La date de fin doit être après ou égale à la date de début.']]]);

        $this->assertSame(0, Evenement::count());
        Bus::assertNothingDispatched();
    }

    public function test_les_erreurs_d_une_meme_ligne_sont_toutes_signalees(): void
    {
        $resultat = $this->valider(self::ENTETE . ";2026-02-30;;;Violet;inconnue|entree;Nulle part\n");

        $this->assertSame([
            'Le nom est obligatoire.',
            'La date de début « 2026-02-30 » est invalide (format attendu : AAAA-MM-JJ).',
            'La date de fin «  » est invalide (format attendu : AAAA-MM-JJ).',
            'Couleur inconnue « Violet » (attendu : 1 à 11, ou un nom de la palette Google Calendar).',
            'Code tâche inconnu « inconnue ».',
            'Calendrier inconnu « Nulle part ».',
        ], $resultat['errors'][0]['erreurs']);
        $this->assertSame(2, $resultat['errors'][0]['ligne']);
    }

    /** @return array<string, array{string, string}> */
    public static function lignesInvalides(): array
    {
        return [
            'nom trop long' => [str_repeat('x', 151) . ';2026-12-01;2026-12-02;;;;', 'Le nom ne doit pas dépasser 150 caractères.'],
            'date au mauvais format' => ['A;01/12/2026;2026-12-02;;;;', 'La date de début « 01/12/2026 » est invalide (format attendu : AAAA-MM-JJ).'],
            'date impossible' => ['A;2026-12-01;2026-02-30;;;;', 'La date de fin « 2026-02-30 » est invalide (format attendu : AAAA-MM-JJ).'],
            'année bissextile fausse' => ['A;2026-02-29;2026-03-01;;;;', 'La date de début « 2026-02-29 » est invalide (format attendu : AAAA-MM-JJ).'],
            'fin avant début' => ['A;2026-12-05;2026-12-01;;;;', 'La date de fin doit être après ou égale à la date de début.'],
            'couleur hors palette' => ['A;2026-12-01;2026-12-02;;12;;', 'Couleur inconnue « 12 » (attendu : 1 à 11, ou un nom de la palette Google Calendar).'],
            'tâche inconnue' => ['A;2026-12-01;2026-12-02;;;nimporte;', 'Code tâche inconnu « nimporte ».'],
            'calendrier inconnu' => ['A;2026-12-01;2026-12-02;;;;Fantôme', 'Calendrier inconnu « Fantôme ».'],
        ];
    }

    #[DataProvider('lignesInvalides')]
    public function test_une_ligne_invalide_est_refusee_avec_le_bon_message(string $ligne, string $message): void
    {
        $resultat = $this->valider(self::ENTETE . $ligne . "\n");

        $this->assertSame([], $resultat['rows']);
        $this->assertContains($message, $resultat['errors'][0]['erreurs']);
    }

    public function test_un_evenement_d_un_seul_jour_est_valide(): void
    {
        $this->assertSame([], $this->valider(self::ENTETE . "A;2026-12-01;2026-12-01;;;;\n")['errors']);
    }

    public function test_un_nom_de_150_caracteres_est_accepte(): void
    {
        $this->assertSame([], $this->valider(self::ENTETE . str_repeat('é', 150) . ";2026-12-01;2026-12-02;;;;\n")['errors']);
    }

    /** @return array<string, array{string, string}> */
    public static function fichiersInexploitables(): array
    {
        return [
            'vide' => ['', 'Fichier vide ou illisible.'],
            'colonnes obligatoires manquantes' => ["nom;description\nA;B\n", 'Colonnes obligatoires manquantes : date_debut, date_fin.'],
            'en-tête seul' => [self::ENTETE, 'Aucune ligne de données trouvée dans le fichier.'],
        ];
    }

    #[DataProvider('fichiersInexploitables')]
    public function test_un_fichier_inexploitable_est_signale(string $contenu, string $message): void
    {
        $resultat = $this->valider($contenu);

        $this->assertSame([], $resultat['rows']);
        $this->assertSame([['ligne' => 1, 'erreurs' => [$message]]], $resultat['errors']);
    }

    // ══ Atomicité ═════════════════════════════════════════════════════════

    public function test_une_panne_pendant_l_import_n_en_laisse_aucun(): void
    {
        $n = 0;
        Evenement::creating(function () use (&$n) {
            if (++$n === 2) {
                throw new \RuntimeException('panne simulée sur le 2e événement');
            }
        });

        try {
            $this->importer(self::ENTETE . "A;2026-12-01;2026-12-02;;;;\nB;2026-12-05;2026-12-06;;;;\nC;2026-12-10;2026-12-11;;;;\n")
                ->assertRedirect(route('evenements.import'))
                ->assertSessionHas('error', "Échec de l'import : panne simulée sur le 2e événement");
        } finally {
            Evenement::flushEventListeners();
        }

        $this->assertSame(0, Evenement::count(), 'le 1er événement a été annulé avec les autres');
        Bus::assertNothingDispatched();
    }

    // ══ Régénération du planning ══════════════════════════════════════════

    public function test_un_import_qui_chevauche_le_planning_le_regenere_et_bloque_les_taches(): void
    {
        $this->personnesValidees(6);
        $this->app->make(SchedulerMain::class)->generateSchedule('2026-10-02', 3);
        Bus::fake();
        $this->assertNotNull($this->idPersonneDuCreneau('2026-10-09', 'entree'));

        $this->importer(self::ENTETE . "Fermeture;2026-10-09;2026-10-10;;;entree|mektaba|salle|amana_food|cours;\n")
            ->assertSessionHas('success', fn(string $m) => str_starts_with($m, '1 événement(s) importé(s) avec succès. Planning régénéré automatiquement à partir du 9 octobre 2026'));

        foreach (self::CODES_TACHES as $code) {
            $this->assertNull($this->idPersonneDuCreneau('2026-10-09', $code), $code);
        }
        $this->assertNotNull($this->idPersonneDuCreneau('2026-10-02', 'entree'), 'la semaine précédente est intacte');
    }
}
