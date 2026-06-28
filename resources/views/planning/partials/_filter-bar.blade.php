{{-- resources/views/planning/partials/_filter-bar.blade.php --}}
{{--
Variables attendues :
$allYears, $allMonths, $currentMonth, $currentYear,
$previousMonth, $previousMonthYear, $nextMonth, $nextMonthYear, $historique
--}}
<div class="flex flex-wrap items-center gap-2.5 px-4 py-3 mb-5 bg-white border border-surface-border rounded-xl shadow-sm">

    <span class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.8px]">Filtrer</span>

    {{-- Années --}}
    <div class="flex items-center gap-1.5 flex-wrap">
        <span class="text-[9.5px] font-bold text-ink-faint uppercase tracking-[0.8px]">Année</span>
        <div class="flex gap-1 flex-wrap" id="yearFilters">
            @foreach($allYears as $year)
                <span class="filter-chip px-2.5 py-1 rounded-md text-[12px] font-semibold cursor-pointer select-none transition-colors border
                             {{ $year === $currentYear
                                 ? 'bg-accent text-white border-accent'
                                 : 'bg-surface-2 text-ink-muted border-surface-border hover:border-accent hover:text-accent' }}"
                      data-type="year" data-value="{{ $year }}"
                      onclick="toggleFilter(this)">{{ $year }}</span>
            @endforeach
        </div>
    </div>

    <div class="w-px h-5 bg-surface-border flex-shrink-0"></div>

    {{-- Mois --}}
    <div class="flex items-center gap-1.5 flex-wrap">
        <span class="text-[9.5px] font-bold text-ink-faint uppercase tracking-[0.8px]">Mois</span>
        <div class="flex gap-1 flex-wrap" id="monthFilters">
            @foreach($allMonths as $num => $name)
                <span class="filter-chip px-2.5 py-1 rounded-md text-[12px] font-semibold cursor-pointer select-none transition-colors border
                             {{ in_array($num, [$currentMonth, $previousMonth, $nextMonth])
                                 ? 'bg-accent text-white border-accent'
                                 : 'bg-surface-2 text-ink-muted border-surface-border hover:border-accent hover:text-accent' }}"
                      data-type="month" data-value="{{ $num }}"
                      onclick="toggleFilter(this)">{{ ucfirst($name) }}</span>
            @endforeach
        </div>
    </div>

    <div class="w-px h-5 bg-surface-border flex-shrink-0"></div>

    <button onclick="clearFilters()"
            class="px-2.5 py-1 text-[12px] font-semibold text-ink-muted border border-surface-border rounded-md hover:border-rose-300 hover:text-rose-500 transition-colors bg-transparent cursor-pointer min-h-[44px]">
        ✕ Effacer
    </button>

    @if(!$historique)
        <a href="{{ route('planning.index', ['historique' => 1]) }}"
           class="px-2.5 py-1 text-[12px] font-semibold text-ink-muted border border-surface-border rounded-md hover:border-accent hover:text-accent transition-colors no-underline min-h-[44px] inline-flex items-center whitespace-nowrap">
            📚 Historique complet
        </a>
    @endif

    <span class="ml-auto text-[11.5px] text-ink-muted italic" id="resultsCount"></span>
</div>

@once
    @push('scripts')
    <script>
        window.PlanningFilterDefaults = {
            currentYear:        {{ $currentYear }},
            currentMonth:       {{ $currentMonth }},
            previousMonth:      {{ $previousMonth }},
            previousMonthYear:  {{ $previousMonthYear }},
            nextMonth:          {{ $nextMonth }},
            nextMonthYear:      {{ $nextMonthYear }},
        };
    </script>
    @endpush
@endonce
