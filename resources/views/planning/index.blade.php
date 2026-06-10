{{-- resources/views/planning/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Planning — AMANA')

@push('styles')
    <style>
        /* ── Filter bar ── */
        .filter-bar {
            background: var(--surface);
            border: 1px solid var(--surface-border);
            border-radius: var(--radius-lg);
            padding: 14px 18px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .filter-label {
            font-size: 10.5px;
            font-weight: 700;
            color: var(--ink-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            white-space: nowrap;
        }

        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            border: 1.5px solid var(--ink-faint);
            background: var(--surface);
            color: var(--ink-muted);
            cursor: pointer;
            transition: var(--transition);
            user-select: none;
        }

        .filter-chip:hover {
            border-color: var(--app-accent);
            color: var(--app-accent);
            background: var(--sky-bg);
        }

        .filter-chip.active {
            background: var(--app-accent);
            border-color: transparent;
            color: white;
            box-shadow: 0 2px 8px rgba(3, 105, 161, 0.3);
        }

        .filter-divider {
            width: 1px;
            height: 24px;
            background: var(--surface-3);
            flex-shrink: 0;
        }

        .filter-clear {
            background: none;
            border: none;
            color: var(--ink-muted);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            font-family: var(--font-body);
            white-space: nowrap;
        }

        .filter-clear:hover {
            color: var(--rose);
            background: var(--rose-bg);
        }

        .results-count {
            font-size: 12px;
            color: var(--ink-muted);
            margin-left: auto;
        }

        /* ── Week blocks ── */
        .week-block {
            margin-bottom: 18px;
        }

        .week-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 18px;
            background: var(--app-sidebar-bg);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .week-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: var(--font-heading);
            font-weight: 600;
            font-size: 13px;
            color: white;
        }

        .week-num {
            background: rgba(255, 255, 255, 0.12);
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 600;
            font-family: var(--font-body);
        }

        .week-dates {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.45);
            font-family: var(--font-body);
        }

        .week-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-delete-week {
            background: rgba(225, 29, 72, 0.3);
            border: 1px solid rgba(225, 29, 72, 0.6);
            color: rgba(255, 255, 255, 0.65);
            padding: 5px 12px;
            border-radius: var(--radius-sm);
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--font-body);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-delete-week:hover {
            background: rgba(225, 29, 72, 0.28);
            color: white;
            border-color: rgba(225, 29, 72, 0.5);
        }

        .btn-add-creneau {
                background: rgba(14, 165, 233, 0.3);
                border: 1px solid rgba(14, 165, 233, 0.6);
                color: rgba(255, 255, 255, 0.75);
                padding: 5px 12px;
                border-radius: var(--radius-sm);
                font-size: 11.5px;
                font-weight: 600;
                cursor: pointer;
                font-family: var(--font-body);
                transition: var(--transition);
                display: flex;
                align-items: center;
                gap: 5px;
            }

            .btn-add-creneau:hover {
                background: rgba(14, 165, 233, 0.25);
                color: white;
                border-color: rgba(14, 165, 233, 0.55);
            }

            .week-body {
                background: var(--surface);
                border: 1px solid var(--surface-border);
                border-top: none;
                border-radius: 0 0 var(--radius-lg) var(--radius-lg);
                overflow: hidden;
                box-shadow: var(--shadow);
            }

            /* ── Bannière événement dans le bloc semaine ── */
            /* Événement informatif (aucune tâche bloquée) */
            .event-banner-info {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 9px 18px;
                background: linear-gradient(90deg, #f5f3ff 0%, #ede9fe 100%);
                border-bottom: 1px solid #ddd6fe;
                font-size: 12.5px;
                color: #5b21b6;
            }

            /* Événement bloquant (tâches partiellement ou totalement bloquées) */
            .event-banner-blocking {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 9px 18px;
                background: linear-gradient(90deg, #fff1f2 0%, #ffe4e6 100%);
                border-bottom: 1px solid #fecdd3;
                font-size: 12.5px;
                color: #9f1239;
            }

            .event-banner-icon {
                font-size: 14px;
                flex-shrink: 0;
            }

            .event-banner-name {
                font-weight: 700;
            }

            .event-banner-dates {
                color: inherit;
                opacity: 0.7;
                font-size: 11.5px;
                margin-left: 4px;
            }

            .event-banner-taches {
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
                margin-left: auto;
            }

            /* ── Task cells ── */
            .task-cell {
                position: relative;
                min-width: 120px;
            }

            .task-cell-inner {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 2px 4px;
                border-radius: var(--radius-sm);
                cursor: pointer;
                transition: var(--transition);
            }

            .task-cell-inner:hover {
                background: var(--surface-2);
            }

            .task-cell-inner:hover .edit-icon {
                opacity: 1;
            }

            .edit-icon {
                opacity: 0;
                font-size: 10px;
                color: var(--ink-muted);
                transition: var(--transition);
                flex-shrink: 0;
            }

            .tache-chip {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 3px 10px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
            }

            .tache-chip.entree     { background: #eff6ff; color: #2563eb; }
            .tache-chip.mektaba    { background: #ecfdf5; color: #059669; }
            .tache-chip.salle      { background: #fffbeb; color: #d97706; }
            .tache-chip.amana_food { background: #fff1f2; color: #e11d48; }
            .tache-chip.cours      { background: #f5f3ff; color: #7c3aed; }

            .tache-vide {
                color: var(--ink-faint);
                font-style: italic;
                font-size: 12px;
            }

            /* Cellule bloquée par un événement */
            .task-cell-blocked {
                background: #fff8f0;
            }

            .tache-blocked-label {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 3px 9px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 600;
                background: #fff3cd;
                color: #92400e;
                border: 1px solid #fde68a;
                max-width: 160px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            /* Ligne entièrement bloquée */
            .day-row-blocked td {
                opacity: 0.55;
            }

            .event-tag {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                background: var(--amber-bg);
                color: var(--amber);
                border: 1px solid var(--amber-border);
                padding: 2px 8px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 600;
            }

            .event-tag.blocked {
                background: var(--rose-bg);
                color: var(--rose);
                border-color: var(--rose-border);
            }

            .btn-delete-day {
                opacity: 0;
                background: none;
                border: 1px solid var(--ink-faint);
                color: var(--ink-muted);
                border-radius: var(--radius-sm);
                padding: 3px 7px;
                font-size: 11px;
                cursor: pointer;
                transition: var(--transition);
                font-family: var(--font-body);
                white-space: nowrap;
            }

            tr:hover .btn-delete-day {
                opacity: 1;
            }

            .btn-delete-day:hover {
                background: var(--rose-bg);
                border-color: var(--rose);
                color: var(--rose);
            }

            /* ── Assignment modal ── */
            .modal-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(13, 17, 23, 0.5);
                backdrop-filter: blur(3px);
                -webkit-backdrop-filter: blur(3px);
                z-index: 1000;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.2s;
            }

            .modal-backdrop.open {
                opacity: 1;
                pointer-events: all;
            }

            .modal {
                background: var(--surface);
                border-radius: var(--radius-xl);
                box-shadow: var(--shadow-lg);
                width: 100%;
                max-width: 440px;
                overflow: hidden;
                transform: translateY(14px) scale(0.98);
                transition: transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
                border: 1px solid var(--surface-border);
            }

            .modal-backdrop.open .modal {
                transform: translateY(0) scale(1);
            }

            .modal-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px 20px;
                border-bottom: 1px solid var(--surface-3);
            }

            .modal-title {
                font-family: var(--font-heading);
                font-size: 14px;
                font-weight: 600;
                color: var(--ink);
                display: flex;
                align-items: center;
                gap: 9px;
            }

            .modal-title-icon {
                width: 28px;
                height: 28px;
                border-radius: var(--radius-sm);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 13px;
            }

            .modal-close {
                background: none;
                border: none;
                color: var(--ink-muted);
                font-size: 18px;
                cursor: pointer;
                padding: 4px 8px;
                border-radius: var(--radius-sm);
                transition: var(--transition);
                line-height: 1;
            }

            .modal-close:hover {
                background: var(--surface-2);
                color: var(--ink);
            }

            .modal-body {
                padding: 20px;
            }

            .modal-info {
                background: var(--surface-2);
                border-radius: var(--radius);
                padding: 11px 15px;
                margin-bottom: 18px;
                font-size: 12.5px;
                color: var(--ink-muted);
                display: flex;
                flex-direction: column;
                gap: 3px;
            }

            .modal-info strong {
                color: var(--ink);
                font-size: 13px;
            }

            .modal-section-title {
                font-size: 10.5px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.8px;
                color: var(--ink-muted);
                margin-bottom: 9px;
            }

            .person-select-wrap {
                display: flex;
                gap: 9px;
                margin-bottom: 16px;
            }

            .person-select-wrap select {
                flex: 1;
                padding: 9px 13px;
                border: 1.5px solid var(--ink-faint);
                border-radius: var(--radius);
                font-size: 13px;
                font-family: var(--font-body);
                color: var(--ink);
                background: var(--surface);
                outline: none;
                transition: var(--transition);
                -webkit-appearance: none;
                appearance: none;
            }

            .person-select-wrap select:focus {
                border-color: var(--app-accent);
                box-shadow: var(--shadow-glow);
            }

            /* ── Add créneau modal ── */
            .add-creneau-modal { max-width: 380px; }

            .add-creneau-modal input[type="date"] {
                width: 100%;
                padding: 10px 14px;
                border: 1.5px solid var(--ink-faint);
                border-radius: var(--radius);
                font-size: 14px;
                font-family: var(--font-body);
                color: var(--ink);
                background: var(--surface);
                outline: none;
                transition: var(--transition);
                -webkit-appearance: auto;
                appearance: auto;
            }

            .add-creneau-modal input[type="date"]:focus {
                border-color: var(--app-accent);
                box-shadow: var(--shadow-glow);
            }

            .add-creneau-hint {
                font-size: 12px;
                color: var(--ink-muted);
                margin-top: 6px;
                line-height: 1.55;
            }

            /* ── Toasts ── */
            .toast-container {
                position: fixed;
                bottom: 22px;
                right: 22px;
                z-index: 2000;
                display: flex;
                flex-direction: column;
                gap: 9px;
            }

            .toast {
                background: var(--ink);
                color: white;
                padding: 11px 16px;
                border-radius: var(--radius-lg);
                font-size: 13px;
                font-weight: 500;
                box-shadow: var(--shadow-lg);
                display: flex;
                align-items: center;
                gap: 10px;
                min-width: 240px;
                animation: toastIn 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
            }

            .toast.success { border-left: 3px solid var(--emerald); }
            .toast.error   { border-left: 3px solid var(--rose); }

            @keyframes toastIn {
                from { opacity: 0; transform: translateX(16px); }
                to   { opacity: 1; transform: translateX(0); }
            }

            @keyframes toastOut {
                from { opacity: 1; transform: translateX(0); }
                to   { opacity: 0; transform: translateX(16px); }
            }

            /* ── Historique banner ── */
            .historique-banner {
                background: var(--amber-bg);
                border: 1px solid var(--amber-border);
                border-radius: var(--radius);
                padding: 10px 16px;
                margin-bottom: 18px;
                font-size: 13px;
                color: #92400e;
                display: flex;
                align-items: center;
                gap: 10px;
            }
        </style>
@endpush

@section('content')
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Planning des permanences</div>
            <div class="page-subtitle">Vendredis &amp; samedis — cliquez sur une cellule pour modifier</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('planning.export.form') }}" class="btn btn-secondary">📄 Export PDF</a>
            @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                <a href="{{ route('planning.generate.form') }}" class="btn btn-primary">✨ Générer</a>
            @endif
        </div>
    </div>

    {{-- Bannière mode historique --}}
    @if($historique)
        <div class="historique-banner">
            <span>📚</span>
            <span>Affichage de tout l'historique.</span>
            <a href="{{ route('planning.index') }}" class="btn btn-secondary btn-sm" style="margin-left:auto;">
                ← Vue normale (1 an)
            </a>
        </div>
    @endif

    @if($creneaux->isEmpty())
        <div class="card">
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <div class="empty-title">Aucun planning généré</div>
                <div class="empty-desc">
                    @if(!$historique)
                        Aucun créneau dans les 12 derniers mois.
                        <a href="{{ route('planning.index', ['historique' => 1]) }}" style="color:var(--app-accent);font-weight:600;">
                            Voir tout l'historique
                        </a>
                    @else
                        Cliquez sur "Générer" pour créer le premier planning automatique.
                    @endif
                </div>
                @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                    <a href="{{ route('planning.generate.form') }}" class="btn btn-primary btn-lg" style="margin-top:16px;">✨ Générer maintenant</a>
                @endif
            </div>
        </div>
    @else

        @php
            $allYears = [];
            $allMonths = [];
            foreach ($creneaux as $group) {
                foreach ($group as $c) {
                    $allYears[$c->date->year] = $c->date->year;
                    $allMonths[$c->date->month] = $c->date->locale('fr')->isoFormat('MMMM');
                }
            }
            krsort($allYears);
            ksort($allMonths);

            // Mois courant et mois précédent pour filtre par défaut
            $currentMonth = (int) now()->format('n');
            $previousMonth = $currentMonth === 1 ? 12 : $currentMonth - 1;
        @endphp

        {{-- Filter bar --}}
        <div class="filter-bar">
            <span class="filter-label">Filtrer</span>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span class="filter-label" style="font-size:10px;">Année</span>
                <div class="filter-group" id="yearFilters">
                    @foreach($allYears as $year)
                        <span class="filter-chip" data-type="year" data-value="{{ $year }}"
                            onclick="toggleFilter(this)">{{ $year }}</span>
                    @endforeach
                </div>
            </div>
            <div class="filter-divider"></div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span class="filter-label" style="font-size:10px;">Mois</span>
                <div class="filter-group" id="monthFilters">
                    @foreach($allMonths as $num => $name)
                        <span class="filter-chip {{ in_array($num, [$currentMonth, $previousMonth]) ? 'active' : '' }}"
                            data-type="month" data-value="{{ $num }}"
                            onclick="toggleFilter(this)">{{ ucfirst($name) }}</span>
                    @endforeach
                </div>
            </div>
            <div class="filter-divider"></div>
            <button class="filter-clear" onclick="clearFilters()">✕ Effacer</button>
            @if(!$historique)
                <a href="{{ route('planning.index', ['historique' => 1]) }}"
                    style="font-size:12px;color:var(--ink-muted);white-space:nowrap;text-decoration:none;padding:4px 10px;border-radius:var(--radius-sm);border:1.5px solid var(--ink-faint);transition:var(--transition);"
                    onmouseover="this.style.color='var(--app-accent)';this.style.borderColor='var(--app-accent)'"
                    onmouseout="this.style.color='var(--ink-muted)';this.style.borderColor='var(--ink-faint)'">
                    📚 Historique complet
                </a>
            @endif
            <span class="results-count" id="resultsCount"></span>
        </div>

        {{-- Weeks --}}
        <div id="planningContainer">
            @foreach($creneaux as $semaineCle => $creneauxSemaine)
                @php
                    $first = $creneauxSemaine->first();
                    $last = $creneauxSemaine->last();
                    $weekYear = $first->date->year;
                    $weekMonth = $first->date->month;
                    $weekIds = $creneauxSemaine->pluck('id')->join(',');

                    $weekMonday = $first->date->copy()->subDays($first->date->isoWeekday() - 1)->startOfDay();
                    $weekSunday = $weekMonday->copy()->addDays(6)->endOfDay();
                    $existingDates = $creneauxSemaine->pluck('date')->map(fn($d) => $d->toDateString())->toJson();

                    // Bannières pour cette semaine
                    $bannièresSemaine = $bannièresParSemaine[$semaineCle] ?? [];

                    // Y a-t-il un événement qui bloque tout le créneau (toutes les tâches) ?
                    $nbTachesActives = $creneauxSemaine->first()?->taches->count() ?? 5;
                    $evtToutBloque = collect($bannièresSemaine)->filter(function ($b) use ($nbTachesActives) {
                        return !$b['informatif'] && $b['evenement']->tachesBloquees->count() >= $nbTachesActives;
                    })->first();
                @endphp
                <div class="week-block" data-year="{{ $weekYear }}" data-month="{{ $weekMonth }}">
                    <div class="week-header">
                        <div class="week-label">
                            📅 <span class="week-num">S{{ $first->semaine }}</span>
                            {{ $first->date->locale('fr')->isoFormat('D MMMM') }} —
                            {{ $last->date->locale('fr')->isoFormat('D MMMM YYYY') }}
                            @if($evtToutBloque)
                                <span style="
                                    background:rgba(225,29,72,0.25);
                                    border:1px solid rgba(225,29,72,0.5);
                                    color:#fda4af;
                                    padding:2px 10px;
                                    border-radius:20px;
                                    font-size:11px;
                                    font-weight:600;
                                    margin-left:6px;
                                ">
                                    🚫 {{ $evtToutBloque['evenement']->nom }}
                                </span>
                            @endif
                        </div>
                        <div class="week-actions">
                            <span class="week-dates">{{ $creneauxSemaine->count() }} créneaux</span>
                            @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                                <button class="btn-add-creneau" onclick="openAddCreneauModal(
                                    '{{ $weekMonday->toDateString() }}',
                                    '{{ $weekSunday->toDateString() }}',
                                    {{ $existingDates }}
                                )">
                                    ➕ Créneau
                                </button>
                                <button class="btn-delete-week" onclick="deleteWeek([{{ $weekIds }}], this)">
                                    🗑️ Supprimer la semaine
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="week-body">

                        {{-- Bannières événements de la semaine --}}
                        @foreach($bannièresSemaine as $bannière)
                            @php
                                $evt = $bannière['evenement'];
                                $debutStr = $bannière['debut_semaine']->locale('fr')->isoFormat('D MMM');
                                $finStr = $bannière['fin_semaine']->locale('fr')->isoFormat('D MMM');
                                $mêmeJour = $bannière['debut_semaine']->isSameDay($bannière['fin_semaine']);
                                $dateStr = $mêmeJour ? $debutStr : "{$debutStr} – {$finStr}";
                            @endphp

                            @if($bannière['informatif'])
                                {{-- Événement purement informatif --}}
                                <div class="event-banner-info">
                                    <span class="event-banner-icon">📅</span>
                                    <span class="event-banner-name">{{ $evt->nom }}</span>
                                    <span class="event-banner-dates">— {{ $dateStr }}</span>
                                </div>
                            @else
                                {{-- Événement bloquant (partiel ou total) --}}
                                <div class="event-banner-blocking">
                                    <span class="event-banner-icon">🚫</span>
                                    <span class="event-banner-name">{{ $evt->nom }}</span>
                                    <span class="event-banner-dates">— {{ $dateStr }}</span>
                                    <div class="event-banner-taches">
                                        @foreach($evt->tachesBloquees as $tb)
                                            @php
                                                $tbColors = [
                                                    'entree' => ['bg' => '#eff6ff', 'color' => '#2563eb'],
                                                    'mektaba' => ['bg' => '#ecfdf5', 'color' => '#059669'],
                                                    'salle' => ['bg' => '#fffbeb', 'color' => '#d97706'],
                                                    'amana_food' => ['bg' => '#fff1f2', 'color' => '#e11d48'],
                                                    'cours' => ['bg' => '#f5f3ff', 'color' => '#7c3aed'],
                                                ];
                                                $s = $tbColors[$tb->code] ?? ['bg' => 'var(--surface-3)', 'color' => 'var(--ink)'];
                                            @endphp
                                            <span style="
                                                display:inline-flex;align-items:center;
                                                padding:2px 8px;border-radius:20px;
                                                font-size:11px;font-weight:600;
                                                background:{{ $s['bg'] }};color:{{ $s['color'] }};
                                            ">{{ $tb->libelle }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        {{-- Table des créneaux --}}
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Jour</th>
                                        <th style="color:#2563eb;">🚪 Entrée</th>
                                        <th style="color:#059669;">📚 Mektaba</th>
                                        <th style="color:#d97706;">🏛️ Salle</th>
                                        <th style="color:#e11d48;">🥪 Amana Food</th>
                                        <th style="color:#7c3aed;">🎓 Cours</th>
                                        <th>Événements</th>
                                        <th style="width:36px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($creneauxSemaine as $creneau)
                                        @php
                                            $tachesMap = $creneau->taches->keyBy(fn($t) => $t->tache?->code);

                                            // Construire les codes de tâches bloquées pour CE créneau
                                            $tachesBloquéesCreneau = collect();
                                            $nomEvenementsBloquants = [];
                                            foreach ($creneau->evenements as $evtCreneau) {
                                                foreach ($evtCreneau->tachesBloquees as $tb) {
                                                    $tachesBloquéesCreneau->push($tb->code);
                                                }
                                                if ($evtCreneau->tachesBloquees->isNotEmpty()) {
                                                    $nomEvenementsBloquants[$evtCreneau->nom] = $evtCreneau->nom;
                                                }
                                            }
                                            $tachesBloquéesCreneau = $tachesBloquéesCreneau->unique();

                                            // Nom de l'événement bloquant (pour affichage dans cellule)
                                            $nomEvtBloquant = implode(', ', $nomEvenementsBloquants);

                                            // Tout bloqué = toutes les tâches actives sont bloquées
                                            $nbTaches = $creneau->taches->count();
                                            $toutBloque = $tachesBloquéesCreneau->count() >= $nbTaches
                                                && $tachesBloquéesCreneau->isNotEmpty();

                                            $evtStr = $creneau->evenements->pluck('nom')->implode(', ');
                                        @endphp
                                        <tr class="{{ $toutBloque ? 'day-row-blocked' : '' }}"
                                            id="row-creneau-{{ $creneau->id }}">

                                            {{-- Jour + date --}}
                                            <td>
                                                <div style="display:flex;align-items:center;gap:8px;">
                                                    <strong style="color:var(--ink);font-family:var(--font-heading);font-size:13px;">
                                                        {{ $creneau->jour }}
                                                    </strong>
                                                    <span style="color:var(--ink-muted);font-size:12px;">
                                                        {{ $creneau->date->locale('fr')->isoFormat('D MMM YYYY') }}
                                                    </span>
                                                    @if($toutBloque)
                                                        <span class="badge badge-danger badge-dot" style="font-size:10px;">Bloqué</span>
                                                    @elseif($tachesBloquéesCreneau->isNotEmpty())
                                                        <span class="badge badge-warning badge-dot" style="font-size:10px;">Partiel</span>
                                                    @endif
                                                </div>
                                            </td>

                                            {{-- 5 tâches --}}
                                            @foreach(['entree', 'mektaba', 'salle', 'amana_food', 'cours'] as $code)
                                                @php
                                                    $ct = $tachesMap->get($code);
                                                    $tacheId = $ct?->id_tache;
                                                    $personne = $ct?->personne;
                                                    $estBloquee = $tachesBloquéesCreneau->contains($code);
                                                @endphp
                                                <td class="task-cell {{ $estBloquee ? 'task-cell-blocked' : '' }}"
                                                    id="cell-{{ $creneau->id }}-{{ $code }}"
                                                    data-creneau-id="{{ $creneau->id }}"
                                                    data-tache-id="{{ $tacheId }}"
                                                    data-tache-code="{{ $code }}"
                                                    data-tache-label="{{ ucfirst(str_replace('_', ' ', $code)) }}"
                                                    data-jour="{{ $creneau->jour }}"
                                                    data-date="{{ $creneau->date->locale('fr')->isoFormat('D MMM YYYY') }}">

                                                    @if($estBloquee)
                                                        {{-- Tâche bloquée : afficher le nom de l'événement --}}
                                                        <div class="task-cell-inner" style="cursor:default;">
                                                            <span class="tache-blocked-label" title="{{ $nomEvtBloquant }}">
                                                                🚫 {{ Str::limit($nomEvtBloquant, 20) }}
                                                            </span>
                                                        </div>
                                                    @else
                                                        @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                                                            <div class="task-cell-inner" onclick="openEditModal(this.closest('td'))">
                                                        @else
                                                            <div class="task-cell-inner" style="cursor:default;">
                                                        @endif
                                                                @if($personne)
                                                                    <span class="tache-chip {{ $code }}" id="chip-{{ $creneau->id }}-{{ $code }}">
                                                                        {{ $personne->prenom }} {{ $personne->nom }}
                                                                    </span>
                                                                @else
                                                                    <span class="tache-vide" id="chip-{{ $creneau->id }}-{{ $code }}">—</span>
                                                                @endif
                                                                @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                                                                    <span class="edit-icon">✏️</span>
                                                                @endif
                                                            </div>
                                                    @endif
                                                </td>
                                            @endforeach

                                            {{-- Événements --}}
                                            <td style="color:var(--ink-faint);">
                                                @if($evtStr)
                                                    <span class="event-tag {{ $toutBloque ? 'blocked' : '' }}">{{ $evtStr }}</span>
                                                @else
                                                    <span style="font-size:12px;">—</span>
                                                @endif
                                            </td>

                                            {{-- Suppression créneau --}}
                                            <td style="text-align:right;padding-right:12px;">
                                                @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                                                    <button class="btn-delete-day"
                                                        onclick="deleteCreneau({{ $creneau->id }}, this)">🗑️</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── Edit / Assign modal ── --}}
    @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
        <div class="modal-backdrop" id="editModalBackdrop" onclick="closeOnBackdrop(event)">
            <div class="modal" id="editModal">
                <div class="modal-header">
                    <div class="modal-title">
                        <div class="modal-title-icon" id="modalTitleIcon" style="background:var(--sky-bg);">✏️</div>
                        <span id="modalTitle">Modifier l'assignation</span>
                    </div>
                    <button class="modal-close" onclick="closeModal()">×</button>
                </div>
                <div class="modal-body">
                    <div class="modal-info">
                        <strong id="modalContextDay">—</strong>
                        <span id="modalContextTask">—</span>
                    </div>
                    <div class="modal-section-title">👤 Réassigner à</div>
                    <div class="person-select-wrap">
                        <select id="modalPersonSelect">
                            <option value="">— Aucune personne (désassigner) —</option>
                        </select>
                        <button class="btn btn-primary btn-sm" onclick="saveAssignation()"
                            id="modalSaveBtn">Enregistrer</button>
                    </div>
                    <div class="divider"></div>
                    <div class="modal-section-title" style="color:var(--rose);">⚠️ Zone dangereuse</div>
                    <div style="display:flex;gap:9px;flex-wrap:wrap;">
                        <button class="btn btn-danger btn-sm" onclick="unassignTask()">✕ Désassigner</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteCreneauFromModal()">🗑️ Supprimer le créneau</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Add créneau modal ── --}}
        <div class="modal-backdrop" id="addCreneauBackdrop" onclick="closeAddCreneauOnBackdrop(event)">
            <div class="modal add-creneau-modal" id="addCreneauModal">
                <div class="modal-header">
                    <div class="modal-title">
                        <div class="modal-title-icon" style="background:var(--emerald-bg);">➕</div>
                        <span>Ajouter un créneau</span>
                    </div>
                    <button class="modal-close" onclick="closeAddCreneauModal()">×</button>
                </div>
                <div class="modal-body">
                    <div class="modal-info" id="addCreneauWeekInfo">
                        <strong>Semaine en cours</strong>
                        <span>Choisissez une date dans cette semaine</span>
                    </div>
                    <div class="modal-section-title">📅 Date du créneau</div>
                    <div>
                        <input type="date" id="addCreneauDate" />
                        <div class="add-creneau-hint" id="addCreneauHint"></div>
                    </div>
                    <div style="display:flex;gap:9px;margin-top:16px;">
                        <button class="btn btn-primary" style="flex:1;justify-content:center;"
                            onclick="submitAddCreneau()" id="addCreneauBtn">
                            ➕ Créer le créneau
                        </button>
                        <button class="btn btn-secondary" onclick="closeAddCreneauModal()">Annuler</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="toast-container" id="toastContainer"></div>
@endsection

@push('scripts')
    <script>
        const CSRF   = document.querySelector('meta[name="csrf-token"]').content;
        const ROUTES = {
            personnes:   '{{ route("planning.edit.personnes") }}',
            assignation: '{{ url("planning/creneau") }}',
            creneau:     '{{ url("planning/creneau") }}',
        };

        let personnesCache = null;
        let currentCell    = null;
        const activeYears  = new Set();
        const activeMonths = new Set();

        // ── Init filtre par défaut : mois courant + mois précédent ──────────
        document.addEventListener('DOMContentLoaded', function () {
            const defaultChips = document.querySelectorAll('.filter-chip[data-type="month"].active');
            defaultChips.forEach(chip => activeMonths.add(parseInt(chip.dataset.value)));
            if (activeMonths.size > 0) applyFilters();
        });

        /* ══ FILTERS ══════════════════════════════════════════════════════ */
        function toggleFilter(chip) {
            const type  = chip.dataset.type;
            const value = parseInt(chip.dataset.value);
            const set   = type === 'year' ? activeYears : activeMonths;
            if (set.has(value)) { set.delete(value); chip.classList.remove('active'); }
            else { set.add(value); chip.classList.add('active'); }
            applyFilters();
        }

        function clearFilters() {
            activeYears.clear(); activeMonths.clear();
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            applyFilters();
        }

        function applyFilters() {
            let visible = 0;
            document.querySelectorAll('.week-block').forEach(block => {
                const y = activeYears.size  === 0 || activeYears.has(parseInt(block.dataset.year));
                const m = activeMonths.size === 0 || activeMonths.has(parseInt(block.dataset.month));
                block.style.display = (y && m) ? '' : 'none';
                if (y && m) visible++;
            });
            const el = document.getElementById('resultsCount');
            if (el) el.textContent = (activeYears.size || activeMonths.size)
                ? `${visible} semaine${visible !== 1 ? 's' : ''} affichée${visible !== 1 ? 's' : ''}`
                : '';
        }

        /* ══ LOAD PERSONNES ═══════════════════════════════════════════════ */
        async function loadPersonnes() {
            if (personnesCache) return personnesCache;
            const res = await fetch(ROUTES.personnes, {
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
            });
            personnesCache = await res.json();
            return personnesCache;
        }

        /* ══ EDIT / ASSIGN MODAL ══════════════════════════════════════════ */
        async function openEditModal(td) {
            currentCell = td;
            const code  = td.dataset.tacheCode;
            const label = td.dataset.tacheLabel;

            const colors = {
                entree:     { bg: '#eff6ff', icon: '🚪' },
                mektaba:    { bg: '#ecfdf5', icon: '📚' },
                salle:      { bg: '#fffbeb', icon: '🏛️' },
                amana_food: { bg: '#fff1f2', icon: '🥪' },
                cours:      { bg: '#f5f3ff', icon: '🎓' },
            };

            const c = colors[code] || { bg: 'var(--sky-bg)', icon: '✏️' };
            document.getElementById('modalTitleIcon').style.background = c.bg;
            document.getElementById('modalTitleIcon').textContent      = c.icon;
            document.getElementById('modalTitle').textContent          = `Modifier — ${label}`;
            document.getElementById('modalContextDay').textContent     = `${td.dataset.jour} ${td.dataset.date}`;
            document.getElementById('modalContextTask').textContent    = `Tâche : ${label}`;

            const select   = document.getElementById('modalPersonSelect');
            select.innerHTML = '<option value="">— Aucune personne (désassigner) —</option>';
            const personnes = await loadPersonnes();
            personnes.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id; opt.textContent = p.label;
                select.appendChild(opt);
            });

            const chip = document.getElementById(`chip-${td.dataset.creneauId}-${code}`);
            if (chip && !chip.classList.contains('tache-vide')) {
                const name = chip.textContent.trim();
                for (const opt of select.options) {
                    if (opt.textContent.trim() === name) { opt.selected = true; break; }
                }
            }

            document.getElementById('editModalBackdrop').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('editModalBackdrop')?.classList.remove('open');
            document.body.style.overflow = '';
            currentCell = null;
        }

        function closeOnBackdrop(e) {
            if (e.target === document.getElementById('editModalBackdrop')) closeModal();
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') { closeModal(); closeAddCreneauModal(); }
        });

        async function saveAssignation() {
            if (!currentCell) return;
            const creneauId = currentCell.dataset.creneauId;
            const tacheId   = currentCell.dataset.tacheId;
            const personneId = document.getElementById('modalPersonSelect').value || null;
            const btn = document.getElementById('modalSaveBtn');
            btn.disabled = true; btn.textContent = '…';
            try {
                const res = await fetch(`${ROUTES.assignation}/${creneauId}/tache/${tacheId}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ id_personne: personneId ? parseInt(personneId) : null }),
                });
                const data = await res.json();
                if (data.success) { updateCell(currentCell, data.personne); showToast(data.message, 'success'); closeModal(); }
                else showToast('Erreur lors de la mise à jour', 'error');
            } catch { showToast('Erreur réseau', 'error'); }
            finally { btn.disabled = false; btn.textContent = 'Enregistrer'; }
        }

        async function unassignTask() {
            if (!currentCell) return;
            if (!confirm('Désassigner cette tâche ?')) return;
            const creneauId = currentCell.dataset.creneauId;
            const tacheId   = currentCell.dataset.tacheId;
            try {
                const res = await fetch(`${ROUTES.assignation}/${creneauId}/tache/${tacheId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (data.success) { updateCell(currentCell, null); showToast('Tâche désassignée', 'success'); closeModal(); }
            } catch { showToast('Erreur réseau', 'error'); }
        }

        async function deleteCreneau(id, el) {
            if (!confirm('Supprimer ce créneau et toutes ses tâches ?')) return;
            await doDeleteCreneau(id, el);
        }

        async function deleteCreneauFromModal() {
            if (!currentCell) return;
            const id = parseInt(currentCell.dataset.creneauId);
            if (!confirm('Supprimer tout ce créneau ?')) return;
            closeModal();
            await doDeleteCreneau(id, null);
        }

        async function doDeleteCreneau(id, el) {
            if (el) { el.disabled = true; el.textContent = '…'; }
            try {
                const res = await fetch(`${ROUTES.creneau}/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (data.success) {
                    const row = document.getElementById(`row-creneau-${id}`);
                    if (row) {
                        row.style.transition = 'opacity 0.3s';
                        row.style.opacity    = '0';
                        setTimeout(() => { row.remove(); checkEmptyWeeks(); }, 300);
                    }
                    showToast(data.message, 'success');
                } else showToast('Erreur', 'error');
            } catch {
                showToast('Erreur réseau', 'error');
                if (el) { el.disabled = false; el.textContent = '🗑️'; }
            }
        }

        async function deleteWeek(ids, el) {
            if (!confirm(`Supprimer les ${ids.length} créneaux de cette semaine ?`)) return;
            el.disabled = true; el.innerHTML = '⏳ Suppression…';
            let n = 0;
            for (const id of ids) {
                try {
                    const res = await fetch(`${ROUTES.creneau}/${id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    if (data.success) { n++; document.getElementById(`row-creneau-${id}`)?.remove(); }
                } catch {}
            }
            checkEmptyWeeks();
            showToast(`Semaine supprimée (${n} créneaux)`, 'success');
        }

        function updateCell(td, personne) {
            const code = td.dataset.tacheCode;
            const chip = document.getElementById(`chip-${td.dataset.creneauId}-${code}`);
            if (!chip) return;
            if (personne) { chip.className = `tache-chip ${code}`; chip.textContent = personne.label; }
            else { chip.className = 'tache-vide'; chip.textContent = '—'; }
        }

        function checkEmptyWeeks() {
            document.querySelectorAll('.week-block').forEach(block => {
                if (block.querySelectorAll('tbody tr').length === 0) {
                    block.style.transition = 'opacity 0.4s';
                    block.style.opacity    = '0';
                    setTimeout(() => block.remove(), 400);
                }
            });
        }

        /* ══ ADD CRÉNEAU MODAL ════════════════════════════════════════════ */
        let addCreneauMin = '', addCreneauMax = '', addCreneauExisting = [];

        function openAddCreneauModal(weekMin, weekMax, existingDates) {
            addCreneauMin      = weekMin;
            addCreneauMax      = weekMax;
            addCreneauExisting = existingDates || [];

            const infoEl = document.getElementById('addCreneauWeekInfo');
            if (infoEl) {
                const fmtMin = new Date(weekMin + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' });
                const fmtMax = new Date(weekMax + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
                infoEl.innerHTML = `<strong>Semaine du ${fmtMin} au ${fmtMax}</strong>`;
            }

            const dateInput = document.getElementById('addCreneauDate');
            dateInput.min   = weekMin;
            dateInput.max   = weekMax;
            dateInput.value = '';

            const hint = document.getElementById('addCreneauHint');
            if (addCreneauExisting.length > 0) {
                const labels = addCreneauExisting.map(d => {
                    const dt = new Date(d + 'T00:00:00');
                    return dt.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
                });
                hint.textContent = `Déjà créé : ${labels.join(', ')}.`;
            } else {
                hint.textContent = 'Choisissez n\'importe quel jour de cette semaine.';
            }

            document.getElementById('addCreneauBackdrop').classList.add('open');
            document.body.style.overflow = 'hidden';
            setTimeout(() => dateInput.focus(), 220);
        }

        function closeAddCreneauModal() {
            document.getElementById('addCreneauBackdrop')?.classList.remove('open');
            document.body.style.overflow = '';
        }

        function closeAddCreneauOnBackdrop(e) {
            if (e.target === document.getElementById('addCreneauBackdrop')) closeAddCreneauModal();
        }

        async function submitAddCreneau() {
            const dateInput = document.getElementById('addCreneauDate');
            const date      = dateInput.value;

            if (!date) { dateInput.focus(); showToast('Veuillez choisir une date.', 'error'); return; }
            if (addCreneauExisting.includes(date)) { showToast('Un créneau existe déjà pour cette date.', 'error'); dateInput.focus(); return; }

            const btn = document.getElementById('addCreneauBtn');
            btn.disabled = true; btn.textContent = '⏳ Création…';

            try {
                const res = await fetch(ROUTES.creneau, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ date }),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    showToast(data.message, 'success');
                    closeAddCreneauModal();
                    setTimeout(() => window.location.reload(), 700);
                } else {
                    const msg = data.errors?.date?.[0] || data.message || 'Erreur lors de la création.';
                    showToast(msg, 'error');
                    btn.disabled = false; btn.textContent = '➕ Créer le créneau';
                }
            } catch {
                showToast('Erreur réseau', 'error');
                btn.disabled = false; btn.textContent = '➕ Créer le créneau';
            }
        }

        /* ══ TOASTS ═══════════════════════════════════════════════════════ */
        function showToast(msg, type = 'success') {
            const c = document.getElementById('toastContainer');
            const t = document.createElement('div');
            t.className = `toast ${type}`;
            t.innerHTML = `<span>${type === 'success' ? '✅' : '❌'}</span><span>${msg}</span>`;
            c.appendChild(t);
            setTimeout(() => {
                t.style.animation = 'toastOut 0.3s ease forwards';
                setTimeout(() => t.remove(), 300);
            }, 3200);
        }
    </script>
@endpush