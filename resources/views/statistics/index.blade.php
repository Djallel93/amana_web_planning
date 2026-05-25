{{-- resources/views/statistics/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Statistiques — AMANA')

@push('styles')
<style>
    .metric-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .metric-card {
        background: white;
        border-radius: 10px;
        padding: 20px 16px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        border-top: 3px solid var(--primary);
    }
    .metric-card .value {
        font-size: 32px;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 6px;
    }
    .metric-card .label {
        font-size: 12px;
        color: #718096;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .metric-card .sub {
        font-size: 11px;
        color: #a0aec0;
        margin-top: 4px;
    }
    .fairness-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        padding: 28px;
        margin-bottom: 24px;
    }
    .fairness-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        margin-top: 16px;
    }
    .fairness-metric {
        background: rgba(255,255,255,0.18);
        border-radius: 8px;
        padding: 14px;
    }
    .fairness-metric .f-value { font-size: 24px; font-weight: 800; }
    .fairness-metric .f-label { font-size: 12px; opacity: 0.85; margin-bottom: 4px; }
    .fairness-metric .f-sub   { font-size: 11px; opacity: 0.7; margin-top: 4px; }

    .stats-table th { font-size: 11px; }
    .stats-table td { font-size: 13px; }
    .high-value { color: #c53030; font-weight: 700; }
    .good-value { color: #276749; }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">📊 Statistiques du planning</div>
        @if($stats['dateDebut'] && $stats['dateFin'])
            <div class="page-subtitle">
                Du {{ \Carbon\Carbon::parse($stats['dateDebut'])->locale('fr')->isoFormat('D MMM YYYY') }}
                au {{ \Carbon\Carbon::parse($stats['dateFin'])->locale('fr')->isoFormat('D MMM YYYY') }}
                — {{ $stats['totalDays'] }} créneaux, {{ floor($stats['totalDays'] / 2) }} semaines
            </div>
        @endif
    </div>
    <a href="{{ route('planning.index') }}" class="btn btn-secondary">← Planning</a>
</div>

@if(empty($stats['personnes']))
    <div class="card" style="text-align:center; padding:60px; color:#718096;">
        <div style="font-size:40px; margin-bottom:16px;">📭</div>
        Aucune donnée de planning disponible. Générez d'abord un planning.
    </div>
@else

    {{-- Métriques clés --}}
    <div class="metric-grid">
        <div class="metric-card">
            <div class="value" style="color:#667eea;">{{ $stats['totalTasks'] }}</div>
            <div class="label">Total assignations</div>
        </div>
        <div class="metric-card">
            <div class="value" style="color:#4299e1;">{{ $stats['nbPersonnes'] }}</div>
            <div class="label">Personnes actives</div>
        </div>
        <div class="metric-card">
            <div class="value" style="color:#48bb78;">{{ $stats['moyenneTaches'] }}</div>
            <div class="label">Moyenne / personne</div>
        </div>
        <div class="metric-card">
            <div class="value" style="color:#ed8936;">{{ $stats['maxConsecutif'] }}</div>
            <div class="label">Max jours consécutifs</div>
            <div class="sub">jours</div>
        </div>
        <div class="metric-card">
            <div class="value" style="color:#d97706;">{{ $stats['totalAbsenceDays'] }}</div>
            <div class="label">Jours d'absence</div>
            <div class="sub">{{ $stats['nbPersonnesAbsentes'] }} personne(s)</div>
        </div>
        <div class="metric-card">
            <div class="value" style="color:#667eea;">{{ $stats['tauxUtilisation'] }}%</div>
            <div class="label">Taux d'utilisation</div>
            <div class="sub">postes occupés</div>
        </div>
    </div>

    {{-- Score d'équité --}}
    <div class="fairness-card">
        <div style="font-size:20px; font-weight:700; margin-bottom:4px;">✨ Évaluation de l'équité</div>
        <div style="opacity:0.9; font-size:14px;">
            Score global : <strong style="font-size:28px;">{{ $stats['fairnessScore'] }}/100</strong>
            @if($stats['fairnessScore'] >= 90) 🏆 Excellent
            @elseif($stats['fairnessScore'] >= 70) 👍 Bon
            @else ⚠️ À améliorer
            @endif
        </div>

        <div class="fairness-grid">
            <div class="fairness-metric">
                <div class="f-label">Écart-type distribution</div>
                <div class="f-value">{{ $stats['ecartType'] }}</div>
                <div class="f-sub">Plus bas = meilleure équité</div>
            </div>
            <div class="fairness-metric">
                <div class="f-label">Coefficient de variation</div>
                <div class="f-value">{{ $stats['coefficientVariation'] }}%</div>
                <div class="f-sub">Écart relatif à la moyenne</div>
            </div>
            <div class="fairness-metric">
                <div class="f-label">Équilibre Ven./Sam.</div>
                <div class="f-value">{{ $stats['desequilibreMoyen'] }}</div>
                <div class="f-sub">Déséquilibre moyen</div>
            </div>
            <div class="fairness-metric">
                <div class="f-label">Distribution Amana Food</div>
                <div class="f-value">{{ $stats['minAmanaFood'] }}–{{ $stats['maxAmanaFood'] }}</div>
                <div class="f-sub">Moy. : {{ $stats['avgAmanaFood'] }}</div>
            </div>
            <div class="fairness-metric">
                <div class="f-label">Plage de distribution</div>
                <div class="f-value">{{ $stats['minTaches'] }}–{{ $stats['maxTaches'] }}</div>
                <div class="f-sub">Écart : {{ $stats['maxTaches'] - $stats['minTaches'] }}</div>
            </div>
            <div class="fairness-metric">
                <div class="f-label">Jours consécutifs</div>
                <div class="f-value">{{ $stats['persAvecHautConsec'] }}</div>
                <div class="f-sub">personne(s) > 2 jours</div>
            </div>
        </div>
    </div>

    {{-- Tableau détaillé --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">📋 Statistiques détaillées par personne</span>
        </div>
        <div class="table-wrap">
            <table class="stats-table">
                <thead>
                    <tr>
                        <th>Personne</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:right;">Vendredis</th>
                        <th style="text-align:right;">Samedis</th>
                        <th style="text-align:right; color:#3182ce;">Entrée</th>
                        <th style="text-align:right; color:#276749;">Mektaba</th>
                        <th style="text-align:right; color:#c05621;">Salle</th>
                        <th style="text-align:right; color:#c53030;">Amana Food</th>
                        <th style="text-align:right;">Consécutifs</th>
                        <th style="text-align:right;">🏖️ Absences</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stats['personnes'] as $nom)
                        @php
                            $total   = $stats['taskCounts'][$nom] ?? 0;
                            $dc      = $stats['dayCounts'][$nom]    ?? ['vendredis' => 0, 'samedis' => 0];
                            $tp      = $stats['tasksByPerson'][$nom] ?? ['entree'=>0,'mektaba'=>0,'salle'=>0,'amana_food'=>0];
                            $consec  = $stats['consecutiveDays'][$nom] ?? 0;
                            $absences= $stats['absenceDays'][$nom]  ?? 0;
                        @endphp
                        <tr>
                            <td><strong>{{ $nom }}</strong></td>
                            <td style="text-align:right; font-weight:700;">{{ $total }}</td>
                            <td style="text-align:right;">{{ $dc['vendredis'] }}</td>
                            <td style="text-align:right;">{{ $dc['samedis'] }}</td>
                            <td style="text-align:right;">{{ $tp['entree'] ?? 0 }}</td>
                            <td style="text-align:right;">{{ $tp['mektaba'] ?? 0 }}</td>
                            <td style="text-align:right;">{{ $tp['salle'] ?? 0 }}</td>
                            <td style="text-align:right;">{{ $tp['amana_food'] ?? 0 }}</td>
                            <td style="text-align:right;"
                                class="{{ $consec > 2 ? 'high-value' : '' }}">
                                {{ $consec }}
                            </td>
                            <td style="text-align:right;"
                                class="{{ $absences > 0 ? '' : '' }}"
                                style="color:{{ $absences > 0 ? '#ed8936' : '#a0aec0' }}">
                                {{ $absences ?: '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

@endif
@endsection
