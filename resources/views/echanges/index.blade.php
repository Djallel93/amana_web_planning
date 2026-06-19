{{-- resources/views/echanges/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Mes échanges — AMANA')

@push('styles')
<style>
    .echange-card {
        background: var(--surface);
        border: 1px solid var(--surface-border);
        border-radius: var(--radius-lg);
        padding: 18px 20px;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        position: relative;
    }

    .echange-card:hover { box-shadow: var(--shadow); }

    .echange-card.statut-en_attente { border-left: 3px solid var(--amber); }
    .echange-card.statut-accepte     { border-left: 3px solid var(--emerald); }
    .echange-card.statut-refuse      { border-left: 3px solid var(--rose); opacity: 0.78; }
    .echange-card.statut-expire      { border-left: 3px solid var(--ink-faint); opacity: 0.65; }
    .echange-card.statut-annule      { border-left: 3px solid var(--ink-faint); opacity: 0.65; }

    .echange-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
        flex-wrap: wrap;
    }

    .echange-role {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        color: var(--ink-muted);
        margin-bottom: 4px;
    }

    .echange-parties {
        font-family: var(--font-heading);
        font-size: 14px;
        font-weight: 600;
        color: var(--ink);
    }

    .swap-inline {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 12px;
        padding: 12px 14px;
        background: var(--surface-2);
        border-radius: var(--radius);
        border: 1px solid var(--surface-border);
    }

    .slot-pill {
        display: inline-flex;
        flex-direction: column;
        padding: 8px 13px;
        border-radius: var(--radius-sm);
        font-size: 12.5px;
        border: 1.5px solid var(--surface-border);
        background: var(--surface);
        min-width: 130px;
    }

    .slot-pill-date { font-weight: 700; color: var(--ink); }
    .slot-pill-tache { color: var(--ink-muted); font-size: 11.5px; margin-top: 2px; }

    .swap-arrow {
        font-size: 18px;
        color: var(--app-accent);
        flex-shrink: 0;
    }

    .echange-actions {
        margin-top: 14px;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .statut-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 11px;
        border-radius: 20px;
        font-size: 11.5px;
        font-weight: 700;
        border: 1px solid;
        white-space: nowrap;
    }

    .statut-en_attente { background: var(--amber-bg);   color: #92400e; border-color: var(--amber-border); }
    .statut-accepte    { background: var(--emerald-bg); color: #065f46; border-color: var(--emerald-border); }
    .statut-refuse     { background: var(--rose-bg);    color: #9f1239; border-color: var(--rose-border); }
    .statut-expire     { background: var(--surface-3);  color: var(--ink-muted); border-color: var(--ink-faint); }
    .statut-annule     { background: var(--surface-3);  color: var(--ink-muted); border-color: var(--ink-faint); }

    .expires-note {
        font-size: 11.5px;
        color: var(--ink-muted);
        margin-top: 6px;
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Mes échanges</div>
        <div class="page-subtitle">Demandes d'échange de créneaux envoyées et reçues</div>
    </div>
    <a href="{{ route('mon-planning') }}" class="btn btn-secondary">← Mon planning</a>
</div>

@if($echanges->isEmpty())
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">🔄</div>
            <div class="empty-title">Aucun échange</div>
            <div class="empty-desc">
                Vous n'avez pas encore initié ou reçu de demande d'échange.<br>
                Pour demander un échange, cliquez sur « 🔄 Échanger » dans Mon planning.
            </div>
            <a href="{{ route('mon-planning') }}" class="btn btn-primary" style="margin-top:8px;">
                📅 Aller à mon planning
            </a>
        </div>
    </div>
@else
    <div style="display:flex;flex-direction:column;gap:12px;">
        @foreach($echanges as $echange)
            @php
                $isDemandeur = $echange->id_personne_demandeur === $user->id;
                $autrePersonne = $isDemandeur ? $echange->cible : $echange->demandeur;
                $monSlotCreneau = $isDemandeur ? $echange->creneauDemandeur : $echange->creneauCible;
                $monSlotTache   = $isDemandeur ? $echange->tacheDemandeur   : $echange->tacheCible;
                $sonSlotCreneau = $isDemandeur ? $echange->creneauCible     : $echange->creneauDemandeur;
                $sonSlotTache   = $isDemandeur ? $echange->tacheCible       : $echange->tacheDemandeur;

                $statutLabels = [
                    'en_attente' => ['⏳', 'En attente'],
                    'accepte'    => ['✅', 'Accepté'],
                    'refuse'     => ['✕',  'Refusé'],
                    'expire'     => ['⌛', 'Expiré'],
                    'annule'     => ['✗',  'Annulé'],
                ];
                [$statutIcon, $statutLabel] = $statutLabels[$echange->statut] ?? ['?', $echange->statut];
            @endphp

            <div class="echange-card statut-{{ $echange->statut }}">
                <div class="echange-header">
                    <div>
                        <div class="echange-role">
                            {{ $isDemandeur ? 'Vous avez demandé' : 'Reçu de' }}
                        </div>
                        <div class="echange-parties">
                            @if($isDemandeur)
                                Échange avec {{ $autrePersonne->prenom }} {{ $autrePersonne->nom }}
                            @else
                                {{ $autrePersonne->prenom }} {{ $autrePersonne->nom }} vous propose un échange
                            @endif
                        </div>
                        <div style="font-size:12px;color:var(--ink-muted);margin-top:2px;">
                            {{ $echange->created_at->locale('fr')->isoFormat('D MMM YYYY à HH:mm') }}
                        </div>
                    </div>
                    <span class="statut-badge statut-{{ $echange->statut }}">
                        {{ $statutIcon }} {{ $statutLabel }}
                    </span>
                </div>

                {{-- Swap slots display --}}
                <div class="swap-inline">
                    <div class="slot-pill">
                        <span class="slot-pill-date">
                            {{ $monSlotCreneau->date->locale('fr')->isoFormat('ddd D MMM YYYY') }}
                        </span>
                        <span class="slot-pill-tache">
                            {{ $monSlotTache->libelle }} · {{ $monSlotCreneau->jour }}
                        </span>
                    </div>
                    <span class="swap-arrow">⇄</span>
                    <div class="slot-pill">
                        <span class="slot-pill-date">
                            {{ $sonSlotCreneau->date->locale('fr')->isoFormat('ddd D MMM YYYY') }}
                        </span>
                        <span class="slot-pill-tache">
                            {{ $sonSlotTache->libelle }} · {{ $sonSlotCreneau->jour }}
                        </span>
                    </div>
                    @if($echange->approuve_par && $echange->approbateur)
                        <span style="font-size:11.5px;color:var(--app-accent);margin-left:auto;">
                            ✓ approuvé par {{ $echange->approbateur->prenom }}
                        </span>
                    @endif
                </div>

                @if($echange->statut === 'en_attente')
                    <div class="expires-note">
                        ⏰ Expire le {{ $echange->expires_at->locale('fr')->isoFormat('D MMMM YYYY') }}
                    </div>
                @endif

                {{-- Actions --}}
                @if($echange->statut === 'en_attente' && $isDemandeur)
                    <div class="echange-actions">
                        <form action="{{ route('echanges.destroy', $echange->id) }}" method="POST" class="form-delete"
                            onsubmit="return confirm('Annuler cette demande d\'échange ?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm">✗ Annuler ma demande</button>
                        </form>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
@endsection
