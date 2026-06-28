{{-- resources/views/settings/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Paramètres — AMANA')

@section('content')

{{-- En-tête page --}}
<div class="flex flex-wrap items-start justify-between gap-4 mb-7">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">⚙️ Paramètres</h1>
        <p class="text-[13px] text-ink-muted mt-1">Configuration de l'application AMANA Planning</p>
    </div>
</div>

<form action="{{ route('settings.update') }}" method="POST" id="settingsForm">
    @csrf

    {{-- ═══════════════════════════════════════
        SECTION 1 — Inscription publique
    ════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden mb-5">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-emerald-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">🔓</div>
            <span class="font-heading text-[14px] font-semibold text-ink">Inscription publique</span>
            @if(!$user->isAdmin())
                <span class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                    🛡️ Admin uniquement
                </span>
            @endif
        </div>
        <div class="px-5 py-5">
            <p class="text-[12.5px] text-ink-muted mb-4 leading-relaxed">
                Contrôle l'accès au formulaire d'inscription public (<code class="bg-surface-3 px-1 py-0.5 rounded text-[11.5px]">/inscription</code>).
                Fermer les inscriptions bloque l'affichage du formulaire et la soumission.
                @if(!$user->isAdmin())
                    <strong class="text-rose-600"> Seuls les administrateurs peuvent modifier ce paramètre.</strong>
                @endif
            </p>

            @if(isset($inscription['inscription_ouverte']))
                @php $io = $inscription['inscription_ouverte']; @endphp

                @if($user->isAdmin())
                    <input type="hidden" name="settings[inscription_ouverte]" value="0">

                    <div class="flex items-center gap-4">
                        {{-- Toggle switch en Tailwind pur --}}
                        <label class="relative inline-flex items-center cursor-pointer min-h-[44px]">
                            <input type="checkbox"
                                   name="settings[inscription_ouverte]"
                                   value="1"
                                   id="inscriptionToggle"
                                   {{ $io['valeur'] ? 'checked' : '' }}
                                   onchange="updateInscriptionStatus(this)"
                                   class="sr-only peer">
                            <div class="w-12 h-6 bg-ink-faint peer-checked:bg-emerald-500 rounded-full relative transition-colors duration-200
                                        after:content-[''] after:absolute after:top-[3px] after:left-[3px]
                                        after:bg-white after:rounded-full after:w-[18px] after:h-[18px]
                                        after:transition-all after:duration-200 after:shadow
                                        peer-checked:after:translate-x-6">
                            </div>
                        </label>
                        <span id="inscriptionLabel" class="text-[13.5px] text-ink-light font-medium">
                            {{ $io['valeur'] ? '✅ Inscriptions ouvertes' : '🔒 Inscriptions fermées' }}
                        </span>
                    </div>

                    @if(!$io['valeur'])
                        <div class="flex items-start gap-2 mt-4 px-4 py-3 bg-rose-50 border border-rose-200 rounded-lg text-[12.5px] text-rose-800">
                            <span class="flex-shrink-0">⚠️</span>
                            <span>Les inscriptions sont actuellement <strong>fermées</strong>. Le formulaire public est inaccessible.</span>
                        </div>
                    @endif

                @else
                    <div class="flex items-center gap-4 opacity-60">
                        <div class="w-12 h-6 {{ $io['valeur'] ? 'bg-emerald-500' : 'bg-ink-faint' }} rounded-full relative
                                    after:content-[''] after:absolute after:top-[3px] after:{{ $io['valeur'] ? 'left-[27px]' : 'left-[3px]' }}
                                    after:bg-white after:rounded-full after:w-[18px] after:h-[18px] after:shadow">
                        </div>
                        <span class="text-[13.5px] text-ink-light font-medium">
                            {{ $io['valeur'] ? '✅ Inscriptions ouvertes' : '🔒 Inscriptions fermées' }}
                        </span>
                    </div>
                    <p class="text-xs text-ink-muted mt-3">Connectez-vous en tant qu'administrateur pour modifier ce paramètre.</p>
                @endif

            @else
                <div class="flex items-start gap-2 px-4 py-3 bg-sky-50 border border-sky-200 rounded-lg text-[12.5px] text-sky-900">
                    <span class="flex-shrink-0">⚠️</span>
                    <span>
                        Le paramètre <code>inscription_ouverte</code> n'existe pas encore en base.
                        Lancez <code>php artisan migrate</code> ou <code>php artisan db:seed</code> pour l'ajouter.
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════
        SECTION 2 — Horaires & Lieu
    ════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden mb-5">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">🕐</div>
            <span class="font-heading text-[14px] font-semibold text-ink">Horaires &amp; Lieu</span>
        </div>
        <div class="px-5 py-5">
            <p class="text-[12.5px] text-ink-muted mb-5 leading-relaxed">
                Heure du cours et adresse physique des permanences.
                Tous les horaires des événements sont calculés relativement à l'heure du cours.
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-[1fr_2fr] gap-5">

                @if(isset($horaires['heure_cours']))
                    @php $hc = $horaires['heure_cours']; @endphp
                    <div class="flex flex-col gap-1.5">
                        <label for="heure_cours" class="text-xs font-bold text-ink tracking-[0.2px]">
                            {{ $hc['libelle'] }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="time"
                               id="heure_cours"
                               name="settings[heure_cours]"
                               value="{{ $hc['valeur_raw'] }}"
                               required
                               class="w-full max-w-[160px] px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                      focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] hover:border-ink-muted">
                        <span class="text-[11.5px] text-ink-muted">Format 24h — ex : 20:00</span>
                    </div>
                @endif

                @if(isset($horaires['lieu']))
                    @php $lieu = $horaires['lieu']; @endphp
                    <div class="flex flex-col gap-1.5">
                        <label for="lieu" class="text-xs font-bold text-ink tracking-[0.2px]">
                            {{ $lieu['libelle'] }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                               id="lieu"
                               name="settings[lieu]"
                               value="{{ $lieu['valeur_raw'] }}"
                               maxlength="500"
                               placeholder="Adresse complète"
                               class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                      focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] hover:border-ink-muted">
                        <span class="text-[11.5px] text-ink-muted">Adresse envoyée dans les événements Google Calendar</span>
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════
        SECTION 3 — Calendriers Google Calendar
    ════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden mb-5">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-violet-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">📆</div>
            <span class="font-heading text-[14px] font-semibold text-ink">Calendriers Google Calendar</span>
        </div>
        <div class="px-5 py-5">
            <p class="text-[12.5px] text-ink-muted mb-5 leading-relaxed">
                Nom du calendrier Google Calendar dans lequel chaque type d'événement sera créé.
                Laissez vide pour utiliser le calendrier par défaut configuré dans Make.com.
            </p>

            @php
                $calendarChips = [
                    'calendar_entree'                => ['libelle' => 'Entrée',               'chip' => 'entree'],
                    'calendar_mektaba'               => ['libelle' => 'Mektaba',              'chip' => 'mektaba'],
                    'calendar_salle'                 => ['libelle' => 'Salle',                'chip' => 'salle'],
                    'calendar_amana_food'            => ['libelle' => 'Amana Food',           'chip' => 'amana_food'],
                    'calendar_cours'                 => ['libelle' => 'Cours',                'chip' => 'cours'],
                    'calendar_rappel_sandwich'       => ['libelle' => 'Rappel Sandwich',      'chip' => 'rappel_sandwich'],
                    'calendar_assistance_amana_food' => ['libelle' => 'Assistance Amana Food','chip' => 'assistance_amana_food'],
                    'calendar_annonce_cours'         => ['libelle' => 'Annonce Cours',        'chip' => 'annonce_cours'],
                    'calendar_message_bot'           => ['libelle' => 'Message Bot',          'chip' => 'message_bot'],
                ];
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($calendarChips as $cle => $meta)
                    @if(isset($calendriers[$cle]))
                        @php $cal = $calendriers[$cle]; @endphp
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold text-ink tracking-[0.2px]">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold chip-{{ $meta['chip'] }}">
                                    {{ $meta['libelle'] }}
                                </span>
                            </label>
                            <input type="hidden"
                                   id="{{ $cle }}"
                                   name="settings[{{ $cle }}]"
                                   value="{{ $cal['valeur_raw'] }}">
                            <div style="position:relative;margin-top:2px;">
                                <button type="button"
                                        id="{{ $cle }}_trigger"
                                        class="cs-trigger"
                                        aria-haspopup="listbox">
                                    <span class="cs-trigger-text {{ $cal['valeur_raw'] ? '' : 'placeholder' }}">
                                        {{ $cal['valeur_raw'] ?: 'Sélectionner…' }}
                                    </span>
                                    <span class="cs-trigger-arrow">▼</span>
                                </button>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════
        SECTION 4 — Décalages des tâches
    ════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden mb-6">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-amber-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">⏱️</div>
            <span class="font-heading text-[14px] font-semibold text-ink">Décalages des tâches</span>
        </div>

        <div class="flex items-start gap-2 mx-5 mt-4 mb-0 px-4 py-3 bg-sky-50 border border-sky-200 rounded-lg text-[12.5px] text-sky-900 leading-relaxed">
            <span class="flex-shrink-0 mt-px">ℹ️</span>
            <span>
                Les décalages sont en <strong>minutes par rapport à l'heure du cours</strong>.
                Une valeur négative signifie avant le cours (ex : −30 = 30 min avant), positive = après.
                Le rappel sandwich a un horaire fixe (08:00–08:15) indépendant.
            </span>
        </div>

        {{-- Table décalages — desktop (≥ sm) --}}
        <div class="hidden sm:block overflow-x-auto mt-4">
            <table class="w-full border-collapse text-[13.5px]">
                <thead>
                    <tr>
                        <th class="text-left px-5 py-2.5 text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] bg-surface-2 border-b border-surface-3 font-body whitespace-nowrap">
                            Tâche / Événement
                        </th>
                        <th class="text-center px-4 py-2.5 text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] bg-surface-2 border-b border-surface-3 font-body w-36 whitespace-nowrap">
                            Début (min)
                        </th>
                        <th class="text-center px-4 py-2.5 text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] bg-surface-2 border-b border-surface-3 font-body w-36 whitespace-nowrap">
                            Fin (min)
                        </th>
                        <th class="text-left px-5 py-2.5 text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] bg-surface-2 border-b border-surface-3 font-body w-44">
                            Horaire calculé
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($decalagesGroupes as $codeTache => $groupe)
                        @include('settings.partials._offset-row', compact('codeTache', 'groupe', 'horaires'))
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Cartes décalages — mobile (< sm) --}}
        <div class="sm:hidden divide-y divide-surface-3 mt-4">
            @foreach($decalagesGroupes as $codeTache => $groupe)
                @php
                    $isSandwich = $codeTache === 'rappel_sandwich';
                    $heureCours = $horaires['heure_cours']['valeur_raw'] ?? '20:00';
                    [$hh, $mm] = explode(':', $heureCours);
                    $baseMin = (int)$hh * 60 + (int)$mm;
                    $dOff = (int)($groupe['debut']['valeur_raw'] ?? 0);
                    $fOff = (int)($groupe['fin']['valeur_raw'] ?? 60);
                    $calcDebut = sprintf('%02d:%02d', intdiv(((($baseMin + $dOff) % 1440) + 1440) % 1440, 60), ((($baseMin + $dOff) % 1440) + 1440) % 1440 % 60);
                    $calcFin   = sprintf('%02d:%02d', intdiv(((($baseMin + $fOff) % 1440) + 1440) % 1440, 60), ((($baseMin + $fOff) % 1440) + 1440) % 1440 % 60);
                @endphp
                <div class="px-5 py-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold chip-{{ $codeTache }}">
                            {{ $groupe['libelle'] }}
                        </span>
                        <span class="text-[12.5px] font-semibold {{ $isSandwich ? 'text-ink-muted' : 'text-accent' }}">
                            {{ $isSandwich ? '08:00 → 08:15' : "$calcDebut → $calcFin" }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10.5px] font-bold text-ink-muted uppercase tracking-wide">Début (min)</label>
                            @if($groupe['debut'])
                                <input type="number"
                                       name="settings[{{ $groupe['debut']['cle'] }}]"
                                       value="{{ $groupe['debut']['valeur_raw'] }}"
                                       step="1" min="-999" max="999"
                                       {{ $isSandwich ? 'disabled' : '' }}
                                       class="w-full px-3 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base text-center font-body text-ink bg-surface-2 outline-none transition
                                              focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]
                                              disabled:opacity-50 disabled:cursor-not-allowed">
                                @if(!$isSandwich)<span class="text-[11px] text-ink-muted">min</span>@endif
                            @else
                                <span class="text-ink-faint text-sm">—</span>
                            @endif
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10.5px] font-bold text-ink-muted uppercase tracking-wide">Fin (min)</label>
                            @if($groupe['fin'])
                                <input type="number"
                                       name="settings[{{ $groupe['fin']['cle'] }}]"
                                       value="{{ $groupe['fin']['valeur_raw'] }}"
                                       step="1" min="-999" max="999"
                                       {{ $isSandwich ? 'disabled' : '' }}
                                       class="w-full px-3 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base text-center font-body text-ink bg-surface-2 outline-none transition
                                              focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]
                                              disabled:opacity-50 disabled:cursor-not-allowed">
                                @if(!$isSandwich)<span class="text-[11px] text-ink-muted">min</span>@endif
                            @else
                                <span class="text-ink-faint text-sm">—</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Boutons --}}
    <div class="flex flex-wrap gap-3 items-center">
        <button type="submit"
                class="inline-flex items-center gap-2 px-6 py-3 bg-accent hover:bg-accent-dark text-white font-bold text-[13.5px] rounded-lg
                       shadow-[0_3px_14px_rgba(3,105,161,0.35)] hover:shadow-[0_6px_20px_rgba(3,105,161,0.45)]
                       hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer min-h-[48px]">
            💾 Enregistrer les paramètres
        </button>
        <a href="{{ route('planning.index') }}"
           class="inline-flex items-center gap-2 px-6 py-3 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink font-semibold text-[13.5px] rounded-lg transition-colors no-underline min-h-[48px]">
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
        label.textContent = checkbox.checked ? '✅ Inscriptions ouvertes' : '🔒 Inscriptions fermées';
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
            const debutEl = document.querySelector('[name="' + span.dataset.debutInput + '"]');
            const finEl   = document.querySelector('[name="' + span.dataset.finInput + '"]');
            if (!debutEl || !finEl) return;
            span.textContent = addMinutes(heureCours, parseInt(debutEl.value, 10) || 0)
                             + ' → '
                             + addMinutes(heureCours, parseInt(finEl.value,   10) || 0);
        });
    }

    document.getElementById('settingsForm').addEventListener('input', updatePreviews);
})();
</script>

<script src="{{ asset('js/calendar-select.js') }}"></script>
<script>
(function () {
    const apiUrl = '{{ route("calendriers.index") }}';
    @foreach($calendarChips as $cle => $meta)
        @if(isset($calendriers[$cle]))
            @php $cal = $calendriers[$cle]; @endphp
            CalendarSelect.init({
                inputId      : '{{ $cle }}',
                triggerId    : '{{ $cle }}_trigger',
                apiUrl       : apiUrl,
                currentValue : '{{ addslashes($cal['valeur_raw']) }}',
            });
        @endif
    @endforeach
})();
</script>
@endpush
