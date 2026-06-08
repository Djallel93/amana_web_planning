{{-- resources/views/settings/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Paramètres — AMANA')

@push('styles')
<style>
    .settings-section-title {
        font-family: var(--font-heading);
        font-size: 15px;
        font-weight: 600;
        color: var(--ink);
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 9px;
    }

    .settings-section-sub {
        font-size: 12.5px;
        color: var(--ink-muted);
        margin-bottom: 20px;
    }

    /* ── Tableau décalages ── */
    .offsets-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13.5px;
    }

    .offsets-table thead th {
        padding: 9px 14px;
        text-align: left;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        color: var(--ink-muted);
        background: var(--surface-2);
        border-bottom: 1px solid var(--surface-3);
        white-space: nowrap;
        font-family: var(--font-body);
    }

    .offsets-table thead th.col-num {
        text-align: center;
        width: 140px;
    }

    .offsets-table tbody td {
        padding: 11px 14px;
        border-bottom: 1px solid var(--surface-3);
        vertical-align: middle;
    }

    .offsets-table tbody tr:last-child td {
        border-bottom: none;
    }

    .offsets-table tbody tr:hover {
        background: var(--surface-2);
    }

    .offsets-table td.col-num {
        text-align: center;
    }

    .offset-input {
        width: 90px;
        padding: 7px 11px;
        border: 1.5px solid var(--ink-faint);
        border-radius: var(--radius);
        font-size: 13px;
        font-family: var(--font-body);
        color: var(--ink);
        background: var(--surface);
        text-align: center;
        transition: var(--transition);
        outline: none;
        -webkit-appearance: none;
        appearance: none;
    }

    .offset-input:focus {
        border-color: var(--app-accent);
        box-shadow: var(--shadow-glow);
    }

    .offset-unit {
        font-size: 11px;
        color: var(--ink-muted);
        margin-top: 3px;
    }

    /* ── Chip tâche ── */
    .tache-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 11px;
        border-radius: 20px;
        font-size: 12.5px;
        font-weight: 600;
    }

    .chip-entree                { background: #eff6ff; color: #2563eb; }
    .chip-mektaba               { background: #ecfdf5; color: #059669; }
    .chip-salle                 { background: #fffbeb; color: #d97706; }
    .chip-amana_food            { background: #fff1f2; color: #e11d48; }
    .chip-cours                 { background: #f5f3ff; color: #7c3aed; }
    .chip-rappel_sandwich       { background: var(--sky-bg);    color: #0369a1; }
    .chip-assistance_amana_food { background: var(--emerald-bg);color: #065f46; }
    .chip-annonce_cours         { background: var(--amber-bg);  color: #92400e; }
    .chip-message_bot           { background: var(--surface-3); color: var(--ink-muted); }

    /* ── Horaires form ── */
    .horaires-grid {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 18px;
    }

    /* ── Info note ── */
    .info-note {
        background: var(--sky-bg);
        border: 1px solid var(--sky-border);
        border-radius: var(--radius);
        padding: 11px 15px;
        font-size: 12.5px;
        color: #0c4a6e;
        display: flex;
        align-items: flex-start;
        gap: 9px;
        margin-bottom: 22px;
    }

    @media (max-width: 768px) {
        .horaires-grid {
            grid-template-columns: 1fr;
        }
        .offset-input {
            width: 75px;
        }
    }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">⚙️ Paramètres</div>
            <div class="page-subtitle">Configuration de l'application AMANA Planning</div>
        </div>
    </div>

    <form action="{{ route('settings.update') }}" method="POST" id="settingsForm">
        @csrf

        {{-- ══════════════════════════════════════════════════════════════
             SECTION 1 — Horaires
        ══════════════════════════════════════════════════════════════ --}}
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon" style="background:var(--sky-bg);">🕐</div>
                    Horaires &amp; Lieu
                </div>
            </div>
            <div class="card-body">
                <div class="settings-section-sub">
                    Heure du cours et adresse physique des permanences.
                    Tous les horaires des événements sont calculés relativement à l'heure du cours.
                </div>

                <div class="horaires-grid">
                    {{-- Heure du cours --}}
                    @if(isset($horaires['heure_cours']))
                        @php $hc = $horaires['heure_cours']; @endphp
                        <div class="form-group">
                            <label for="heure_cours">{{ $hc['libelle'] }} <span class="req">*</span></label>
                            <input
                                type="time"
                                id="heure_cours"
                                name="settings[heure_cours]"
                                value="{{ $hc['valeur_raw'] }}"
                                required
                                style="max-width:160px;">
                            <span class="form-hint">Format 24h — ex : 20:00</span>
                        </div>
                    @endif

                    {{-- Lieu --}}
                    @if(isset($horaires['lieu']))
                        @php $lieu = $horaires['lieu']; @endphp
                        <div class="form-group">
                            <label for="lieu">{{ $lieu['libelle'] }} <span class="req">*</span></label>
                            <input
                                type="text"
                                id="lieu"
                                name="settings[lieu]"
                                value="{{ $lieu['valeur_raw'] }}"
                                maxlength="500"
                                placeholder="Adresse complète">
                            <span class="form-hint">Adresse envoyée dans les événements Google Calendar</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════
             SECTION 2 — Décalages des tâches
        ══════════════════════════════════════════════════════════════ --}}
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon" style="background:var(--amber-bg);">⏱️</div>
                    Décalages des tâches
                </div>
            </div>
            <div class="card-body" style="padding:0;">

                <div class="info-note" style="margin:16px 22px 4px;margin-bottom:0;">
                    <span style="flex-shrink:0;">ℹ️</span>
                    <span>
                        Les décalages sont en <strong>minutes par rapport à l'heure du cours</strong>.
                        Une valeur négative signifie avant le cours (ex : −30 = 30 min avant),
                        positive = après. Le rappel sandwich a un horaire fixe (08:00–08:15) indépendant.
                    </span>
                </div>

                <div class="table-wrap">
                    <table class="offsets-table">
                        <thead>
                            <tr>
                                <th>Tâche / Événement</th>
                                <th class="col-num">Début (min)</th>
                                <th class="col-num">Fin (min)</th>
                                <th style="width:180px;">Horaire calculé</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($decalagesGroupes as $codeTache => $groupe)
                                <tr>
                                    <td>
                                        <span class="tache-chip chip-{{ $codeTache }}">
                                            {{ $groupe['libelle'] }}
                                        </span>
                                        @if($codeTache === 'rappel_sandwich')
                                            <div style="font-size:11px;color:var(--ink-muted);margin-top:4px;">
                                                Horaire fixe — valeurs ignorées
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Début --}}
                                    <td class="col-num">
                                        @if($groupe['debut'])
                                            @php $d = $groupe['debut']; @endphp
                                            <input
                                                type="number"
                                                class="offset-input"
                                                name="settings[{{ $d['cle'] }}]"
                                                value="{{ $d['valeur_raw'] }}"
                                                step="1"
                                                min="-999"
                                                max="999"
                                                {{ $codeTache === 'rappel_sandwich' ? 'disabled' : '' }}>
                                            @if($codeTache !== 'rappel_sandwich')
                                                <div class="offset-unit">min</div>
                                            @endif
                                        @else
                                            <span style="color:var(--ink-faint);">—</span>
                                        @endif
                                    </td>

                                    {{-- Fin --}}
                                    <td class="col-num">
                                        @if($groupe['fin'])
                                            @php $f = $groupe['fin']; @endphp
                                            <input
                                                type="number"
                                                class="offset-input"
                                                name="settings[{{ $f['cle'] }}]"
                                                value="{{ $f['valeur_raw'] }}"
                                                step="1"
                                                min="-999"
                                                max="999"
                                                {{ $codeTache === 'rappel_sandwich' ? 'disabled' : '' }}>
                                            @if($codeTache !== 'rappel_sandwich')
                                                <div class="offset-unit">min</div>
                                            @endif
                                        @else
                                            <span style="color:var(--ink-faint);">—</span>
                                        @endif
                                    </td>

                                    {{-- Horaire calculé (aperçu dynamique) --}}
                                    <td>
                                        @if($codeTache === 'rappel_sandwich')
                                            <span style="font-size:12.5px;color:var(--ink-muted);font-weight:600;">
                                                08:00 → 08:15
                                            </span>
                                        @else
                                            @php
                                                $heureCours = $horaires['heure_cours']['valeur_raw'] ?? '20:00';
                                                [$h, $m] = explode(':', $heureCours);
                                                $baseMin = (int)$h * 60 + (int)$m;
                                                $debutMin = $baseMin + (int)($groupe['debut']['valeur_raw'] ?? 0);
                                                $finMin   = $baseMin + (int)($groupe['fin']['valeur_raw'] ?? 60);
                                                $fmt = fn(int $mins) => sprintf('%02d:%02d', intdiv(abs($mins), 60) + ($mins < 0 ? -1 : 0), abs($mins) % 60);
                                                // Calcul propre
                                                $debutH = intdiv((($debutMin % 1440) + 1440) % 1440, 60);
                                                $debutM = ((($debutMin % 1440) + 1440) % 1440) % 60;
                                                $finH   = intdiv((($finMin % 1440) + 1440) % 1440, 60);
                                                $finM   = ((($finMin % 1440) + 1440) % 1440) % 60;
                                            @endphp
                                            <span
                                                class="horaire-preview"
                                                style="font-size:12.5px;color:var(--app-accent);font-weight:600;"
                                                data-base="{{ $heureCours }}"
                                                data-debut-input="settings[{{ $groupe['debut']['cle'] ?? '' }}]"
                                                data-fin-input="settings[{{ $groupe['fin']['cle'] ?? '' }}]">
                                                {{ sprintf('%02d:%02d', $debutH, $debutM) }}
                                                →
                                                {{ sprintf('%02d:%02d', $finH, $finM) }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Boutons de soumission --}}
        <div style="display:flex;gap:11px;align-items:center;">
            <button type="submit" class="btn btn-primary btn-lg">
                💾 Enregistrer les paramètres
            </button>
            <a href="{{ route('planning.index') }}" class="btn btn-secondary btn-lg">
                Annuler
            </a>
        </div>

    </form>
@endsection

@push('scripts')
<script>
/**
 * Mise à jour dynamique de l'aperçu "Horaire calculé"
 * quand l'utilisateur modifie heure_cours ou un offset.
 */
(function () {
    // Convertit "HH:MM" + offset minutes en "HH:MM"
    function addMinutes(hhmm, minutes) {
        const [h, m] = hhmm.split(':').map(Number);
        const total = ((h * 60 + m + minutes) % 1440 + 1440) % 1440;
        return String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
    }

    function updatePreviews() {
        const heureCoursInput = document.getElementById('heure_cours');
        const heureCours = heureCoursInput ? heureCoursInput.value : '20:00';

        document.querySelectorAll('.horaire-preview').forEach(function (span) {
            const debutName = span.dataset.debutInput;
            const finName   = span.dataset.finInput;

            const debutEl = document.querySelector('[name="' + debutName + '"]');
            const finEl   = document.querySelector('[name="' + finName + '"]');

            if (!debutEl || !finEl) return;

            const offsetDebut = parseInt(debutEl.value, 10) || 0;
            const offsetFin   = parseInt(finEl.value,   10) || 0;

            span.textContent = addMinutes(heureCours, offsetDebut)
                + ' → '
                + addMinutes(heureCours, offsetFin);
        });
    }

    // Écouter tous les inputs concernés
    document.getElementById('settingsForm').addEventListener('input', updatePreviews);
})();
</script>
@endpush