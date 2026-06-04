{{-- resources/views/restrictions/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Disponibilités — AMANA')

@push('styles')
    <style>
        /* ── Grille restrictions ── */
        .restrictions-table th.jour-header {
            text-align: center;
            background: linear-gradient(135deg, var(--primary) 0%, var(--violet) 100%);
            color: white;
            font-size: 12px;
            padding: 10px 8px;
            text-transform: none;
            letter-spacing: 0;
            font-weight: 700;
        }
        .restrictions-table th.sub-header {
            text-align: center;
            font-size: 10.5px;
            padding: 7px 4px;
            background: var(--surface-2);
            white-space: nowrap;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .restrictions-table td {
            text-align: center;
            padding: 9px 6px;
        }
        .restrictions-table td:first-child {
            text-align: left;
            padding-left: 18px;
            font-weight: 600;
            white-space: nowrap;
            color: var(--ink);
        }
        .restrictions-table input[type="checkbox"] {
            width: 17px; height: 17px;
            cursor: pointer;
            accent-color: var(--primary);
        }
        .restrictions-table input[type="checkbox"]:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }
        .sub-entree     { color: #2563eb; }
        .sub-mektaba    { color: #059669; }
        .sub-salle      { color: #d97706; }
        .sub-amana_food { color: #e11d48; }

        /* ── Ligne surlignée pour le membre connecté ── */
        .my-row { background: var(--violet-bg) !important; }
        .my-row td:first-child::after {
            content: ' (moi)';
            font-size: 11px;
            font-weight: 400;
            color: var(--primary);
            margin-left: 6px;
        }

        /* ── Info box lecture seule ── */
        .readonly-info {
            background: var(--sky-bg);
            border: 1px solid #bae6fd;
            border-radius: var(--radius);
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #0c4a6e;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        /* ── Toolbar ── */
        .toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--surface-3);
            flex-wrap: wrap;
        }
        .hint-text {
            margin-left: auto;
            font-size: 12.5px;
            color: var(--ink-muted);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .hint-item { display: flex; align-items: center; gap: 5px; }

        /* ── Section membre — formulaire personnel ── */
        .member-section {
            background: var(--violet-bg);
            border: 1.5px solid #ddd6fe;
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            margin-bottom: 24px;
        }
        .member-section-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .member-section-sub {
            font-size: 13px;
            color: var(--ink-muted);
            margin-bottom: 18px;
            line-height: 1.5;
        }
        .member-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .member-tache-card {
            background: var(--surface);
            border: 1.5px solid var(--surface-3);
            border-radius: var(--radius);
            padding: 14px 16px;
            transition: var(--transition);
        }
        .member-tache-card:hover { border-color: var(--primary); }
        .member-tache-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .member-tache-checks {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .member-check-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--ink-light);
            cursor: pointer;
        }
        .member-check-item input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
            flex-shrink: 0;
        }
    </style>
@endpush

@section('content')
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">
                {{ ($user->isAdmin() || $user->isGestionnaire()) ? 'Restrictions de disponibilité' : 'Mes disponibilités' }}
            </div>
            <div class="page-subtitle">
                Case cochée ✓ = la personne <strong>peut</strong> effectuer la tâche ce jour-là
            </div>
        </div>
    </div>

    @if($personnes->isEmpty())
        <div class="card">
            <div class="empty-state">
                <div class="empty-icon">🔒</div>
                <div class="empty-title">Aucun membre actif</div>
                <div class="empty-desc">Ajoutez des personnes avec statut "Validé" et une date de début planning.</div>
                @if($user->isAdmin())
                    <a href="{{ route('personnes.create') }}" class="btn btn-primary">+ Ajouter une personne</a>
                @endif
            </div>
        </div>

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- VUE MEMBRE                                                           --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}
    @elseif(! $user->isAdmin() && ! $user->isGestionnaire())

        {{-- Formulaire personnel du membre --}}
        <div class="member-section">
            <div class="member-section-title">
                🔧 Modifier mes disponibilités
            </div>
            <div class="member-section-sub">
                Cochez les tâches que vous <strong>pouvez effectuer</strong> chaque jour.
                Ces informations sont prises en compte lors de la génération du planning.
            </div>

            <form action="{{ route('restrictions.update') }}" method="POST" id="memberForm">
                @csrf
                <div class="member-grid">
                    @foreach($taches as $tache)
                        @php
                            $chipColors = [
                                'entree'     => ['bg' => '#eff6ff', 'color' => '#2563eb'],
                                'mektaba'    => ['bg' => '#ecfdf5', 'color' => '#059669'],
                                'salle'      => ['bg' => '#fffbeb', 'color' => '#d97706'],
                                'amana_food' => ['bg' => '#fff1f2', 'color' => '#e11d48'],
                            ];
                            $chip = $chipColors[$tache->code] ?? ['bg' => 'var(--surface-3)', 'color' => 'var(--ink-muted)'];
                        @endphp
                        <div class="member-tache-card">
                            <div class="member-tache-title">
                                <span style="
                                    padding: 2px 9px; border-radius: 20px;
                                    font-size: 12px; font-weight: 600;
                                    background: {{ $chip['bg'] }};
                                    color: {{ $chip['color'] }};
                                ">{{ $tache->libelle }}</span>
                            </div>
                            <div class="member-tache-checks">
                                @foreach(['Vendredi', 'Samedi'] as $jour)
                                    @php
                                        $autorise = $restrictionsMap[$user->id][$tache->id][$jour] ?? true;
                                    @endphp
                                    <label class="member-check-item">
                                        <input
                                            type="checkbox"
                                            name="checkboxes[{{ $user->id }}][{{ $tache->id }}][{{ $jour }}]"
                                            value="1"
                                            {{ $autorise ? 'checked' : '' }}
                                        >
                                        <span>{{ $jour }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <button type="submit" class="btn btn-primary">
                    💾 Enregistrer mes disponibilités
                </button>
            </form>
        </div>

        {{-- Grille complète en lecture seule pour le membre --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon" style="background:var(--sky-bg);">👀</div>
                    Disponibilités de l'équipe
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="readonly-info" style="margin:16px 20px 0;">
                    <span style="font-size:18px; flex-shrink:0;">ℹ️</span>
                    <span>Vue en lecture seule — vous pouvez consulter les disponibilités de toute l'équipe.</span>
                </div>
                <div style="overflow-x:auto; padding-bottom:4px;">
                    <table class="restrictions-table" style="min-width:700px;">
                        <thead>
                            <tr>
                                <th rowspan="2" style="vertical-align:middle; min-width:170px; text-align:left; padding-left:18px;">
                                    Personne
                                </th>
                                @foreach(['Vendredi', 'Samedi'] as $jour)
                                    <th colspan="{{ $taches->count() }}" class="jour-header">{{ $jour }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach(['Vendredi', 'Samedi'] as $jour)
                                    @foreach($taches as $tache)
                                        <th class="sub-header sub-{{ $tache->code }}">{{ $tache->libelle }}</th>
                                    @endforeach
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($personnes as $personne)
                                <tr class="{{ $personne->id === $user->id ? 'my-row' : '' }}">
                                    <td>
                                        <div style="display:flex; align-items:center; gap:9px;">
                                            <div style="
                                                width:28px; height:28px;
                                                background:linear-gradient(135deg,var(--primary),var(--violet));
                                                border-radius:50%;
                                                display:flex; align-items:center; justify-content:center;
                                                color:white; font-size:11px; font-weight:700; flex-shrink:0;
                                            ">{{ strtoupper(substr($personne->prenom, 0, 1)) }}</div>
                                            {{ $personne->prenom }} {{ $personne->nom }}
                                        </div>
                                    </td>
                                    @foreach(['Vendredi', 'Samedi'] as $jour)
                                        @foreach($taches as $tache)
                                            @php $autorise = $restrictionsMap[$personne->id][$tache->id][$jour] ?? true; @endphp
                                            <td>
                                                <input type="checkbox"
                                                       {{ $autorise ? 'checked' : '' }}
                                                       disabled
                                                       title="{{ $personne->prenom }} — {{ $tache->libelle }} — {{ $jour }}">
                                            </td>
                                        @endforeach
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- VUE ADMIN / GESTIONNAIRE                                             --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}
    @else
        <div class="card">
            <div class="card-body" style="padding:0;">
                <form action="{{ route('restrictions.update') }}" method="POST" id="restrictionsForm">
                    @csrf
                    <div style="overflow-x:auto;">
                        <table class="restrictions-table" style="min-width:700px;">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="vertical-align:middle; min-width:170px; text-align:left; padding-left:18px;">
                                        Personne
                                    </th>
                                    @foreach(['Vendredi', 'Samedi'] as $jour)
                                        <th colspan="{{ $taches->count() }}" class="jour-header">{{ $jour }}</th>
                                    @endforeach
                                </tr>
                                <tr>
                                    @foreach(['Vendredi', 'Samedi'] as $jour)
                                        @foreach($taches as $tache)
                                            <th class="sub-header sub-{{ $tache->code }}">{{ $tache->libelle }}</th>
                                        @endforeach
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($personnes as $personne)
                                    <tr class="{{ $personne->id === $user->id ? 'my-row' : '' }}">
                                        <td>
                                            <div style="display:flex; align-items:center; gap:9px;">
                                                <div style="
                                                    width:28px; height:28px;
                                                    background:linear-gradient(135deg,var(--primary),var(--violet));
                                                    border-radius:50%;
                                                    display:flex; align-items:center; justify-content:center;
                                                    color:white; font-size:11px; font-weight:700; flex-shrink:0;
                                                ">{{ strtoupper(substr($personne->prenom, 0, 1)) }}</div>
                                                {{ $personne->prenom }} {{ $personne->nom }}
                                            </div>
                                        </td>
                                        @foreach(['Vendredi', 'Samedi'] as $jour)
                                            @foreach($taches as $tache)
                                                @php $autorise = $restrictionsMap[$personne->id][$tache->id][$jour] ?? true; @endphp
                                                <td>
                                                    <input
                                                        type="checkbox"
                                                        name="checkboxes[{{ $personne->id }}][{{ $tache->id }}][{{ $jour }}]"
                                                        value="1"
                                                        title="{{ $personne->prenom }} — {{ $tache->libelle }} — {{ $jour }}"
                                                        {{ $autorise ? 'checked' : '' }}
                                                    >
                                                </td>
                                            @endforeach
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="padding:20px 24px;">
                        <div class="toolbar">
                            <button type="submit" class="btn btn-primary">
                                💾 Enregistrer toutes les restrictions
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="toggleAll(true)">
                                Tout cocher
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="toggleAll(false)">
                                Tout décocher
                            </button>
                            <div class="hint-text">
                                <div class="hint-item">
                                    <input type="checkbox" checked disabled style="width:14px;height:14px;accent-color:var(--primary);">
                                    <span>Disponible</span>
                                </div>
                                <div class="hint-item">
                                    <input type="checkbox" disabled style="width:14px;height:14px;">
                                    <span>Indisponible</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

@endsection

@push('scripts')
    <script>
    // Uniquement disponible pour l'admin et le gestionnaire
    function toggleAll(state) {
        document.querySelectorAll('#restrictionsForm input[type="checkbox"]')
            .forEach(cb => cb.checked = state);
    }
    </script>
@endpush
