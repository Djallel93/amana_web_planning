{{-- resources/views/statistics/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Statistiques — AMANA')

@push('styles')
    <style>
        .fairness-band {
            background: var(--app-sidebar-bg);
            border-radius: var(--radius-xl);
            padding: 26px 30px;
            margin-bottom: 22px;
            position: relative;
            overflow: hidden;
        }

        .fairness-band::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(3, 105, 161, 0.35) 0%, transparent 65%);
            pointer-events: none;
        }

        .fairness-band::after {
            content: '';
            position: absolute;
            bottom: -40px;
            left: 40px;
            width: 160px;
            height: 160px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.18) 0%, transparent 65%);
            pointer-events: none;
        }

        .fairness-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 22px;
            position: relative;
            z-index: 1;
        }

        .fairness-title {
            font-family: var(--font-heading);
            font-size: 20px;
            font-weight: 600;
            color: white;
            margin-bottom: 4px;
        }

        .fairness-sub {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.5);
        }

        .fairness-score-wrap {
            text-align: right;
        }

        .fairness-score {
            font-family: var(--font-heading);
            font-size: 48px;
            font-weight: 700;
            color: white;
            line-height: 1;
            letter-spacing: -2px;
        }

        .fairness-score-label {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 4px;
        }

        .fairness-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            position: relative;
            z-index: 1;
        }

        .f-metric {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius);
            padding: 12px 14px;
        }

        .f-metric-label {
            font-size: 10.5px;
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin-bottom: 6px;
        }

        .f-metric-value {
            font-family: var(--font-heading);
            font-size: 20px;
            font-weight: 700;
            color: white;
            line-height: 1;
        }

        .f-metric-sub {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.3);
            margin-top: 3px;
        }

        .score-bar-wrap {
            margin-top: 18px;
            position: relative;
            z-index: 1;
        }

        .score-bar-bg {
            height: 5px;
            border-radius: 3px;
            background: rgba(255, 255, 255, 0.1);
            overflow: hidden;
            margin-bottom: 5px;
        }

        .score-bar-fill {
            height: 100%;
            border-radius: 3px;
            background: var(--app-accent-light);
            transition: width 1s ease;
        }

        .score-bar-labels {
            display: flex;
            justify-content: space-between;
            font-size: 10.5px;
            color: rgba(255, 255, 255, 0.25);
        }

        .stats-table .col-num {
            text-align: right;
        }

        .high-val {
            color: var(--rose);
            font-weight: 700;
        }
    </style>
@endpush

