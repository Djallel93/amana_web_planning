<?php
// app/Services/NavBadges.php

declare(strict_types=1);

namespace App\Services;

use Amana\Shared\Contracts\NavBadgeProvider;
use App\Models\Personne;

/**
 * Implémentation planning du contrat Amana\Shared\Contracts\NavBadgeProvider
 * — voir ce contrat pour le pourquoi (config('amana-shared.nav') est un
 * tableau statique, incompatible avec un compteur dynamique).
 *
 * Liée dans AppServiceProvider::register() :
 *   $this->app->bind(NavBadgeProvider::class, NavBadges::class);
 *
 * Aujourd'hui : uniquement le nombre de candidatures en attente sur l'item
 * 'admin.candidatures.index'. D'autres compteurs (ex. échanges en attente
 * sur 'admin.echanges.index') pourront être ajoutés ici plus tard sans
 * toucher au contrat ni à la sidebar partagée.
 */
class NavBadges implements NavBadgeProvider
{
    public function counts(): array
    {
        return [
            'admin.candidatures.index' => Personne::enAttente()->count(),
        ];
    }
}
