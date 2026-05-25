<?php
// app/Models/Vehicule.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Modèle pour ref_vehicules. */
class Vehicule extends Model
{
    protected $table = 'ref_vehicules';
    public $timestamps = false;
    protected $fillable = ['type', 'capacite_kg', 'nombre_parts_max'];

    public function personnes(): HasMany
    {
        return $this->hasMany(Personne::class, 'id_vehicule');
    }
}
