{{-- resources/views/planning/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Planning — AMANA')

@section('content')

{{-- En-tête --}}
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Planning des permanences</h1>
        <p class="text-[13px] text-ink-muted mt-1">Vendredis &amp; samedis — cliquez sur une cellule pour modifier</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('mon-planning') }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 text-[12.5px] font-semibold text-ink-muted border-[1.5px] border-ink-faint rounded-lg hover:bg-surface-3 hover:text-ink transition-colors no-underline min-h-[44px]">
            🙋 Mon planning
        </a>
        <a href="{{ route('planning.export.form') }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 text-[12.5px] font-semibold text-ink-muted border-[1.5px] border-ink-faint rounded-lg hover:bg-surface-3 hover:text-ink transition-colors no-underline min-h-[44px]">
            📄 Export PDF
        </a>
        @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
            <a href="{{ route('planning.generate.form') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent hover:bg-accent-dark text-white text-[12.5px] font-semibold rounded-lg
                      shadow-[0_3px_12px_rgba(3,105,161,0.3)] hover:-translate-y-px transition-all no-underline min-h-[44px]">
                ✨ Générer
            </a>
        @endif
    </div>
</div>

@if($historique)
    <div class="flex items-center gap-3 px-4 py-3 mb-5 bg-amber-50 border border-amber-200 rounded-lg text-[13px] text-amber-800">
        <span>📚</span>
        <span class="flex-1">Affichage de tout l'historique.</span>
        <a href="{{ route('planning.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[12px] font-semibold border border-amber-300 rounded-lg hover:bg-amber-100 transition-colors no-underline text-amber-800 min-h-[44px]">
            ← Vue normale (1 an)
        </a>
    </div>
@endif

@if($creneaux->isEmpty())
    <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <div class="text-center py-16 px-8">
            <div class="text-5xl mb-3 opacity-40">📭</div>
            <h3 class="font-heading text-base font-semibold text-ink mb-1.5">Aucun planning généré</h3>
            <p class="text-ink-muted text-[13.5px] mb-6">
                @if(!$historique)
                    Aucun créneau dans les 12 derniers mois.
                    <a href="{{ route('planning.index', ['historique' => 1]) }}" class="text-accent font-semibold hover:underline">
                        Voir tout l'historique
                    </a>
                @else
                    Cliquez sur "Générer" pour créer le premier planning automatique.
                @endif
            </p>
            @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                <a href="{{ route('planning.generate.form') }}"
                   class="inline-flex items-center gap-2 px-6 py-3 bg-accent hover:bg-accent-dark text-white font-bold text-[13.5px] rounded-lg
                          shadow-[0_3px_14px_rgba(3,105,161,0.35)] transition-all no-underline min-h-[48px]">
                    ✨ Générer maintenant
                </a>
            @endif
        </div>
    </div>

@else

    @php
        $allYears  = [];
        $allMonths = [];
        foreach ($creneaux as $group) {
            foreach ($group as $c) {
                $allYears[$c->date->year]   = $c->date->year;
                $allMonths[$c->date->month] = $c->date->locale('fr')->isoFormat('MMMM');
            }
        }
        krsort($allYears);
        ksort($allMonths);

        $currentMonth       = (int) now()->format('n');
        $currentYear        = (int) now()->format('Y');
        $previousMonth      = $currentMonth === 1  ? 12 : $currentMonth - 1;
        $nextMonth          = $currentMonth === 12 ? 1  : $currentMonth + 1;
        $previousMonthYear  = $currentMonth === 1  ? $currentYear - 1 : $currentYear;
        $nextMonthYear      = $currentMonth === 12 ? $currentYear + 1 : $currentYear;
    @endphp

    @include('planning.partials._filter-bar', compact(
        'allYears','allMonths','currentMonth','currentYear',
        'previousMonth','previousMonthYear','nextMonth','nextMonthYear','historique'
    ))

    <div id="planningContainer">
        @foreach($creneaux as $semaineCle => $creneauxSemaine)
            @include('planning.partials._week-block', [
                'semaineCle'          => $semaineCle,
                'creneauxSemaine'     => $creneauxSemaine,
                'bannièresParSemaine' => $bannièresParSemaine,
            ])
        @endforeach
    </div>
@endif

@if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
    @include('planning.partials._edit-modal')
    @include('planning.partials._add-creneau-modal')
@endif

<div id="toastContainer" class="fixed bottom-5 right-5 z-[500] flex flex-col gap-2 pointer-events-none"></div>

@endsection

@push('scripts')
<script>
    window.PlanningConfig = {
        csrf: document.querySelector('meta[name="csrf-token"]').content,
        routes: {
            personnes:    '{{ route("planning.edit.personnes") }}',
            assignation:  '{{ url("planning/creneau") }}',
            creneau:      '{{ url("planning/creneau") }}',
        },
    };
</script>
<script src="{{ asset('js/planning-index.js') }}"></script>
@endpush