@section('content')
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Statistiques</div>
            @if($stats['dateDebut'] && $stats['dateFin'])
                <div class="page-subtitle">
                    Du {{ \Carbon\Carbon::parse($stats['dateDebut'])->locale('fr')->isoFormat('D MMM YYYY') }}
                    au {{ \Carbon\Carbon::parse($stats['dateFin'])->locale('fr')->isoFormat('D MMM YYYY') }}
                    — {{ $stats['totalDays'] }} créneaux
                </div>
            @endif
        </div>
        <a href="{{ route('planning.index') }}" class="btn btn-secondary">← Planning</a>
    </div>

    @if(empty($stats['personnes']))
        <div class="card">
            <div class="empty-state">
                <div class="empty-icon">📊</div>
                <div class="empty-title">Aucune donnée</div>
                <div class="empty-desc">Générez d'abord un planning pour voir les statistiques.</div>
                @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                    <a href="{{ route('planning.generate.form') }}" class="btn btn-primary">✨ Générer un planning</a>
                @endif
            </div>
        </div>
    @else

        {{-- Key metrics --}}
        <div class="stat-grid">
            <div class="stat-card color-primary">
                <div class="stat-value" style="color:var(--app-accent);">{{ $stats['totalTasks'] }}</div>
                <div class="stat-label">Total assignations</div>
            </div>
            <div class="stat-card color-sky">
                <div class="stat-value" style="color:var(--sky);">{{ $stats['nbPersonnes'] }}</div>
                <div class="stat-label">Personnes actives</div>
            </div>
            <div class="stat-card color-emerald">
                <div class="stat-value" style="color:var(--emerald);">{{ $stats['moyenneTaches'] }}</div>
                <div class="stat-label">Moyenne / personne</div>
            </div>
            <div class="stat-card color-amber">
                <div class="stat-value" style="color:var(--amber);">{{ $stats['maxConsecutif'] }}</div>
                <div class="stat-label">Max jours consécutifs</div>
            </div>
            <div class="stat-card color-violet">
                <div class="stat-value" style="color:var(--violet);">{{ $stats['tauxUtilisation'] }}%</div>
                <div class="stat-label">Taux d'utilisation</div>
            </div>
            <div class="stat-card color-rose">
                <div class="stat-value" style="color:var(--rose);">{{ $stats['totalAbsenceDays'] }}</div>
                <div class="stat-label">Jours d'absence</div>
                <div class="stat-sub">{{ $stats['nbPersonnesAbsentes'] }} personne(s)</div>
            </div>
        </div>

        {{-- Fairness band --}}
        <div class="fairness-band">
            <div class="fairness-top">
                <div>
                    <div class="fairness-title">
                        Score d'équité
                        @if($stats['fairnessScore'] >= 90) 🏆
                        @elseif($stats['fairnessScore'] >= 70) 👍
                        @else ⚠️
                        @endif
                    </div>
                    <div class="fairness-sub">
                        @if($stats['fairnessScore'] >= 90) Excellent — distribution très équilibrée
                        @elseif($stats['fairnessScore'] >= 70) Bon — quelques déséquilibres mineurs
                        @else À améliorer — distribution déséquilibrée
                        @endif
                    </div>
                </div>
                <div class="fairness-score-wrap">
                    <div class="fairness-score">{{ $stats['fairnessScore'] }}</div>
                    <div class="fairness-score-label">/ 100</div>
                </div>
            </div>

            <div class="fairness-metrics">
                <div class="f-metric">
                    <div class="f-metric-label">Écart-type</div>
                    <div class="f-metric-value">{{ $stats['ecartType'] }}</div>
                    <div class="f-metric-sub">Plus bas = meilleur</div>
                </div>
                <div class="f-metric">
                    <div class="f-metric-label">Coeff. variation</div>
                    <div class="f-metric-value">{{ $stats['coefficientVariation'] }}%</div>
                    <div class="f-metric-sub">Écart relatif</div>
                </div>
                <div class="f-metric">
                    <div class="f-metric-label">Déséq. Ven./Sam.</div>
                    <div class="f-metric-value">{{ $stats['desequilibreMoyen'] }}</div>
                    <div class="f-metric-sub">Moy. par personne</div>
                </div>
                <div class="f-metric">
                    <div class="f-metric-label">Amana Food</div>
                    <div class="f-metric-value">{{ $stats['minAmanaFood'] }}–{{ $stats['maxAmanaFood'] }}</div>
                    <div class="f-metric-sub">Moy. {{ $stats['avgAmanaFood'] }}</div>
                </div>
                <div class="f-metric">
                    <div class="f-metric-label">Plage distrib.</div>
                    <div class="f-metric-value">{{ $stats['minTaches'] }}–{{ $stats['maxTaches'] }}</div>
                    <div class="f-metric-sub">Écart {{ $stats['maxTaches'] - $stats['minTaches'] }}</div>
                </div>
                <div class="f-metric">
                    <div class="f-metric-label">Jours consécutifs</div>
                    <div class="f-metric-value">{{ $stats['persAvecHautConsec'] }}</div>
                    <div class="f-metric-sub">Pers. &gt; 2 jours</div>
                </div>
            </div>

            <div class="score-bar-wrap">
                <div class="score-bar-bg">
                    <div class="score-bar-fill" style="width:{{ $stats['fairnessScore'] }}%"></div>
                </div>
                <div class="score-bar-labels">
                    <span>0</span><span>25</span><span>50</span><span>75</span><span>100</span>
                </div>
            </div>
        </div>

        {{-- Detail table --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon" style="background:var(--sky-bg);">📋</div>
                    Détail par personne
                </div>
            </div>
            <div class="table-wrap">
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Personne</th>
                            <th class="col-num">Total</th>
                            <th class="col-num">Vendredis</th>
                            <th class="col-num">Samedis</th>
                            <th class="col-num" style="color:#2563eb;">Entrée</th>
                            <th class="col-num" style="color:#059669;">Mektaba</th>
                            <th class="col-num" style="color:#d97706;">Salle</th>
                            <th class="col-num" style="color:#e11d48;">Amana Food</th>
                            <th class="col-num" style="color:#7c3aed;">Cours</th>
                            <th class="col-num">Consécutifs</th>
                            <th class="col-num">Absences</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stats['personnes'] as $nom)
                            @php
                                $total = $stats['taskCounts'][$nom] ?? 0;
                                $dc = $stats['dayCounts'][$nom] ?? ['vendredis' => 0, 'samedis' => 0];
                                $tp = $stats['tasksByPerson'][$nom] ?? [];
                                $consec = $stats['consecutiveDays'][$nom] ?? 0;
                                $abs = $stats['absenceDays'][$nom] ?? 0;
                            @endphp
                            <tr>
                                <td class="td-primary">{{ $nom }}</td>
                                <td class="col-num" style="font-weight:700;color:var(--ink);">{{ $total }}</td>
                                <td class="col-num">{{ $dc['vendredis'] }}</td>
                                <td class="col-num">{{ $dc['samedis'] }}</td>
                                <td class="col-num">{{ $tp['entree'] ?? 0 }}</td>
                                <td class="col-num">{{ $tp['mektaba'] ?? 0 }}</td>
                                <td class="col-num">{{ $tp['salle'] ?? 0 }}</td>
                                <td class="col-num">{{ $tp['amana_food'] ?? 0 }}</td>
                                <td class="col-num">{{ $tp['cours'] ?? 0 }}</td>
                                <td class="col-num {{ $consec > 2 ? 'high-val' : '' }}">{{ $consec }}</td>
                                <td class="col-num" style="color:{{ $abs > 0 ? 'var(--amber)' : 'var(--ink-faint)' }};">
                                    {{ $abs ?: '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    @endif
@endsection