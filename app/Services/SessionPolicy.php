<?php
// app/Services/SessionPolicy.php
//
// Règles de durée de session de l'application, lues par ApplySessionPolicy :
//
//   - Session « ordinaire » (case « Rester connecté » NON cochée) : expire après
//     N minutes d'INACTIVITÉ. N = paramètre admin `session_lifetime` (ref_settings,
//     app « planning »), borné entre MIN_MINUTES et MAX_MINUTES, 120 par défaut.
//   - Session « Rester connecté jusqu'à minuit » (case cochée) : pas de délai
//     d'inactivité, mais coupure ABSOLUE au prochain minuit, heure de Paris.
//
// Ce service ne touche ni à la session ni à l'authentification : il ne fait que
// calculer des durées et des échéances (testable sans HTTP).

declare(strict_types=1);

namespace App\Services;

use Amana\Shared\Models\Setting;
use App\Helpers\DateHelper;
use Carbon\Carbon;

class SessionPolicy
{
    public const CLE_PARAMETRE = 'session_lifetime';

    public const DEFAUT_MINUTES = 120;

    public const MIN_MINUTES = 5;

    /** 24 h : aussi la durée de vie minimale du stockage de session (voir AppServiceProvider). */
    public const MAX_MINUTES = 1440;

    /**
     * Délai d'inactivité (minutes) après lequel une session ordinaire expire.
     * Valeur absente/illisible → défaut ; hors bornes → ramenée dans les bornes.
     */
    public function dureeInactiviteMinutes(): int
    {
        $valeur = Setting::get(self::CLE_PARAMETRE, 'planning');

        if (!is_int($valeur) && !(is_string($valeur) && ctype_digit($valeur))) {
            return self::DEFAUT_MINUTES;
        }

        return max(self::MIN_MINUTES, min(self::MAX_MINUTES, (int) $valeur));
    }

    /** Prochain minuit (00:00) à Paris, strictement dans le futur. */
    public function prochainMinuit(): Carbon
    {
        return Carbon::now(DateHelper::FUSEAU_METIER)->addDay()->startOfDay();
    }

    /** Minutes restantes jusqu'au prochain minuit (au moins 1) — durée du cookie « se souvenir de moi ». */
    public function minutesJusquAMinuit(): int
    {
        $secondes = $this->prochainMinuit()->getTimestamp() - Carbon::now()->getTimestamp();

        return max(1, (int) ceil($secondes / 60));
    }
}
