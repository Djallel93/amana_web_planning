<?php
// app/Models/Restriction.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pour plan_restrictions.
 * Une ligne par combinaison (personne × tâche × jour).
 * autorise = true  → la personne PEUT faire cette tâche ce jour-là
 * autorise = false → INTERDIT
 */
class Restriction extends Model
{
    protected $table = 'plan_restrictions';
    public $timestamps = false;

    /**
     * Sans cette déclaration explicite, Eloquent ferait hériter à ce modèle
     * la connexion de Personne ('commun') dès qu'il est chargé via la
     * relation Personne::restrictions() — Illuminate\Database\Eloquent\
     * Concerns\HasRelationships::newRelatedInstance() copie la connexion du
     * modèle PARENT sur le modèle lié si ce dernier n'en déclare pas une à
     * lui (getConnectionName() === null). plan_restrictions vit dans
     * amana_planning (connexion par défaut), pas dans amana_commun — d'où
     * ce garde-fou explicite. Même piège pour tout modèle applicatif
     * relié en hasMany/hasOne depuis Personne (voir Absence, CreneauTache).
     */
    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    protected $fillable = ['id_personne', 'id_tache', 'jour', 'autorise'];

    protected $casts = [
        'autorise' => 'boolean',
    ];

    // Valeurs autorisées pour le champ 'jour'
    const JOURS = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];

    public function personne(): BelongsTo
    {
        return $this->belongsTo(Personne::class, 'id_personne');
    }

    public function tache(): BelongsTo
    {
        return $this->belongsTo(Tache::class, 'id_tache');
    }
}
