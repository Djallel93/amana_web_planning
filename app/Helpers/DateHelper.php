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
     * Fuseau horaire métier de l'association (les permanences ont lieu en
     * France). L'application tourne en UTC (APP_TIMEZONE) : tout ce qui doit
     * suivre l'heure « du mur » des bénévoles — jour courant, heure d'envoi
     * des rappels planifiés — doit passer par ce fuseau explicite plutôt que
     * par now() nu.
     */
    public const FUSEAU_METIER = 'Europe/Paris';

    /**
     * Date du jour dans le fuseau métier (minuit, Europe/Paris).
     */
    public static function aujourdhui(): Carbon
    {
        return Carbon::today(self::FUSEAU_METIER);
    }

    /**
     * Vrai si la date (YYYY-MM-DD) est STRICTEMENT antérieure à aujourd'hui
     * dans le fuseau métier. Aujourd'hui lui-même n'est pas « passé ».
     */
    public static function estPasse(string $date): bool
    {
        return Carbon::parse($date, self::FUSEAU_METIER)->toDateString()
            < self::aujourdhui()->toDateString();
    }

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