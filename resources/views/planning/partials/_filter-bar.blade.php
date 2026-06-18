{{-- resources/views/planning/partials/_filter-bar.blade.php --}}
{{--
Variables attendues :
$allYears, $allMonths, $currentMonth, $previousMonth, $historique
--}}
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