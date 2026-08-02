<?php
// app/Models/Echange.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pour plan_echanges.
 *
 * Représente une demande d'échange de créneau entre deux membres.
 *
 * @property int         $id
 * @property int         $id_personne_demandeur
 * @property int         $id_creneau_demandeur
 * @property int         $id_tache_demandeur
 * @property int         $id_personne_cible
 * @property int         $id_creneau_cible
 * @property int         $id_tache_cible
 * @property string      $statut  en_attente|accepte|refuse|expire|annule
 * @property string      $token_accept
 * @property string      $token_refuse
 * @property \Carbon\Carbon $expires_at
 * @property int|null    $approuve_par
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Echange extends Model
{
    protected $table = 'plan_echanges';

    protected $fillable = [
        'id_personne_demandeur',
        'id_creneau_demandeur',
        'id_tache_demandeur',
        'id_personne_cible',
        'id_creneau_cible',
        'id_tache_cible',
        'statut',
        'token_accept',
        'token_refuse',
        'expires_at',
        'approuve_par',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    // ── Statuts ────────────────────────────────────────────────────────────

    const STATUT_EN_ATTENTE = 'en_attente';
    const STATUT_ACCEPTE    = 'accepte';
    const STATUT_REFUSE     = 'refuse';
    const STATUT_EXPIRE     = 'expire';
    const STATUT_ANNULE     = 'annule';

    // ── Relations ──────────────────────────────────────────────────────────

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(Personne::class, 'id_personne_demandeur');
    }

    public function cible(): BelongsTo
    {
        return $this->belongsTo(Personne::class, 'id_personne_cible');
    }

    public function creneauDemandeur(): BelongsTo
    {
        return $this->belongsTo(Creneau::class, 'id_creneau_demandeur');
    }

    public function creneauCible(): BelongsTo
    {
        return $this->belongsTo(Creneau::class, 'id_creneau_cible');
    }

    public function tacheDemandeur(): BelongsTo
    {
        return $this->belongsTo(Tache::class, 'id_tache_demandeur');
    }

    public function tacheCible(): BelongsTo
    {
        return $this->belongsTo(Tache::class, 'id_tache_cible');
    }

    public function approbateur(): BelongsTo
    {
        return $this->belongsTo(Personne::class, 'approuve_par');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isEnAttente(): bool
    {
        return $this->statut === self::STATUT_EN_ATTENTE;
    }

    public function isAccepte(): bool
    {
        return $this->statut === self::STATUT_ACCEPTE;
    }

    public function isTermine(): bool
    {
        return in_array($this->statut, [
            self::STATUT_ACCEPTE,
            self::STATUT_REFUSE,
            self::STATUT_EXPIRE,
            self::STATUT_ANNULE,
        ]);
    }

    public function isExpire(): bool
    {
        return $this->expires_at->isPast() && $this->statut === self::STATUT_EN_ATTENTE;
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeEnAttente($query)
    {
        return $query->where('statut', self::STATUT_EN_ATTENTE);
    }

    public function scopeImpliquant($query, int $personneId)
    {
        return $query->where(function ($q) use ($personneId) {
            $q->where('id_personne_demandeur', $personneId)
              ->orWhere('id_personne_cible', $personneId);
        });
    }
}
