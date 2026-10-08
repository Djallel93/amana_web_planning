<?php
// app/Services/VerrouAction.php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ActionEnCoursException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Sérialise une action sensible derrière un verrou atomique (Cache::lock).
 *
 * Pourquoi : un double clic ou deux requêtes simultanées peuvent sinon créer deux
 * lignes et régénérer deux fois le planning EN PARALLÈLE (la régénération supprime
 * puis recrée des créneaux : deux exécutions concurrentes se marchent dessus).
 * Le verrou est la garantie côté serveur ; le verrouillage du bouton côté
 * navigateur (data-submit-lock, @amana/shared-ui) n'est qu'un confort.
 *
 * Deux modes, selon qui appelle :
 *   - $attenteSecondes = 0 (défaut) : si le verrou est pris, on refuse tout de suite
 *     (ActionEnCoursException). C'est le mode des actions déclenchées par un
 *     formulaire : la seconde requête n'a rien à faire.
 *   - $attenteSecondes > 0 : on patiente jusqu'à ce délai, puis on refuse. C'est le
 *     mode d'une régénération AUTOMATIQUE (suite à une absence, un événement) : la
 *     donnée de l'utilisateur est déjà enregistrée, il faut donc laisser passer la
 *     génération en cours plutôt que de renoncer à la sienne.
 *
 * Le verrou expire de lui-même après config('planning.verrou.ttl') secondes : un
 * process tué en cours de route ne bloque jamais l'action indéfiniment.
 *
 * Fonctionne avec les stores database/redis/file/array — le store `database` de
 * production dispose de la table cache_locks (migration 0001_01_01_000001).
 */
class VerrouAction
{
    /** Clé commune à TOUTES les générations/régénérations du planning. */
    public const GENERATION_PLANNING = 'planning-generation';

    /**
     * @template T
     *
     * @param  callable(): T  $action
     * @return T
     *
     * @throws ActionEnCoursException si le verrou est déjà pris (et le reste après l'attente éventuelle)
     */
    public function executer(string $cle, callable $action, int $attenteSecondes = 0, ?string $messageSiOccupe = null): mixed
    {
        $verrou = Cache::lock('verrou:' . $cle, (int) config('planning.verrou.ttl', 120));

        if ($attenteSecondes > 0) {
            try {
                return $verrou->block($attenteSecondes, $action);
            } catch (LockTimeoutException) {
                throw new ActionEnCoursException($cle, $messageSiOccupe);
            }
        }

        if (!$verrou->get()) {
            throw new ActionEnCoursException($cle, $messageSiOccupe);
        }

        try {
            return $action();
        } finally {
            $verrou->release();
        }
    }
}
