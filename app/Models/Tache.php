<?php
// app/Models/Tache.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle pour ref_taches.
 * Référentiel des tâches planifiables (entree, mektaba, salle, amana_food).
 *
 * @property string      $code
 * @property string      $libelle
 * @property string|null $description            Résumé affiché côté app (inscription, disponibilités).
 * @property string|null $description_calendrier Texte envoyé dans le body de l'événement Google Calendar (webhook).
 * @property bool        $actif
 */
class Tache extends Model
{
    protected $table = 'ref_taches';
    public $timestamps = false;

    // NOTE : 'description' manquait ici jusqu'ici — Tache::updateOrCreate()
    // dans le seeder passait bien 'description' dans le tableau d'attributs,
    // mais Eloquent l'ignorait silencieusement (protection mass-assignment),
    // ce qui explique pourquoi la plupart des tâches se retrouvaient avec
    // une description vide en base malgré le seeder.
    protected $fillable = ['code', 'libelle', 'actif', 'description', 'description_calendrier'];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function restrictions(): HasMany
    {
        return $this->hasMany(Restriction::class, 'id_tache');
    }

    public function creneauxTaches(): HasMany
    {
        return $this->hasMany(CreneauTache::class, 'id_tache');
    }

    /** Scope : uniquement les tâches actives */
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
