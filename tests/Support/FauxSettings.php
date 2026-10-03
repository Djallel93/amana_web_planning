<?php
// tests/Support/FauxSettings.php
//
// Setting::get() (amana_shared) lit ref_settings puis mémorise le résultat
// dans un cache statique privé. Pour tester sans base de données du code qui
// lit des paramètres, on pré-remplit ce cache — c'est exactement ce que
// Setting::get() renverrait après sa première lecture.
//
// À appeler avec ::reinitialiser() en tearDown : le cache est statique, donc
// partagé entre tests.

declare(strict_types=1);

namespace Tests\Support;

use Amana\Shared\Models\Setting;
use ReflectionProperty;

final class FauxSettings
{
    /**
     * @param array<string, mixed> $valeurs cle => valeur (déjà castée)
     *                                       ex. ['calendar_entree' => 'abc@group.calendar.google.com']
     */
    public static function definir(array $valeurs, string $appCode = 'planning'): void
    {
        $cache = new ReflectionProperty(Setting::class, 'cache');
        $existant = $cache->getValue();

        foreach ($valeurs as $cle => $valeur) {
            $existant["{$appCode}:{$cle}"] = $valeur;
        }

        $cache->setValue(null, $existant);
    }

    public static function reinitialiser(): void
    {
        Setting::clearCache();
    }
}
