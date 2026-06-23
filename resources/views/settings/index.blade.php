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

    .offsets-table tbody tr:last-child td { border-bottom: none; }
    .offsets-table tbody tr:hover { background: var(--surface-2); }
    .offsets-table td.col-num { text-align: center; }

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

    /* ── Toggle switch for boolean ── */
    .toggle-wrap {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .toggle-switch {
        position: relative;
        width: 48px;
        height: 26px;
        flex-shrink: 0;
    }

    .toggle-switch input[type="checkbox"] {
        opacity: 0;
        width: 0;
        height: 0;
        position: absolute;
    }

    .toggle-slider {
        position: absolute;
        inset: 0;
        background: var(--ink-faint);
        border-radius: 26px;
        cursor: pointer;
        transition: var(--transition);
    }

    .toggle-slider::before {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        left: 3px;
        top: 3px;
        background: white;
        border-radius: 50%;
        transition: var(--transition);
        box-shadow: 0 1px 4px rgba(0,0,0,0.2);
    }

    .toggle-switch input:checked + .toggle-slider {
        background: var(--emerald);
    }

    .toggle-switch input:checked + .toggle-slider::before {
        transform: translateX(22px);
    }

    /* Disabled state for non-admin */
    .toggle-switch input:disabled + .toggle-slider {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .toggle-label {
        font-size: 13.5px;
        color: var(--ink-light);
        font-weight: 500;
    }

    /* ── Calendriers grid ── */
    .calendriers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 14px;
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

    /* ── Warning note ── */
    .warn-note {
        background: var(--rose-bg);
        border: 1px solid var(--rose-border);
        border-radius: var(--radius);
        padding: 11px 15px;
        font-size: 12.5px;
        color: #9f1239;
        display: flex;
        align-items: flex-start;
        gap: 9px;
        margin-bottom: 0;
        margin-top: 14px;
    }

    /* ── Admin-only badge ── */
    .admin-only-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        background: var(--rose-bg);
        color: #9f1239;
        border: 1px solid var(--rose-border);
        margin-left: 8px;
        vertical-align: middle;
    }

    @media (max-width: 768px) {
        .horaires-grid { grid-template-columns: 1fr; }
        .offset-input  { width: 75px; }
        .calendriers-grid { grid-template-columns: 1fr; }
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
            SECTION 1 — Inscription publique (admin uniquement)
        ══════════════════════════════════════════════════════════════ --}}
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon" style="background:var(--emerald-bg);">🔓</div>
                    Inscription publique
                    @if(!$user->isAdmin())
                        <span class="admin-only-badge">🛡️ Admin uniquement</span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="settings-section-sub">
                    Contrôle l'accès au formulaire d'inscription public (<code>/inscription</code>).
                    Fermer les inscriptions bloque l'affichage du formulaire et la soumission.
                    @if(!$user->isAdmin())
                        <strong style="color:var(--rose);">Seuls les administrateurs peuvent modifier ce paramètre.</strong>
                    @endif
                </div>

                @if(isset($inscription['inscription_ouverte']))
                    @php $io = $inscription['inscription_ouverte']; @endphp

                    @if($user->isAdmin())
                        {{--
                            Hidden input ensures value "0" is submitted when the checkbox is unchecked.
                            When checked, the checkbox value "1" overrides this hidden input because it
                            appears later in the DOM (PHP takes the last value for duplicate keys in
                            regular POST, but Laravel's request->input() takes the last occurrence).
                            We use a separate name trick: the hidden sends 0, the checkbox sends 1.
                        --}}
                        <input type="hidden" name="settings[inscription_ouverte]" value="0">

                        <div class="toggle-wrap">
                            <label class="toggle-switch">
                                <input
                                    type="checkbox"
                                    name="settings[inscription_ouverte]"
                                    value="1"
                                    id="inscriptionToggle"
                                    {{ $io['valeur'] ? 'checked' : '' }}
                                    onchange="updateInscriptionStatus(this)">
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="toggle-label" id="inscriptionLabel">
                                {{ $io['valeur'] ? '✅ Inscriptions ouvertes' : '🔒 Inscriptions fermées' }}
                            </span>
                        </div>

                        @if(!$io['valeur'])
                            <div class="warn-note">
                                <span style="flex-shrink:0;">⚠️</span>
                                <span>Les inscriptions sont actuellement <strong>fermées</strong>. Le formulaire public est inaccessible.</span>
                            </div>
                        @endif

                    @else
                        {{-- Gestionnaire : affichage lecture seule --}}
                        <div class="toggle-wrap" style="opacity:0.6;">
                            <label class="toggle-switch">
                                <input
                                    type="checkbox"
                                    {{ $io['valeur'] ? 'checked' : '' }}
                                    disabled>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="toggle-label">
                                {{ $io['valeur'] ? '✅ Inscriptions ouvertes' : '🔒 Inscriptions fermées' }}
                            </span>
                        </div>
                        <p style="font-size:12px;color:var(--ink-muted);margin-top:10px;">
                            Connectez-vous en tant qu'administrateur pour modifier ce paramètre.
                        </p>
                    @endif

                @else
                    {{-- Paramètre absent de la base (ancien déploiement avant migration) --}}
                    <div class="info-note" style="margin-bottom:0;">
                        <span style="flex-shrink:0;">⚠️</span>
                        <span>
                            Le paramètre <code>inscription_ouverte</code> n'existe pas encore en base.
                            Lancez <code>php artisan migrate</code> ou <code>php artisan db:seed</code> pour l'ajouter.
                        </span>
                    </div>
                @endif
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════
            SECTION 2 — Horaires & Lieu
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
            SECTION 3 — Calendriers Google Calendar
        ══════════════════════════════════════════════════════════════ --}}
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon" style="background:var(--violet-bg);">📆</div>
                    Calendriers Google Calendar
                </div>
            </div>
            <div class="card-body">
                <div class="settings-section-sub">
                    Nom du calendrier Google Calendar dans lequel chaque type d'événement sera créé.
                    Laissez vide pour utiliser le calendrier par défaut configuré dans Make.com.
                </div>

                @php
                    $calendarChips = [
                        'calendar_entree'                => ['libelle' => 'Entrée',                 'chip' => 'entree'],
                        'calendar_mektaba'               => ['libelle' => 'Mektaba',                'chip' => 'mektaba'],
                        'calendar_salle'                 => ['libelle' => 'Salle',                  'chip' => 'salle'],
                        'calendar_amana_food'            => ['libelle' => 'Amana Food',             'chip' => 'amana_food'],
                        'calendar_cours'                 => ['libelle' => 'Cours',                  'chip' => 'cours'],
                        'calendar_rappel_sandwich'       => ['libelle' => 'Rappel Sandwich',        'chip' => 'rappel_sandwich'],
                        'calendar_assistance_amana_food' => ['libelle' => 'Assistance Amana Food',  'chip' => 'assistance_amana_food'],
                        'calendar_annonce_cours'         => ['libelle' => 'Annonce Cours',          'chip' => 'annonce_cours'],
                        'calendar_message_bot'           => ['libelle' => 'Message Bot',            'chip' => 'message_bot'],
                    ];
                @endphp

                <div class="calendriers-grid">
                    @foreach($calendarChips as $cle => $meta)
                        @if(isset($calendriers[$cle]))
                            @php $cal = $calendriers[$cle]; @endphp
                            <div class="form-group">
                                <label for="{{ $cle }}">
                                    <span class="tache-chip chip-{{ $meta['chip'] }}" style="font-size:11px;padding:1px 8px;">
                                        {{ $meta['libelle'] }}
                                    </span>
                                </label>
                                <input
                                    type="text"
                                    id="{{ $cle }}"
                                    name="settings[{{ $cle }}]"
                                    value="{{ $cal['valeur_raw'] }}"
                                    maxlength="200"
                                    placeholder="Nom du calendrier…"
                                    style="margin-top:6px;">
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════
            SECTION 4 — Décalages des tâches
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

                                    <td>
                                        @if($codeTache === 'rappel_sandwich')
                                            <span style="font-size:12.5px;color:var(--ink-muted);font-weight:600;">
                                                08:00 → 08:15
                                            </span>
                                        @else
                                            @php
                                                $heureCours = $horaires['heure_cours']['valeur_raw'] ?? '20:00';
                                                [$h, $m] = explode(':', $heureCours);
                                                $baseMin  = (int)$h * 60 + (int)$m;
                                                $debutMin = $baseMin + (int)($groupe['debut']['valeur_raw'] ?? 0);
                                                $finMin   = $baseMin + (int)($groupe['fin']['valeur_raw'] ?? 60);
                                                $debutH   = intdiv((($debutMin % 1440) + 1440) % 1440, 60);
                                                $debutM   = ((($debutMin % 1440) + 1440) % 1440) % 60;
                                                $finH     = intdiv((($finMin % 1440) + 1440) % 1440, 60);
                                                $finM     = ((($finMin % 1440) + 1440) % 1440) % 60;
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
function updateInscriptionStatus(checkbox) {
    const label = document.getElementById('inscriptionLabel');
    if (label) {
        label.textContent = checkbox.checked
            ? '✅ Inscriptions ouvertes'
            : '🔒 Inscriptions fermées';
    }
}

(function () {
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
            const debutEl   = document.querySelector('[name="' + debutName + '"]');
            const finEl     = document.querySelector('[name="' + finName + '"]');
            if (!debutEl || !finEl) return;
            const offsetDebut = parseInt(debutEl.value, 10) || 0;
            const offsetFin   = parseInt(finEl.value,   10) || 0;
            span.textContent  = addMinutes(heureCours, offsetDebut) + ' → ' + addMinutes(heureCours, offsetFin);
        });
    }

    document.getElementById('settingsForm').addEventListener('input', updatePreviews);
})();
</script>
@endpush