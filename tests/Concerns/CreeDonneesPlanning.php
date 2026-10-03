<?php
// tests/Concerns/CreeDonneesPlanning.php
//
// Fabriques de scénarios partagées par les tests de services avec base de
// données. Nécessite RefreshesBothDatabases (factories → deux connexions).

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Personne;
use App\Models\Tache;
use Database\Factories\TacheFactory;

trait CreeDonneesPlanning
{
    public const CODES_TACHES = ['amana_food', 'entree', 'mektaba', 'salle', 'cours'];

    /** Les cinq tâches de rotation, dans l'ordre d'id (= ordre de génération). @return array<string, Tache> */
    protected function tachesDeRotation(): array
    {
        $taches = [];
        foreach (self::CODES_TACHES as $code) {
            $taches[$code] = TacheFactory::pourCode($code);
        }

        return $taches;
    }

    /** @return list<Personne> personnes validées, nommées A1, A2… (ordre alphabétique = ordre de création) */
    protected function personnesValidees(int $nombre): array
    {
        $personnes = [];
        for ($i = 1; $i <= $nombre; $i++) {
            $personnes[] = Personne::factory()->create(['nom' => sprintf('Nom%02d', $i), 'prenom' => 'Test']);
        }

        return $personnes;
    }

    protected function cle(Personne $personne): string
    {
        return $personne->nom . ' ' . $personne->prenom;
    }

    protected function creneauLe(string $date): Creneau
    {
        return Creneau::where('date', $date)->first() ?? Creneau::factory()->pourDate($date)->create();
    }

    /** Assigne `$personne` à la tâche `$code` le `$date` (crée le créneau si besoin). */
    protected function assigner(?Personne $personne, string $date, string $code): CreneauTache
    {
        $creneau = $this->creneauLe($date);
        $tache = TacheFactory::pourCode($code);
        $factory = CreneauTache::factory()->pour($creneau, $tache);

        return ($personne ? $factory->assigneA($personne) : $factory)->create();
    }

    protected function idPersonneDuCreneau(string $date, string $code): ?int
    {
        $valeur = CreneauTache::query()
            ->where('id_planning', $this->creneauLe($date)->id)
            ->where('id_tache', TacheFactory::pourCode($code)->id)
            ->value('id_personne');

        return $valeur === null ? null : (int) $valeur;
    }
}
