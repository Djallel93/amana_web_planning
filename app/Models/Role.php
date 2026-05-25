<?php
// app/Models/Role.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Modèle pour ref_roles. */
class Role extends Model
{
    protected $table = 'ref_roles';
    public $timestamps = false;
    protected $fillable = ['code', 'libelle'];

    public function personnes(): BelongsToMany
    {
        return $this->belongsToMany(
            Personne::class,
            'ref_personnes_roles',
            'id_role',
            'id_personne'
        )->withPivot('date_attribution');
    }
}
