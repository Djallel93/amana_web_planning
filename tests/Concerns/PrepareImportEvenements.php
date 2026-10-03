<?php
// tests/Concerns/PrepareImportEvenements.php
//
// Décor commun aux tests d'import d'événements (CSV et saisie manuelle) : horloge, rôles, tâches,
// deux calendriers, un gestionnaire connecté, et les trois aides pour fabriquer un fichier CSV.

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\CalendrierGoogle;
use App\Models\Tache;
use App\Services\EvenementCsvImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;

trait PrepareImportEvenements
{
    use ConnecteParRole;
    use CreeDonneesPlanning;

    private const ENTETE = "nom;date_debut;date_fin;description;couleur;taches;calendriers\n";

    /** @var array<string, Tache> */
    private array $taches;

    protected function preparerImport(): void
    {
        Bus::fake();
        $this->travelTo('2026-09-30 09:00:00');
        $this->creerRolesPlanning();
        $this->taches = $this->tachesDeRotation();
        CalendrierGoogle::factory()->create(['nom' => 'Calendrier Général', 'calendar_id' => 'general@group.calendar.google.com']);
        CalendrierGoogle::factory()->create(['nom' => 'Jeunes', 'calendar_id' => 'jeunes@group.calendar.google.com']);
        $this->connecterEn('gestionnaire');
    }

    private function csv(string $contenu, string $nom = 'evenements.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nom, $contenu);
    }

    private function importer(string $contenu)
    {
        return $this->post(route('evenements.import.store'), ['csv' => $this->csv($contenu)]);
    }

    private function valider(string $contenu): array
    {
        return (new EvenementCsvImporter())->validate($this->csv($contenu));
    }

    private function ligneManuelle(array $surcharge = []): array
    {
        return array_replace(['nom' => 'Manuel', 'date_debut' => '2026-12-01', 'date_fin' => '2026-12-02', 'couleur' => '4', 'taches' => [$this->taches['entree']->id], 'calendar_ids' => ['general@group.calendar.google.com']], $surcharge);
    }
}
