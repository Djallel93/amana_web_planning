{{-- resources/views/planning/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Planning — AMANA')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/planning-index.css') }}">
@endpush

@section('content')
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Planning des permanences</div>
            <div class="page-subtitle">Vendredis &amp; samedis — cliquez sur une cellule pour modifier</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('mon-planning') }}" class="btn btn-ghost btn-sm">🙋 Mon planning</a>
            <a href="{{ route('planning.export.form') }}" class="btn btn-secondary">📄 Export PDF</a>
            @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                <a href="{{ route('planning.generate.form') }}" class="btn btn-primary">✨ Générer</a>
            @endif
        </div>
    </div>

    @if($historique)
        <div class="historique-banner">
            <span>📚</span>
            <span>Affichage de tout l'historique.</span>
            <a href="{{ route('planning.index') }}" class="btn btn-secondary btn-sm" style="margin-left:auto;">
                ← Vue normale (1 an)
            </a>
        </div>
    @endif

    @if($creneaux->isEmpty())
        <div class="card">
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <div class="empty-title">Aucun planning généré</div>
                <div class="empty-desc">
                    @if(!$historique)
                        Aucun créneau dans les 12 derniers mois.
                        <a href="{{ route('planning.index', ['historique' => 1]) }}"
                            style="color:var(--app-accent);font-weight:600;">
                            Voir tout l'historique
                        </a>
                    @else
                        Cliquez sur "Générer" pour créer le premier planning automatique.
                    @endif
                </div>
                @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                    <a href="{{ route('planning.generate.form') }}" class="btn btn-primary btn-lg" style="margin-top:16px;">
                        ✨ Générer maintenant
                    </a>
                @endif
            </div>
        </div>
    @else

        @php
            // Listes des années / mois disponibles, utilisées par la barre de filtres.
            $allYears = [];
            $allMonths = [];
            foreach ($creneaux as $group) {
                foreach ($group as $c) {
                    $allYears[$c->date->year] = $c->date->year;
                    $allMonths[$c->date->month] = $c->date->locale('fr')->isoFormat('MMMM');
                }
            }
            krsort($allYears);
            ksort($allMonths);

            $currentMonth  = (int) now()->format('n');
            $currentYear   = (int) now()->format('Y');
            $previousMonth = $currentMonth === 1  ? 12 : $currentMonth - 1;
            $nextMonth     = $currentMonth === 12 ? 1  : $currentMonth + 1;
            // Années auxquelles appartiennent mois-1 et mois+1 (gestion des bords janvier/décembre)
            $previousMonthYear = $currentMonth === 1  ? $currentYear - 1 : $currentYear;
            $nextMonthYear     = $currentMonth === 12 ? $currentYear + 1 : $currentYear;
        @endphp

        @include('planning.partials._filter-bar', [
            'allYears'          => $allYears,
            'allMonths'         => $allMonths,
            'currentMonth'      => $currentMonth,
            'currentYear'       => $currentYear,
            'previousMonth'     => $previousMonth,
            'previousMonthYear' => $previousMonthYear,
            'nextMonth'         => $nextMonth,
            'nextMonthYear'     => $nextMonthYear,
            'historique'        => $historique,
        ])

        {{-- Semaines --}}
        <div id="planningContainer">
            @foreach($creneaux as $semaineCle => $creneauxSemaine)
                @include('planning.partials._week-block', [
                    'semaineCle' => $semaineCle,
                    'creneauxSemaine' => $creneauxSemaine,
                    'bannièresParSemaine' => $bannièresParSemaine,
                ])
            @endforeach
        </div>
    @endif

    @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
        @include('planning.partials._edit-modal')
        @include('planning.partials._add-creneau-modal')
    @endif

    <div class="toast-container" id="toastContainer"></div>
@endsection

@push('scripts')
    {{--
        Le JS de cette page vit dans public/js/planning-index.js (fichier statique,
        pas de build npm). On expose seulement ce qui dépend de Blade/Laravel
        (CSRF token, routes nommées) via un petit objet de config global.
    --}}
    <script>
        window.PlanningConfig = {
            csrf: document.querySelector('meta[name="csrf-token"]').content,
            routes: {
                personnes: '{{ route("planning.edit.personnes") }}',
                assignation: '{{ url("planning/creneau") }}',
                creneau: '{{ url("planning/creneau") }}',
            },
        };
    </script>
    <script src="{{ asset('js/planning-index.js') }}"></script>
@endpush