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

{{--
    Point de montage PlanningGrid.vue — remplace entièrement le rendu Blade
    des bannières historique, de la barre de filtres, des blocs semaine,
    des modals d'édition et du toastContainer.
    Les données sont chargées via GET /planning/data (PlanningApiController).
--}}
<div id="vue-planning-grid"></div>

@endsection

@push('scripts')
<script>
    window.PlanningConfig = {
        csrf: document.querySelector('meta[name="csrf-token"]').content,
        routes: {
            personnes:    '{{ route("planning.edit.personnes") }}',
            assignation:  '{{ url("planning/creneau") }}',
            creneau:      '{{ url("planning/creneau") }}',
            data:         '{{ route("planning.data") }}',
            annulationCours: '{{ route("planning.annulation-cours") }}',
        },
    };
</script>
@endpush
