<?php
// app/Models/Application.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle pour ref_applications.
 *
 * Référentiel de toutes les applications AMANA partageant
 * cette base de données.
 *
 * @property int    $id
 * @property string $code    ex: planning, livraisons, tirelire
 * @property string $libelle ex: AMANA Planning
 * @property bool   $actif
 */
class Application extends Model
{
    protected $table = 'ref_applications';
    public $timestamps = false;

    protected $fillable = ['code', 'libelle', 'actif'];

    protected $casts = [
        'actif' => 'boolean',
    ];

    // ──────────────────────────────────────────────────────────────────────
    // RELATIONS
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Rôles appartenant à cette application.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'id_application');
    }

    // ──────────────────────────────────────────────────────────────────────
    // SCOPES
    // ──────────────────────────────────────────────────────────────────────

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
