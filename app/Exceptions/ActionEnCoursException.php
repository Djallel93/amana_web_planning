<?php
// app/Exceptions/ActionEnCoursException.php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Levée par App\Services\VerrouAction quand l'action demandée est déjà en cours
 * d'exécution (double soumission, deux onglets, régénération concurrente).
 *
 * Ce n'est pas une erreur applicative : l'appelant la rattrape pour répondre par
 * un message clair plutôt que par une erreur 500.
 */
class ActionEnCoursException extends RuntimeException
{
    public const MESSAGE_PAR_DEFAUT = 'Cette action est déjà en cours — patientez quelques secondes, puis actualisez la page.';

    public function __construct(public readonly string $cle, ?string $message = null)
    {
        parent::__construct($message ?? self::MESSAGE_PAR_DEFAUT);
    }
}
