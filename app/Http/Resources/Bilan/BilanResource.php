<?php
// app/Http/Resources/Bilan/BilanResource.php

declare(strict_types=1);

namespace App\Http\Resources\Bilan;

use App\Models\Bilan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

/**
 * Représentation JSON du bilan quotidien pour une date donnée, consommée
 * par BilanView.vue — voir BilanController::show() (et les réponses
 * store*/reset* qui embarquent la même forme sous la clé 'bilan').
 *
 * Ne wrappe pas directement un modèle Bilan : une date sans bilan
 * enregistré est un cas normal (voir docblock de BilanController, section
 * "NULL vs 0") et doit quand même produire une réponse — donc la resource
 * prend un tableau ['date' => string, 'bilan' => ?Bilan] plutôt qu'un
 * Bilan seul. Bilan étant chargé avec ses relations personneMajFood /
 * personneMajPresence par le contrôleur avant d'arriver ici (voir
 * with()/load() dans BilanController), aucune requête supplémentaire
 * n'est déclenchée par toArray().
 *
 * `peutReinitialiser` dépend de l'utilisateur courant, pas du bilan
 * lui-même — c'est une info de représentation ("ce que CET utilisateur a
 * le droit de voir/faire sur cette réponse"), pas une règle métier propre
 * au modèle.
 */
class BilanResource extends JsonResource
{
    /**
     * @param array{date: string, bilan: ?Bilan} $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $date = $this->resource['date'];
        $bilan = $this->resource['bilan'];

        /** @var \App\Models\Personne|null $user */
        $user = Auth::user();

        return [
            'date' => $date,
            'montantCarte' => $bilan?->montant_carte !== null ? (float) $bilan->montant_carte : null,
            'montantEspece' => $bilan?->montant_espece !== null ? (float) $bilan->montant_espece : null,
            'nbPresents' => $bilan?->nb_presents,
            'nbEnLigne' => $bilan?->nb_en_ligne,
            'existe' => $bilan !== null,
            'derniereMajFood' => $bilan?->maj_food_at?->locale('fr')->isoFormat('D MMM YYYY [à] HH:mm'),
            'derniereMajFoodPar' => $bilan?->personneMajFood
                ? $bilan->personneMajFood->prenom . ' ' . $bilan->personneMajFood->nom
                : null,
            'derniereMajPresence' => $bilan?->maj_presence_at?->locale('fr')->isoFormat('D MMM YYYY [à] HH:mm'),
            'derniereMajPresencePar' => $bilan?->personneMajPresence
                ? $bilan->personneMajPresence->prenom . ' ' . $bilan->personneMajPresence->nom
                : null,
            'peutReinitialiser' => (bool) ($user?->isAdmin() || $user?->isGestionnaire()),
        ];
    }
}
