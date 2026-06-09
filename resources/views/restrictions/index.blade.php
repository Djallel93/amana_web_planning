{{-- resources/views/restrictions/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Disponibilités — AMANA')

@push('styles')
    <style>
        .restrictions-table th.jour-header {
            text-align: center;
            background: var(--app-accent);
            color: white;
            font-size: 12px;
            text-transform: none;
            letter-spacing: 0;
            font-weight: 600;
            padding: 10px 8px;
        }

        /* Left border separator between Vendredi and Samedi groups */
        .restrictions-table .jour-separator {
            border-left: 2px solid var(--app-accent) !important;
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
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--app-accent);
            -webkit-appearance: auto;
            appearance: auto;
        }

        .restrictions-table input[type="checkbox"]:disabled {
            cursor: not-allowed;
            opacity: 0.45;
        }

        .sub-entree      { color: #2563eb; }
        .sub-mektaba     { color: #059669; }
        .sub-salle       { color: #d97706; }
        .sub-amana_food  { color: #e11d48; }

        .my-row { background: var(--sky-bg) !important; }
        .my-row:hover { background: #e0f2fe !important; }
        .my-row td:first-child::after {
            content: ' (moi)';
            font-size: 11px;
            font-weight: 400;
            color: var(--app-accent);
            margin-left: 5px;
        }

        .toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid var(--surface-3);
            flex-wrap: wrap;
        }

        .hint-text {
            margin-left: auto;
            font-size: 12px;
            color: var(--ink-muted);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .hint-item { display: flex; align-items: center; gap: 5px; }

        .member-section {
            background: var(--sky-bg);
            border: 1.5px solid var(--sky-border);
            border-radius: var(--radius-lg);
            padding: 20px 22px;
            margin-bottom: 22px;
        }

        .member-section-title {
            font-family: var(--font-heading);
            font-size: 15px;
            font-weight: 600;
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
            line-height: 1.55;
        }

        .member-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .member-tache-card {
            background: var(--surface);
            border: 1.5px solid var(--surface-border);
            border-radius: var(--radius);
            padding: 13px 15px;
            transition: var(--transition);
        }

        .member-tache-card:hover { border-color: var(--app-accent); }

        .member-tache-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .member-tache-checks { display: flex; flex-direction: column; gap: 8px; }

        .member-check-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--ink-light);
            cursor: pointer;
        }

        .member-check-item input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: var(--app-accent);
            cursor: pointer;
            flex-shrink: 0;
            -webkit-appearance: auto;
            appearance: auto;
        }

        .tache-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .chip-entree     { background: #eff6ff; color: #2563eb; }
        .chip-mektaba    { background: #ecfdf5; color: #059669; }
        .chip-salle      { background: #fffbeb; color: #d97706; }
        .chip-amana_food { background: #fff1f2; color: #e11d48; }
    </style>
@endpush

@section('content')
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">
                {{ ($user->isAdmin() || $user->isGestionnaire()) ? 'Restrictions de disponibilité' : 'Mes disponibilités' }}
            </div>
            <div class="page-subtitle">Case cochée ✓ = la personne peut effectuer la tâche ce jour-là</div>
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

    @elseif(!$user->isAdmin() && !$user->isGestionnaire())

        {{-- Member: personal edit form --}}
        <div class="member-section">
            <div class="member-section-title">🔧 Modifier mes disponibilités</div>
            <div class="member-section-sub">
                Cochez les tâches que vous <strong>pouvez effectuer</strong> chaque jour.
                Ces informations sont prises en compte lors de la génération du planning.
            </div>

            <form action="{{ route('restrictions.update') }}" method="POST" id="memberForm">
                @csrf
                <div class="member-grid">
                    @foreach($taches as $tache)
                        <div class="member-tache-card">
                            <div class="member-tache-title">
                                <span class="tache-chip chip-{{ $tache->code }}">{{ $tache->libelle }}</span>
                            </div>
                            <div class="member-tache-checks">
                                @foreach(['Vendredi', 'Samedi'] as $jour)
                                    @php $autorise = $restrictionsMap[$user->id][$tache->id][$jour] ?? true; @endphp
                                    <label class="member-check-item">
                                        <input type="checkbox"
                                            name="checkboxes[{{ $user->id }}][{{ $tache->id }}][{{ $jour }}]"
                                            value="1" {{ $autorise ? 'checked' : '' }}>
                                        <span>{{ $jour }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <button type="submit" class="btn btn-primary">💾 Enregistrer mes disponibilités</button>
            </form>
        </div>

        {{-- Member: read-only full grid --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon" style="background:var(--sky-bg);">👀</div>
                    Disponibilités de l'équipe
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div style="background:var(--sky-bg);border-bottom:1px solid var(--sky-border);padding:11px 20px;font-size:12.5px;color:#0c4a6e;display:flex;align-items:center;gap:8px;">
                    <span>ℹ️</span>
                    <span>Vue en lecture seule — vous pouvez consulter les disponibilités de toute l'équipe.</span>
                </div>
                <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
                    <table class="restrictions-table" style="min-width:680px;">
                        <thead>
                            <tr>
                                <th rowspan="2" style="vertical-align:middle;min-width:160px;text-align:left;padding-left:18px;background:var(--surface-2);">Personne</th>
                                @foreach(['Vendredi', 'Samedi'] as $jour)
                                    <th colspan="{{ $taches->count() }}" class="jour-header{{ $loop->last ? ' jour-separator' : '' }}">{{ $jour }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach(['Vendredi', 'Samedi'] as $jour)
                                    @foreach($taches as $tache)
                                        <th class="sub-header sub-{{ $tache->code }}{{ ($jour === 'Samedi' && $loop->first) ? ' jour-separator' : '' }}">{{ $tache->libelle }}</th>
                                    @endforeach
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($personnes as $personne)
                                <tr class="{{ $personne->id === $user->id ? 'my-row' : '' }}">
                                    <td>
                                        <div style="display:flex;align-items:center;gap:9px;">
                                            <div style="width:26px;height:26px;background:var(--app-accent);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:10px;font-weight:700;flex-shrink:0;">
                                                {{ strtoupper(substr($personne->prenom, 0, 1)) }}
                                            </div>
                                            {{ $personne->prenom }} {{ $personne->nom }}
                                        </div>
                                    </td>
                                    @foreach(['Vendredi', 'Samedi'] as $jour)
                                        @foreach($taches as $tache)
                                            @php $autorise = $restrictionsMap[$personne->id][$tache->id][$jour] ?? true; @endphp
                                            <td class="{{ ($jour === 'Samedi' && $loop->first) ? 'jour-separator' : '' }}">
                                                <input type="checkbox" {{ $autorise ? 'checked' : '' }} disabled
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

    @else
        {{-- Admin / Gestionnaire: editable grid --}}
        <div class="card">
            <div class="card-body" style="padding:0;">
                <form action="{{ route('restrictions.update') }}" method="POST" id="restrictionsForm">
                    @csrf
                    <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
                        <table class="restrictions-table" style="min-width:680px;">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="vertical-align:middle;min-width:160px;text-align:left;padding-left:18px;">Personne</th>
                                    @foreach(['Vendredi', 'Samedi'] as $jour)
                                        <th colspan="{{ $taches->count() }}" class="jour-header{{ $loop->last ? ' jour-separator' : '' }}">{{ $jour }}</th>
                                    @endforeach
                                </tr>
                                <tr>
                                    @foreach(['Vendredi', 'Samedi'] as $jour)
                                        @foreach($taches as $tache)
                                            <th class="sub-header sub-{{ $tache->code }}{{ ($jour === 'Samedi' && $loop->first) ? ' jour-separator' : '' }}">{{ $tache->libelle }}</th>
                                        @endforeach
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($personnes as $personne)
                                    <tr class="{{ $personne->id === $user->id ? 'my-row' : '' }}">
                                        <td>
                                            <div style="display:flex;align-items:center;gap:9px;">
                                                <div style="width:26px;height:26px;background:var(--app-accent);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:10px;font-weight:700;flex-shrink:0;">
                                                    {{ strtoupper(substr($personne->prenom, 0, 1)) }}
                                                </div>
                                                {{ $personne->prenom }} {{ $personne->nom }}
                                            </div>
                                        </td>
                                        @foreach(['Vendredi', 'Samedi'] as $jour)
                                            @foreach($taches as $tache)
                                                @php $autorise = $restrictionsMap[$personne->id][$tache->id][$jour] ?? true; @endphp
                                                <td class="{{ ($jour === 'Samedi' && $loop->first) ? 'jour-separator' : '' }}">
                                                    <input type="checkbox"
                                                        name="checkboxes[{{ $personne->id }}][{{ $tache->id }}][{{ $jour }}]"
                                                        value="1"
                                                        title="{{ $personne->prenom }} — {{ $tache->libelle }} — {{ $jour }}"
                                                        {{ $autorise ? 'checked' : '' }}>
                                                </td>
                                            @endforeach
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="padding:18px 22px;">
                        <div class="toolbar">
                            <button type="submit" class="btn btn-primary">💾 Enregistrer toutes les restrictions</button>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="toggleAll(true)">Tout cocher</button>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="toggleAll(false)">Tout décocher</button>
                            <div class="hint-text">
                                <div class="hint-item">
                                    <input type="checkbox" checked disabled style="width:13px;height:13px;accent-color:var(--app-accent);-webkit-appearance:auto;appearance:auto;">
                                    <span>Disponible</span>
                                </div>
                                <div class="hint-item">
                                    <input type="checkbox" disabled style="width:13px;height:13px;-webkit-appearance:auto;appearance:auto;">
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
        function toggleAll(state) {
            document.querySelectorAll('#restrictionsForm input[type="checkbox"]')
                .forEach(cb => cb.checked = state);
        }
    </script>
@endpush