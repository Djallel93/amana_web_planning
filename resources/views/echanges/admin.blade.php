{{-- resources/views/echanges/admin.blade.php --}}
@extends('layouts.app')

@section('title', 'Gestion des échanges — AMANA')

@push('styles')
<style>
    .echange-row-attente { background: var(--amber-bg) !important; }
    .echange-row-attente:hover { background: #fef3c7 !important; }

    .slot-compact {
        display: inline-flex;
        flex-direction: column;
        gap: 1px;
    }
    .slot-compact-date  { font-weight: 600; color: var(--ink); font-size: 12.5px; }
    .slot-compact-tache { color: var(--ink-muted); font-size: 11.5px; }

    .statut-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        border: 1px solid;
        white-space: nowrap;
    }
    .statut-en_attente { background: var(--amber-bg);   color: #92400e; border-color: var(--amber-border); }
    .statut-accepte    { background: var(--emerald-bg); color: #065f46; border-color: var(--emerald-border); }
    .statut-refuse     { background: var(--rose-bg);    color: #9f1239; border-color: var(--rose-border); }
    .statut-expire     { background: var(--surface-3);  color: var(--ink-muted); border-color: var(--ink-faint); }
    .statut-annule     { background: var(--surface-3);  color: var(--ink-muted); border-color: var(--ink-faint); }
</style>
@endpush

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Gestion des échanges</div>
        <div class="page-subtitle">Approuvez ou refusez les demandes d'échange en attente</div>
    </div>
</div>

@if($nbEnAttente > 0)
    <div class="flash flash-warning" style="margin-bottom:20px;">
        <span>⏳</span>
        <span>{{ $nbEnAttente }} demande{{ $nbEnAttente > 1 ? 's' : '' }} en attente de décision.</span>
    </div>
@endif

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <div class="card-title-icon" style="background:var(--amber-bg);">🔄</div>
            Toutes les demandes d'échange
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date demande</th>
                    <th>Demandeur</th>
                    <th>Son créneau</th>
                    <th style="text-align:center;">⇄</th>
                    <th>Cible</th>
                    <th>Créneau cible</th>
                    <th>Statut</th>
                    <th>Expire le</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($echanges as $echange)
                    <tr class="{{ $echange->statut === 'en_attente' ? 'echange-row-attente' : '' }}">
                        <td style="font-size:12px;color:var(--ink-muted);white-space:nowrap;">
                            {{ $echange->created_at->locale('fr')->isoFormat('D MMM HH:mm') }}
                        </td>
                        <td>
                            <div style="font-weight:600;color:var(--ink);font-size:13px;">
                                {{ $echange->demandeur?->prenom }} {{ $echange->demandeur?->nom }}
                            </div>
                        </td>
                        <td>
                            <div class="slot-compact">
                                <span class="slot-compact-date">
                                    {{ $echange->creneauDemandeur?->date?->locale('fr')->isoFormat('ddd D MMM YYYY') ?? '—' }}
                                </span>
                                <span class="slot-compact-tache">
                                    {{ $echange->tacheDemandeur?->libelle ?? '—' }}
                                </span>
                            </div>
                        </td>
                        <td style="text-align:center;color:var(--app-accent);font-size:18px;">⇄</td>
                        <td>
                            <div style="font-weight:600;color:var(--ink);font-size:13px;">
                                {{ $echange->cible?->prenom }} {{ $echange->cible?->nom }}
                            </div>
                        </td>
                        <td>
                            <div class="slot-compact">
                                <span class="slot-compact-date">
                                    {{ $echange->creneauCible?->date?->locale('fr')->isoFormat('ddd D MMM YYYY') ?? '—' }}
                                </span>
                                <span class="slot-compact-tache">
                                    {{ $echange->tacheCible?->libelle ?? '—' }}
                                </span>
                            </div>
                        </td>
                        <td>
                            @php
                                $labels = [
                                    'en_attente' => ['⏳', 'En attente'],
                                    'accepte'    => ['✅', 'Accepté'],
                                    'refuse'     => ['✕',  'Refusé'],
                                    'expire'     => ['⌛', 'Expiré'],
                                    'annule'     => ['✗',  'Annulé'],
                                ];
                                [$icon, $label] = $labels[$echange->statut] ?? ['?', $echange->statut];
                            @endphp
                            <span class="statut-badge statut-{{ $echange->statut }}">
                                {{ $icon }} {{ $label }}
                            </span>
                            @if($echange->approuve_par && $echange->approbateur)
                                <div style="font-size:11px;color:var(--ink-muted);margin-top:3px;">
                                    par {{ $echange->approbateur->prenom }}
                                </div>
                            @endif
                        </td>
                        <td style="font-size:12px;color:var(--ink-muted);white-space:nowrap;">
                            @if($echange->statut === 'en_attente')
                                {{ $echange->expires_at->locale('fr')->isoFormat('D MMM YYYY') }}
                                @if($echange->expires_at->isPast())
                                    <span style="color:var(--rose);font-weight:600;"> (passée)</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($echange->statut === 'en_attente')
                                <div class="actions">
                                    <form action="{{ route('admin.echanges.approuver', $echange->id) }}"
                                        method="POST" class="form-delete"
                                        onsubmit="return confirm('Approuver et exécuter cet échange immédiatement ?')">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm">
                                            ✅ Approuver
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.echanges.refuser', $echange->id) }}"
                                        method="POST" class="form-delete"
                                        onsubmit="return confirm('Refuser cet échange ? Le demandeur sera notifié.')">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            ✕ Refuser
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span style="color:var(--ink-faint);font-size:12px;">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="empty-state" style="padding:36px;">
                                <div class="empty-icon">🔄</div>
                                <div class="empty-title">Aucune demande d'échange</div>
                                <div class="empty-desc">Les demandes d'échange des membres apparaîtront ici.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($echanges->hasPages())
        <div style="padding:16px 22px;border-top:1px solid var(--surface-3);">
            {{ $echanges->links() }}
        </div>
    @endif
</div>
@endsection
