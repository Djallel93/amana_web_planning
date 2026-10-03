<?php
// tests/Support/PersonneDeTest.php
//
// Faux Personne pour les tests de logique pure (RotationEngine, DataLoader::
// isPersonAbsent…) : aucune base, aucun conteneur Laravel. RotationEngine ne
// type-hint pas ses personnes, il utilise seulement nom, prenom,
// date_debut_planning et peutFaireTache().

declare(strict_types=1);

namespace Tests\Support;

use Carbon\Carbon;

class PersonneDeTest
{
    public ?Carbon $date_debut_planning;

    /**
     * @param array<int, list<string>> $interdits id de tâche => jours interdits
     */
    public function __construct(
        public string $nom,
        public string $prenom = 'Test',
        private array $interdits = [],
        ?string $dateDebutPlanning = null,
    ) {
        $this->date_debut_planning = $dateDebutPlanning ? Carbon::parse($dateDebutPlanning) : null;
    }

    public function peutFaireTache(int $idTache, string $jour): bool
    {
        return ! in_array($jour, $this->interdits[$idTache] ?? [], true);
    }

    /** Clé « nom prenom » utilisée par RotationEngine / DataLoader. */
    public function cle(): string
    {
        return $this->nom . ' ' . $this->prenom;
    }
}
