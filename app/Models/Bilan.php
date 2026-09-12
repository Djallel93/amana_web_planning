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
 * NULL vs 0 : depuis la migration 2026_07_14_000002, les 4 colonnes de
 * valeurs sont nullable. NULL signifie "pas de cours ce jour-là" (jour de
 * semaine, vacances, cours annulé…) — distinct de 0, qui est une vraie
 * valeur saisie (ex. 0 € collecté, 0 personne en ligne). Un groupe
 * (Amana food ou Présences) passe à NULL via le bouton "Réinitialiser"
 * (BilanController::resetAmanaFood / resetPresence), réservé aux rôles
 * gestionnaire et admin.
 *
 * @property int                    $id
 * @property \Carbon\Carbon         $date
 * @property float|null             $montant_carte
 * @property float|null             $montant_espece
 * @property int|null               $id_personne_maj_food
 * @property \Carbon\Carbon|null    $maj_food_at
 * @property int|null               $nb_presents
 * @property int|null               $nb_en_ligne
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
