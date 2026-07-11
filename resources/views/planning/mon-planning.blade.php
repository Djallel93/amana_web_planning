{{-- resources/views/planning/mon-planning.blade.php --}}
@extends('layouts.app')

@section('title', 'Mon planning — AMANA')

@section('content')

    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Mon planning</h1>
            <p class="text-[13px] text-ink-muted mt-1">
                {{ $historique ? 'Vos permanences — historique complet' : 'Vos permanences — un an glissant + futur' }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('echanges.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
                🔄 Mes échanges
            </a>
            <a href="{{ route('planning.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
                📅 Planning complet
            </a>
        </div>
    </div>

    {{-- Stats strip --}}
    @php
        $tachesMeta = [
            'entree' => ['Entrée', '🚪'],
            'mektaba' => ['Mektaba', '📚'],
            'salle' => ['Salle', '🏛️'],
            'amana_food' => ['Amana Food', '🥪'],
            'cours' => ['Cours', '🎓'],
        ];
    @endphp
    <div class="flex flex-wrap gap-2.5 mb-6">
        <div
            class="bg-surface border border-surface-border rounded-xl shadow-sm px-4 py-3 flex flex-col gap-0.5 min-w-[90px]">
            <div class="font-heading text-2xl font-bold text-ink">{{ $total }}</div>
            <div class="text-[10.5px] font-bold uppercase tracking-[0.6px] text-ink-muted">Total</div>
        </div>
        <div
            class="bg-surface border border-surface-border rounded-xl shadow-sm px-4 py-3 flex flex-col gap-0.5 min-w-[90px]">
            <div class="font-heading text-2xl font-bold text-accent">{{ $futures }}</div>
            <div class="text-[10.5px] font-bold uppercase tracking-[0.6px] text-ink-muted">À venir</div>
        </div>
        @foreach($parTache as $code => $count)
            @if(isset($tachesMeta[$code]))
                <div
                    class="bg-surface border border-surface-border rounded-xl shadow-sm px-4 py-3 flex flex-col gap-0.5 min-w-[90px]">
                    <div class="font-heading text-2xl font-bold text-ink">{{ $count }}</div>
                    <div class="text-[10.5px] font-bold uppercase tracking-[0.6px] text-ink-muted">
                        {{ $tachesMeta[$code][1] }} {{ $tachesMeta[$code][0] }}
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    @if($parMois->isEmpty())
        <div class="bg-surface rounded-xl border border-surface-border shadow-sm">
            <div class="text-center py-16 px-8">
                <div class="text-5xl mb-3 opacity-40">📭</div>
                <h3 class="font-heading text-base font-semibold text-ink mb-1.5">Aucune permanence</h3>
                <p class="text-ink-muted text-[13.5px]">
                    @if(!$historique)
                        Aucune permanence sur les 12 derniers mois.
                        <a href="{{ route('mon-planning') }}?historique=1" class="text-accent font-semibold hover:underline">Voir
                            tout l'historique</a>
                    @else
                        Vous n'avez pas encore été assigné(e) à des créneaux.
                    @endif
                </p>
            </div>
        </div>
    @else
        @php
            // Années/mois réellement présents dans $parMois, pour construire les
            // pastilles de filtre — jamais de valeur "fantôme" qui n'aurait pas
            // de bloc correspondant dans le DOM (même règle que PlanningGrid.vue).
            $moisLabels = ['01' => 'Jan', '02' => 'Fév', '03' => 'Mar', '04' => 'Avr', '05' => 'Mai', '06' => 'Juin', '07' => 'Juil', '08' => 'Août', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Déc'];
            $anneesDisponibles = $parMois->keys()->map(fn($k) => (int) explode('-', $k)[0])->unique()->sort()->values();
            $moisDisponibles = $parMois->keys()->map(fn($k) => (int) explode('-', $k)[1])->unique()->sort()->values();
        @endphp

        {{-- Barre de filtres --}}
        <div id="monPlanningFiltres"
            class="flex flex-wrap items-center gap-2.5 px-4 py-3 mb-5 bg-surface border border-surface-border rounded-xl shadow-sm">
            <span class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.8px]">Filtrer</span>

            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-[9.5px] font-bold text-ink-faint uppercase tracking-[0.8px]">Année</span>
                <div class="flex gap-1 flex-wrap">
                    @foreach($anneesDisponibles as $annee)
                        <span data-mp-year="{{ $annee }}"
                            class="mp-filter-pill px-2.5 py-1 rounded-md text-[12px] font-semibold cursor-pointer select-none transition-colors border bg-surface-2 text-ink-muted border-surface-border hover:border-accent hover:text-accent">{{ $annee }}</span>
                    @endforeach
                </div>
            </div>

            <div class="w-px h-5 bg-surface-border flex-shrink-0"></div>

            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-[9.5px] font-bold text-ink-faint uppercase tracking-[0.8px]">Mois</span>
                <div class="flex gap-1 flex-wrap">
                    @foreach($moisDisponibles as $mois)
                        <span data-mp-month="{{ $mois }}"
                            class="mp-filter-pill px-2.5 py-1 rounded-md text-[12px] font-semibold cursor-pointer select-none transition-colors border bg-surface-2 text-ink-muted border-surface-border hover:border-accent hover:text-accent">{{ $moisLabels[str_pad((string) $mois, 2, '0', STR_PAD_LEFT)] }}</span>
                    @endforeach
                </div>
            </div>

            <div class="w-px h-5 bg-surface-border flex-shrink-0"></div>

            <button type="button" id="mpClearFilters"
                class="px-2.5 py-1 text-[12px] font-semibold text-ink-muted border border-surface-border rounded-md
                                   hover:border-rose-300 hover:text-rose-500 transition-colors bg-transparent cursor-pointer min-h-[44px]">✕ Effacer</button>

            @if(!$historique)
                <a href="{{ route('mon-planning') }}?historique=1" class="px-2.5 py-1 text-[12px] font-semibold text-ink-muted border border-surface-border rounded-md
                                               hover:border-accent hover:text-accent transition-colors min-h-[44px] inline-flex items-center
                                               whitespace-nowrap no-underline">📚 Historique complet</a>
            @else
                <a href="{{ route('mon-planning') }}" class="px-2.5 py-1 text-[12px] font-semibold text-ink-muted border border-surface-border rounded-md
                                               hover:border-accent hover:text-accent transition-colors min-h-[44px] inline-flex items-center
                                               whitespace-nowrap no-underline">↩︎ Douze derniers mois</a>
            @endif

            <span id="mpResultsCount" class="ml-auto text-[11.5px] text-ink-muted italic"></span>
        </div>

        <div class="flex flex-col gap-7" id="monPlanningListe">
            @foreach($parMois as $moisKey => $lignes)
                @php
                    $firstDate = $lignes->first()->creneau->date;
                    $moisLabel = ucfirst($firstDate->locale('fr')->isoFormat('MMMM YYYY'));
                    [$anneeGroupe, $moisGroupe] = explode('-', $moisKey);
                @endphp
                <div data-mp-group data-mp-year="{{ (int) $anneeGroupe }}" data-mp-month="{{ (int) $moisGroupe }}">
                    {{-- En-tête mois --}}
                    <div class="flex items-center gap-3 mb-3.5">
                        <span
                            class="font-heading text-[13px] font-bold uppercase tracking-[1.2px] text-ink-muted">{{ $moisLabel }}</span>
                        <div class="flex-1 h-px bg-surface-border"></div>
                        <span class="text-[11px] text-ink-faint font-semibold whitespace-nowrap">
                            {{ $lignes->count() }} créneau{{ $lignes->count() > 1 ? 'x' : '' }}
                        </span>
                    </div>

                    {{-- Cartes créneaux --}}
                    <div class="flex flex-col gap-2.5">
                        @foreach($lignes as $ligne)
                            @php
                                $creneau = $ligne->creneau;
                                $tache = $ligne->tache;
                                $date = $creneau->date;
                                $isToday = $date->isToday();
                                $isFuture = $date->isFuture() && !$isToday;
                                $isPast = $date->isPast() && !$isToday;
                                $evtStr = $creneau->evenements?->pluck('nom')->implode(', ');
                                $echangeEnAttente = $echangesEnAttente->first(
                                    fn($e) =>
                                    ($e->id_creneau_demandeur === $creneau->id && $e->id_tache_demandeur === $tache?->id)
                                    || ($e->id_creneau_cible === $creneau->id && $e->id_tache_cible === $tache?->id)
                                );
                                $borderColor = $isToday ? 'border-l-emerald-400' : ($isFuture ? 'border-l-accent' : 'border-l-surface-3');
                                $bgColor = $isToday ? 'bg-emerald-50' : 'bg-surface';
                                $icons = ['entree' => '🚪', 'mektaba' => '📚', 'salle' => '🏛️', 'amana_food' => '🥪', 'cours' => '🎓'];
                            @endphp

                            <div data-mp-card
                                class="relative flex items-center gap-4 sm:gap-5 px-4 py-3.5 {{ $bgColor }} rounded-xl border border-surface-border border-l-[3px] {{ $borderColor }} shadow-sm
                                                                    {{ $isPast ? 'opacity-70' : '' }} {{ $isFuture ? 'hover:shadow transition-shadow' : '' }}">

                                @if($echangeEnAttente)
                                    <span
                                        class="absolute top-2.5 right-3.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-bold bg-amber-50 border border-amber-200 text-amber-800">
                                        ⏳ Échange en attente
                                    </span>
                                @endif

                                {{-- Date --}}
                                <div class="flex-shrink-0 w-14 text-center">
                                    <div class="font-heading text-[26px] font-bold text-ink leading-none">{{ $date->format('d') }}</div>
                                    <div class="text-[10.5px] font-bold uppercase tracking-[0.7px] text-ink-muted">
                                        {{ $date->locale('fr')->isoFormat('MMM') }}
                                    </div>
                                    <div class="text-[10px] text-ink-faint font-semibold mt-0.5">{{ $creneau->jour }}</div>
                                </div>

                                <div class="w-px h-11 bg-surface-3 flex-shrink-0 hidden sm:block"></div>

                                {{-- Infos --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap mb-1">
                                        @if($tache) <span
                                            class="chip-{{ $tache->code }} inline-flex items-center px-2.5 py-0.5 rounded-full text-[12px] font-semibold">
                                        {{ $icons[$tache->code] ?? '' }} {{ $tache->libelle }} </span> @endif
                                        <span
                                            class="text-[11px] text-ink-muted bg-surface-2 border border-surface-border px-2 py-0.5 rounded-full font-semibold">
                                            S{{ $creneau->semaine }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 flex-wrap text-[12px] text-ink-muted">
                                        <span>{{ $date->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</span>
                                        @if($evtStr)
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 border border-amber-200 text-amber-700">
                                                🎉 {{ $evtStr }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Statut + action --}}
                                <div class="flex-shrink-0 flex flex-col items-end gap-2">
                                    @if($isToday) <span
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 border border-emerald-200 text-emerald-700">●
                                    Aujourd'hui</span> @elseif($isFuture) <span
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-sky-50 border border-sky-200 text-sky-700">→
                                        À venir</span> @else <span
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-surface-3 border border-surface-border text-ink-muted">✓
                                        Effectué</span> @endif

                                    @if($isFuture && !$echangeEnAttente && $tache)
                                        <button
                                            class="inline-flex items-center gap-1 px-3 py-1.5 border-[1.5px] border-accent text-accent hover:bg-sky-50 text-[11.5px] font-semibold rounded-lg cursor-pointer transition-colors bg-transparent min-h-[44px]"
                                            data-creneau-id="{{ $creneau->id }}" data-tache-id="{{ $tache->id }}"
                                            data-tache-libelle="{{ $tache->libelle }}"
                                            data-date="{{ $date->locale('fr')->isoFormat('dddd D MMMM YYYY') }}"
                                            onclick="openSwapModal(this)">
                                            🔄 Échanger
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{--
    Point de montage Vue — SwapRequestModal.vue remplace le bloc modal
    et le script inline ci-dessous. Le composant est monté par app.ts
    sur #vue-swap-modal.
    --}}
    <div id="vue-swap-modal"></div>

    {{--
    toastContainer supprimé : les toasts sont maintenant gérés par
    Toast.vue monté globalement sur #vue-toast dans layouts/app.blade.php.
    --}}

@endsection

@push('scripts')
    <script>
        {
            {
                --
                    MonPlanningConfig : injecte les routes Laravel dans window pour que
                SwapRequestModal.vue puisse les consommer sans dépendre de Blade.
                    C'est le même pattern que window.PlanningConfig dans PlanningGrid.vue.
                --}
        }
        window.MonPlanningConfig = {
            routeSlots: '{{ route("echanges.slots") }}',
            routeStore: '{{ route("echanges.store") }}',
        };

        {
            {
                --
                    Filtres année / mois pour Mon planning — mêmes règles que PlanningGrid.vue :
                - Filtres par défaut = mois courant ± 1, restreints aux mois / années
              RÉELLEMENT présents dans les blocs rendus(évite le bug des filtres
              "fantômes" corrigé sur la page Planning : un mois calculé depuis la
              date du jour mais absent des données ne doit jamais entrer dans le
              filtre actif, sinon décocher tout ce qui est visible ne vide jamais
              complètement le filtre et fait disparaître les résultats).
            - Ensemble vide sur une dimension(année ou mois) = pas de filtre sur
              cette dimension(tout est affiché).
            - "Effacer" vide les deux ensembles.
            - "Historique complet" est un lien serveur(?historique = 1) : contrairement
              à la page Planning, cette vue est rendue côté serveur, donc l'aller
              chercher revient à recharger la page avec le jeu de données complet.
        --}
        }
        document.addEventListener('DOMContentLoaded', function () {
            var bar = document.getElementById('monPlanningFiltres');
            if (!bar) return; // Aucune permanence : pas de barre de filtres à activer.

            var groups = Array.from(document.querySelectorAll('[data-mp-group]'));
            var yearPills = Array.from(bar.querySelectorAll('[data-mp-year]'));
            var monthPills = Array.from(bar.querySelectorAll('[data-mp-month]'));
            var resultsLabel = document.getElementById('mpResultsCount');

            var activeYears = new Set();
            var activeMonths = new Set();

            var ACTIVE_CLASSES = ['bg-accent', 'text-white', 'border-accent'];
            var INACTIVE_CLASSES = ['bg-surface-2', 'text-ink-muted', 'border-surface-border'];

            function paintPill(pill, active) {
                pill.classList.remove.apply(pill.classList, active ? INACTIVE_CLASSES : ACTIVE_CLASSES);
                pill.classList.add.apply(pill.classList, active ? ACTIVE_CLASSES : INACTIVE_CLASSES);
            }

            function applyFilters() {
                var visibleGroups = 0;
                var visibleCards = 0;

                groups.forEach(function (group) {
                    var year = parseInt(group.getAttribute('data-mp-year'), 10);
                    var month = parseInt(group.getAttribute('data-mp-month'), 10);
                    var yearOk = activeYears.size === 0 || activeYears.has(year);
                    var monthOk = activeMonths.size === 0 || activeMonths.has(month);
                    var show = yearOk && monthOk;

                    group.style.display = show ? '' : 'none';
                    if (show) {
                        visibleGroups++;
                        visibleCards += group.querySelectorAll('[data-mp-card]').length;
                    }
                });

                yearPills.forEach(function (pill) {
                    paintPill(pill, activeYears.has(parseInt(pill.getAttribute('data-mp-year'), 10)));
                });
                monthPills.forEach(function (pill) {
                    paintPill(pill, activeMonths.has(parseInt(pill.getAttribute('data-mp-month'), 10)));
                });

                if (resultsLabel) {
                    resultsLabel.textContent = visibleGroups === 0
                        ? 'Aucun résultat'
                        : visibleCards + ' créneau' + (visibleCards > 1 ? 'x' : '');
                }
            }

            yearPills.forEach(function (pill) {
                pill.addEventListener('click', function () {
                    var year = parseInt(pill.getAttribute('data-mp-year'), 10);
                    activeYears.has(year) ? activeYears.delete(year) : activeYears.add(year);
                    applyFilters();
                });
            });

            monthPills.forEach(function (pill) {
                pill.addEventListener('click', function () {
                    var month = parseInt(pill.getAttribute('data-mp-month'), 10);
                    activeMonths.has(month) ? activeMonths.delete(month) : activeMonths.add(month);
                    applyFilters();
                });
            });

            var clearBtn = document.getElementById('mpClearFilters');
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    activeYears.clear();
                    activeMonths.clear();
                    applyFilters();
                });
            }

            // Filtres par défaut : mois courant ± 1, restreints aux valeurs présentes
            // dans les groupes réellement rendus (cf. commentaire en tête de bloc).
            (function applyDefaultFilters() {
                var now = new Date();
                var currentMonth = now.getMonth() + 1;
                var currentYear = now.getFullYear();
                var previousMonth = currentMonth === 1 ? 12 : currentMonth - 1;
                var nextMonth = currentMonth === 12 ? 1 : currentMonth + 1;
                var previousMonthYear = currentMonth === 1 ? currentYear - 1 : currentYear;
                var nextMonthYear = currentMonth === 12 ? currentYear + 1 : currentYear;

                var candidateYears = new Set([currentYear, previousMonthYear, nextMonthYear]);
                var candidateMonths = new Set([previousMonth, currentMonth, nextMonth]);

                var availableYears = new Set(yearPills.map(function (p) { return parseInt(p.getAttribute('data-mp-year'), 10); }));
                var availableMonths = new Set(monthPills.map(function (p) { return parseInt(p.getAttribute('data-mp-month'), 10); }));

                candidateYears.forEach(function (y) { if (availableYears.has(y)) activeYears.add(y); });
                candidateMonths.forEach(function (m) { if (availableMonths.has(m)) activeMonths.add(m); });

                applyFilters();
            })();
        });
    </script>
@endpush