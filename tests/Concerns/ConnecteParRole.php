<?php
// tests/Concerns/ConnecteParRole.php
//
// Six « personas » pour les tests d'autorisation. La hiérarchie réelle (amana_shared,
// Personne::isMembre() etc.) : admin ⊃ gestionnaire ⊃ membre ; « benevole » est un rôle
// distinct, en dessous de membre. Un compte validé SANS aucun rôle planning existe aussi.

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Personne;

trait ConnecteParRole
{
    /** Rang d'accès croissant. « public » : aucune connexion requise. */
    public const RANGS = ['public' => 0, 'auth' => 1, 'membre' => 3, 'gestionnaire' => 4, 'admin' => 5];

    /** persona → rang atteint. « benevole » (2) est au-dessus de « sans_role » (1) mais sous « membre ». */
    public const PERSONAS = ['guest' => 0, 'sans_role' => 1, 'benevole' => 2, 'membre' => 3, 'gestionnaire' => 4, 'admin' => 5];

    protected function creerPersona(string $persona, array $attributs = []): ?Personne
    {
        return match ($persona) {
            'guest' => null,
            'sans_role' => Personne::factory()->create($attributs),
            default => Personne::factory()->{$persona}()->create($attributs),
        };
    }

    /** Crée le persona et le connecte (rien pour « guest »). */
    protected function connecterEn(string $persona, array $attributs = []): ?Personne
    {
        $personne = $this->creerPersona($persona, $attributs);
        if ($personne) {
            $this->actingAs($personne);
        }

        return $personne;
    }

    /**
     * Le persona peut-il passer le contrôle d'accès de niveau `$niveau` ?
     * public : tous ; auth : tout compte connecté ; membre/gestionnaire/admin : rang suffisant.
     */
    protected function personaAutorise(string $persona, string $niveau): bool
    {
        return match ($niveau) {
            'public' => true,
            'auth' => $persona !== 'guest',
            default => self::PERSONAS[$persona] >= self::RANGS[$niveau],
        };
    }
}
