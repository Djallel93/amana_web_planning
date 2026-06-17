<?php
// app/Helpers/DateHelper.php

declare(strict_types=1);

namespace App\Helpers;

use Carbon\Carbon;

/**
 * Helpers utilitaires liés aux dates.
 */
class DateHelper
{
    /**
     * Retourne le premier vendredi à partir d'une date donnée (incluse).
     * Si la date est déjà un vendredi, elle est retournée telle quelle.
     */
    public static function premierVendredi(string $dateDebut): Carbon
    {
        $date = Carbon::parse($dateDebut)->startOfDay();
        while ($date->dayOfWeek !== Carbon::FRIDAY) {
            $date->addDay();
        }
        return $date;
    }
}