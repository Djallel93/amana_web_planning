<?php
// app/Models/Setting.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Modèle pour ref_settings.
 *
 * Table partagée entre toutes les applications AMANA.
 * Toujours filtrer par id_application lors de la lecture.
 *
 * Utilisation :
 *   Setting::get('heure_cours', 'planning')         → '20:00'
 *   Setting::get('offset_entree_debut', 'planning') → -30 (int)
 *   Setting::set('heure_cours', 'planning', '20:30')
 *
 * Le cache statique évite les N+1 — il vit le temps d'une requête HTTP.
 */
class Setting extends Model
{
    protected $table = 'ref_settings';
    public $timestamps = false;

    protected $fillable = [
        'id_application',
        'cle',
        'valeur',
        'type',
        'libelle',
        'description',
    ];

    // ── Cache statique par requête ─────────────────────────────────────────

    /** @var array<string, mixed> Cache ['appCode:cle' => valeur castée] */
    private static array $cache = [];

    // ── Relations ─────────────────────────────────────────────────────────

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'id_application');
    }

    // ── Helpers statiques ─────────────────────────────────────────────────

    /**
     * Récupère la valeur d'un paramètre, castée selon son type.
     *
     * Jointure sur ref_applications pour filtrer par code (ex: 'planning').
     * Résultat mis en cache pour la durée de la requête.
     *
     * @param string $cle     Clé du paramètre (ex: 'heure_cours')
     * @param string $appCode Code de l'application (ex: 'planning')
     * @return mixed          Valeur castée (string, int, bool) ou null
     */
    public static function get(string $cle, string $appCode): mixed
    {
        $cacheKey = "{$appCode}:{$cle}";

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        // Jointure ref_settings ⟵ ref_applications filtrée par code
        $row = DB::table('ref_settings as s')
            ->join('ref_applications as a', 'a.id', '=', 's.id_application')
            ->where('a.code', $appCode)
            ->where('s.cle', $cle)
            ->select('s.valeur', 's.type')
            ->first();

        $valeur = $row ? self::cast($row->valeur, $row->type) : null;

        self::$cache[$cacheKey] = $valeur;

        return $valeur;
    }

    /**
     * Met à jour la valeur d'un paramètre en base.
     * Le cache n'est pas invalidé — un redirect repart sur une nouvelle
     * requête avec un cache vide, ce qui est suffisant.
     *
     * @param string $cle
     * @param string $appCode
     * @param string $valeur  Valeur brute (sera stockée telle quelle)
     */
    public static function set(string $cle, string $appCode, string $valeur): void
    {
        DB::table('ref_settings as s')
            ->join('ref_applications as a', 'a.id', '=', 's.id_application')
            ->where('a.code', $appCode)
            ->where('s.cle', $cle)
            ->update(['s.valeur' => $valeur]);
    }

    /**
     * Charge tous les paramètres d'une application en une seule requête.
     * Retourne une collection indexée par clé, avec valeurs castées.
     * Alimente aussi le cache statique.
     *
     * Utilisé par SettingsController::index() pour éviter N+1.
     *
     * @param string $appCode
     * @return \Illuminate\Support\Collection<string, array{valeur: mixed, type: string, libelle: string, description: string|null, id: int}>
     */
    public static function allForApp(string $appCode): \Illuminate\Support\Collection
    {
        $rows = DB::table('ref_settings as s')
            ->join('ref_applications as a', 'a.id', '=', 's.id_application')
            ->where('a.code', $appCode)
            ->select('s.id', 's.cle', 's.valeur', 's.type', 's.libelle', 's.description')
            ->orderBy('s.id')
            ->get();

        return $rows->mapWithKeys(function ($row) use ($appCode) {
            $casted = self::cast($row->valeur, $row->type);

            // Alimenter le cache au passage
            self::$cache["{$appCode}:{$row->cle}"] = $casted;

            return [
                $row->cle => [
                    'id' => $row->id,
                    'valeur' => $casted,
                    'valeur_raw' => $row->valeur,
                    'type' => $row->type,
                    'libelle' => $row->libelle,
                    'description' => $row->description,
                ]
            ];
        });
    }

    // ── Cast interne ──────────────────────────────────────────────────────

    /**
     * Caste une valeur brute (string) selon le type défini en base.
     */
    private static function cast(string $valeur, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $valeur,
            'boolean' => in_array(strtolower($valeur), ['1', 'true', 'yes', 'oui'], true),
            'time', 'string' => $valeur,
            default => $valeur,
        };
    }

    // ── Utilitaire de test ────────────────────────────────────────────────

    /**
     * Vide le cache statique — utile dans les tests unitaires.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}