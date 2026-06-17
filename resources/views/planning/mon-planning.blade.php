{{-- resources/views/planning/mon-planning.blade.php --}}
@extends('layouts.app')

@section('title', 'Mon planning — AMANA')

@push('styles')
    <style>
        /* ── Timeline layout ── */
        .timeline-wrapper {
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        .month-section {}

        .month-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .month-label {
            font-family: var(--font-heading);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--ink-muted);
        }

        .month-divider {
            flex: 1;
            height: 1px;
            background: var(--surface-border);
        }

        .month-count {
            font-size: 11px;
            color: var(--ink-faint);
            font-weight: 600;
            white-space: nowrap;
        }

        /* ── Cards ── */
        .creneau-cards {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .creneau-card {
            background: var(--surface);
            border: 1px solid var(--surface-border);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 18px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .creneau-card:hover {
            box-shadow: var(--shadow);
            transform: translateY(-1px);
        }

        .creneau-card.is-future {
            border-left: 3px solid var(--app-accent);
        }

        .creneau-card.is-past {
            opacity: 0.72;
        }

        .creneau-card.is-today {
            border-left: 3px solid var(--emerald);
            background: var(--emerald-bg);
        }

        /* Left: date block */
        .date-block {
            flex-shrink: 0;
            width: 56px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1px;
        }

        .date-day-num {
            font-family: var(--font-heading);
            font-size: 26px;
            font-weight: 700;
            line-height: 1;
            color: var(--ink);
        }

        .date-month-str {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: var(--ink-muted);
        }

        .date-jour-str {
            font-size: 10px;
            color: var(--ink-faint);
            margin-top: 2px;
            font-weight: 600;
        }

        /* Vertical separator */
        .card-sep {
            width: 1px;
            height: 44px;
            background: var(--surface-3);
            flex-shrink: 0;
        }

        /* Middle: task info */
        .task-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .task-main {
            display: flex;
            align-items: center;
            gap: 9px;
            flex-wrap: wrap;
        }

        /* .tache-chip (toutes variantes) est défini globalement dans app.css —
               pas de redéfinition ici. */

        .semaine-badge {
            font-size: 11px;
            color: var(--ink-muted);
            background: var(--surface-2);
            border: 1px solid var(--surface-border);
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        .task-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .evt-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11.5px;
            color: var(--amber);
            background: var(--amber-bg);
            border: 1px solid var(--amber-border);
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        /* Right: status badge */
        .status-col {
            flex-shrink: 0;
            text-align: right;
        }

        .badge-futur {
            background: var(--sky-bg);
            color: var(--sky);
            border: 1px solid var(--sky-border);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-passe {
            background: var(--surface-3);
            color: var(--ink-faint);
            border: 1px solid var(--ink-faint);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-today {
            background: var(--emerald-bg);
            color: var(--emerald);
            border: 1px solid var(--emerald-border);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* ── Stats strip ── */
        .stats-strip {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 26px;
        }

        .stat-pill {
            background: var(--surface);
            border: 1px solid var(--surface-border);
            border-radius: var(--radius-lg);
            padding: 13px 18px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 110px;
        }

        .stat-pill-value {
            font-family: var(--font-heading);
            font-size: 24px;
            font-weight: 700;
            color: var(--ink);
            line-height: 1;
        }

        .stat-pill-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--ink-muted);
        }

        @media (max-width: 600px) {
            .creneau-card {
                flex-wrap: wrap;
                gap: 12px;
            }

            .card-sep {
                display: none;
            }

            .status-col {
                margin-left: auto;
            }
        }
    </style>
@endpush

@section('content')

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Mon planning</div>
            <div class="page-subtitle">
                Vos permanences — un an glissant + futur
            </div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('planning.index') }}" class="btn btn-secondary">📅 Planning complet</a>
        </div>
    </div>

    {{-- Stats strip --}}
    <div class="stats-strip">
        <div class="stat-pill">
            <div class="stat-pill-value">{{ $total }}</div>
            <div class="stat-pill-label">Total créneaux</div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-value" style="color:var(--app-accent);">{{ $futures }}</div>
            <div class="stat-pill-label">À venir</div>
        </div>
        @php
            $tacheLabels = [
                'entree' => ['Entrée', '🚪'],
                'mektaba' => ['Mektaba', '📚'],
                'salle' => ['Salle', '🏛️'],
                'amana_food' => ['Amana Food', '🥪'],
                'cours' => ['Cours', '🎓'],
            ];
        @endphp
        @foreach($parTache as $code => $count)
            @if(isset($tacheLabels[$code]))
                <div class="stat-pill">
                    <div class="stat-pill-value">{{ $count }}</div>
                    <div class="stat-pill-label">{{ $tacheLabels[$code][1] }} {{ $tacheLabels[$code][0] }}</div>
                </div>
            @endif
        @endforeach
    </div>

    @if($parMois->isEmpty())
        <div class="card">
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <div class="empty-title">Aucune permanence</div>
                <div class="empty-desc">
                    Vous n'avez pas encore été assigné à des créneaux sur les 12 derniers mois.
                </div>
            </div>
        </div>
    @else
        <div class="timeline-wrapper">
            @foreach($parMois as $moisKey => $lignes)
                @php
                    $firstDate = $lignes->first()->creneau->date;
                    $moisLabel = $firstDate->locale('fr')->isoFormat('MMMM YYYY');
                    $moisLabel = ucfirst($moisLabel);
                @endphp

                <div class="month-section">
                    <div class="month-header">
                        <div class="month-label">{{ $moisLabel }}</div>
                        <div class="month-divider"></div>
                        <div class="month-count">{{ $lignes->count() }} créneau{{ $lignes->count() > 1 ? 'x' : '' }}</div>
                    </div>

                    <div class="creneau-cards">
                        @foreach($lignes as $ligne)
                            @php
                                $creneau = $ligne->creneau;
                                $tache = $ligne->tache;
                                $date = $creneau->date;

                                $isToday = $date->isToday();
                                $isFuture = $date->isFuture() && !$isToday;
                                $isPast = $date->isPast() && !$isToday;

                                $cardClass = $isToday ? 'is-today' : ($isFuture ? 'is-future' : 'is-past');

                                $evtStr = $creneau->evenements?->pluck('nom')->implode(', ');
                            @endphp

                            <div class="creneau-card {{ $cardClass }}">

                                {{-- Date block --}}
                                <div class="date-block">
                                    <div class="date-day-num">{{ $date->format('d') }}</div>
                                    <div class="date-month-str">{{ $date->locale('fr')->isoFormat('MMM') }}</div>
                                    <div class="date-jour-str">{{ $creneau->jour }}</div>
                                </div>

                                <div class="card-sep"></div>

                                {{-- Task info --}}
                                <div class="task-info">
                                    <div class="task-main">
                                        @if($tache)
                                            <span class="tache-chip {{ $tache->code }}">
                                                @php
                                                    $icons = ['entree' => '🚪', 'mektaba' => '📚', 'salle' => '🏛️', 'amana_food' => '🥪', 'cours' => '🎓'];
                                                @endphp
                                                {{ $icons[$tache->code] ?? '' }} {{ $tache->libelle }}
                                            </span>
                                        @endif
                                        <span class="semaine-badge">S{{ $creneau->semaine }}</span>
                                    </div>

                                    <div class="task-meta">
                                        <span style="font-size:12px;color:var(--ink-muted);">
                                            {{ $date->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                                        </span>
                                        @if($evtStr)
                                            <span class="evt-badge">🎉 {{ $evtStr }}</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Status --}}
                                <div class="status-col">
                                    @if($isToday)
                                        <span class="badge-today">● Aujourd'hui</span>
                                    @elseif($isFuture)
                                        <span class="badge-futur">→ À venir</span>
                                    @else
                                        <span class="badge-passe">✓ Effectué</span>
                                    @endif
                                </div>

                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

@endsection