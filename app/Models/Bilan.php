<?php
// app/Models/Bilan.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pour plan_bilans_quotidiens.
 *
 * Un enregistrement unique et partagé par date — n'importe quel utilisateur
 * connecté peut le consulter ou le modifier (pas de notion de propriétaire).
 *
 * @property int                    $id
 * @property \Carbon\Carbon         $date
 * @property float                  $montant_carte
 * @property float                  $montant_espece
 * @property int|null               $id_personne_maj_food
 * @property \Carbon\Carbon|null    $maj_food_at
 * @property int                    $nb_presents
 * @property int                    $nb_en_ligne
 * @property int|null               $id_personne_maj_presence
 * @property \Carbon\Carbon|null    $maj_presence_at
 * @property \Carbon\Carbon         $created_at
 * @property \Carbon\Carbon         $updated_at
 */
class Bilan extends Model
{
    protected $table = 'plan_bilans_quotidiens';

    protected $fillable = [
        'date',
        'montant_carte',
        'montant_espece',
        'id_personne_maj_food',
        'maj_food_at',
        'nb_presents',
        'nb_en_ligne',
        'id_personne_maj_presence',
        'maj_presence_at',
    ];

    protected $casts = [
        'date'            => 'date',
        'montant_carte'   => 'decimal:2',
        'montant_espece'  => 'decimal:2',
        'nb_presents'     => 'integer',
        'nb_en_ligne'     => 'integer',
        'maj_food_at'     => 'datetime',
        'maj_presence_at' => 'datetime',
    ];

    /** Personne ayant modifié le groupe Amana food en dernier. */
    public function personneMajFood(): BelongsTo
    {
        return $this->belongsTo(Personne::class, 'id_personne_maj_food');
    }

    /** Personne ayant modifié le groupe Présences en dernier. */
    public function personneMajPresence(): BelongsTo
    {
        return $this->belongsTo(Personne::class, 'id_personne_maj_presence');
    }
}
