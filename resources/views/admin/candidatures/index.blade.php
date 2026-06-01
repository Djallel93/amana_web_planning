{{-- resources/views/admin/candidatures/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Candidatures — AMANA')

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Candidatures en attente</div>
        <div class="page-subtitle">
            Validez ou refusez les demandes d'inscription
        </div>
    </div>
</div>

{{-- Compteur --}}
<div class="stat-grid" style="margin-bottom:24px;">
    <div class="stat-card color-amber">
        <div class="stat-value" style="color:var(--amber);">{{ $candidatures->count() }}</div>
        <div class="stat-label">En attente</div>
    </div>
</div>

@if($candidatures->isEmpty())
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">✅</div>
            <div class="empty-title">Aucune candidature en attente</div>
            <div class="empty-desc">
                Toutes les candidatures ont été traitées.<br>
                Les nouvelles inscriptions apparaîtront ici automatiquement.
            </div>
        </div>
    </div>
@else
    <div style="display:flex; flex-direction:column; gap:16px;">
        @foreach($candidatures as $candidat)
        <div class="card">
            <div style="padding:20px 24px;">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;">

                    {{-- Identité --}}
                    <div style="display:flex; align-items:center; gap:14px; flex:1; min-width:200px;">
                        <div style="
                            width:46px; height:46px; flex-shrink:0;
                            background:linear-gradient(135deg,var(--primary),var(--violet));
                            border-radius:50%;
                            display:flex; align-items:center; justify-content:center;
                            color:white; font-size:17px; font-weight:700;
                        ">
                            {{ strtoupper(substr($candidat->prenom, 0, 1)) }}
                        </div>
                        <div>
                            <div style="font-size:16px; font-weight:700; color:var(--ink);">
                                {{ $candidat->prenom }} {{ strtoupper($candidat->nom) }}
                            </div>
                            <div style="font-size:13px; color:var(--ink-muted); margin-top:2px;">
                                {{ $candidat->email }}
                            </div>
                            @if($candidat->telephone)
                                <div style="font-size:12.5px; color:var(--ink-muted);">
                                    📞 {{ $candidat->telephone }}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Infos bénévole --}}
                    <div style="display:flex; flex-direction:column; gap:6px; min-width:180px;">
                        <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:var(--ink-muted);">
                            Informations
                        </div>
                        <div style="font-size:13px; color:var(--ink-light);">
                            📅 Inscrit le :
                            {{ $candidat->date_inscription_benevole
                                ? $candidat->date_inscription_benevole->locale('fr')->isoFormat('D MMM YYYY')
                                : '—' }}
                        </div>
                        <div style="font-size:13px; color:var(--ink-light);">
                            🚗 Véhicule :
                            {{ $candidat->vehicule ? $candidat->vehicule->type . ' (' . $candidat->vehicule->capacite_kg . ' kg)' : 'Aucun' }}
                        </div>
                        <div style="font-size:13px; color:var(--ink-light);">
                            🕐 Candidature :
                            {{ $candidat->derniere_maj?->locale('fr')->isoFormat('D MMM YYYY à HH:mm') ?? '—' }}
                        </div>
                    </div>

                    {{-- Disponibilités --}}
                    <div style="min-width:220px;">
                        <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:var(--ink-muted); margin-bottom:8px;">
                            Disponibilités déclarées
                        </div>
                        @php
                            $restricMap = $candidat->restrictions->groupBy('jour');
                            $jours = ['Vendredi', 'Samedi'];
                        @endphp
                        <div style="display:flex; flex-direction:column; gap:4px;">
                            @foreach($jours as $jour)
                                <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                    <span style="font-size:12px; font-weight:600; color:var(--ink-muted); width:58px;">{{ $jour }}</span>
                                    @foreach($candidat->restrictions->where('jour', $jour) as $r)
                                        <span style="
                                            display:inline-flex; align-items:center; gap:3px;
                                            padding:2px 7px; border-radius:20px;
                                            font-size:11px; font-weight:600;
                                            background:{{ $r->autorise ? 'var(--emerald-bg)' : 'var(--rose-bg)' }};
                                            color:{{ $r->autorise ? '#065f46' : '#9f1239' }};
                                            border:1px solid {{ $r->autorise ? '#a7f3d0' : '#fecdd3' }};
                                        ">
                                            {{ $r->autorise ? '✓' : '✗' }} {{ $r->tache->libelle ?? '—' }}
                                        </span>
                                    @endforeach
                                    @if($candidat->restrictions->where('jour', $jour)->isEmpty())
                                        <span style="font-size:12px; color:var(--ink-faint); font-style:italic;">Non renseigné</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div style="display:flex; flex-direction:column; gap:8px; flex-shrink:0;">
                        {{-- Valider --}}
                        <form action="{{ route('admin.candidatures.valider', $candidat->id) }}"
                              method="POST"
                              onsubmit="return confirm('Valider la candidature de {{ $candidat->prenom }} {{ $candidat->nom }} ?\nUn email d\'invitation lui sera envoyé.')">
                            @csrf
                            <button type="submit" class="btn btn-success" style="width:100%; justify-content:center;">
                                ✅ Valider
                            </button>
                        </form>

                        {{-- Refuser --}}
                        <form action="{{ route('admin.candidatures.refuser', $candidat->id) }}"
                              method="POST"
                              onsubmit="return confirm('Refuser la candidature de {{ $candidat->prenom }} {{ $candidat->nom }} ?')">
                            @csrf
                            <button type="submit" class="btn btn-danger" style="width:100%; justify-content:center;">
                                ✕ Refuser
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
        @endforeach
    </div>
@endif

@endsection
