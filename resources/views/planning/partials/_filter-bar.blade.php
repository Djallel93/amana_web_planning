{{-- resources/views/planning/partials/_filter-bar.blade.php --}}
{{--
Variables attendues :
$allYears, $allMonths, $currentMonth, $currentYear,
$previousMonth, $previousMonthYear, $nextMonth, $nextMonthYear, $historique

Filtre par défaut : année courante + (mois-1, mois courant, mois+1).
Les bords janvier/décembre sont gérés via *MonthYear : si mois-1 ou mois+1
appartient à une autre année, seul le mois de cette autre année est activé
(pas l'année entière), afin de ne pas afficher trop de données.
--}}
<div class="filter-bar">
    <span class="filter-label">Filtrer</span>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span class="filter-label" style="font-size:10px;">Année</span>
        <div class="filter-group" id="yearFilters">
            @foreach($allYears as $year)
                <span class="filter-chip {{ $year === $currentYear ? 'active' : '' }}" data-type="year"
                    data-value="{{ $year }}" onclick="toggleFilter(this)">{{ $year }}</span>
            @endforeach
        </div>
    </div>
    <div class="filter-divider"></div>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span class="filter-label" style="font-size:10px;">Mois</span>
        <div class="filter-group" id="monthFilters">
            @foreach($allMonths as $num => $name)
                <span class="filter-chip {{ in_array($num, [$currentMonth, $previousMonth, $nextMonth]) ? 'active' : '' }}"
                    data-type="month" data-value="{{ $num }}" onclick="toggleFilter(this)">
                    {{ ucfirst($name) }}
                </span>
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

@once
    @push('scripts')
        <script>
            // Fenêtre par défaut : année courante + mois-1/mois/mois+1.
            // On stocke aussi l'année de chaque mois de bord pour que applyFilters()
            // puisse filtrer année ET mois simultanément avec cohérence.
            window.PlanningFilterDefaults = {
                currentYear: {{ $currentYear }},
                currentMonth: {{ $currentMonth }},
                previousMonth: {{ $previousMonth }},
                previousMonthYear: {{ $previousMonthYear }},
                nextMonth: {{ $nextMonth }},
                nextMonthYear: {{ $nextMonthYear }},
            };
        </script>
    @endpush
@endonce