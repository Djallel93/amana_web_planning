{{-- resources/views/bilan/statistiques.blade.php --}}
@extends('layouts.app')

@section('title', 'Statistiques Bilan — AMANA')

@section('content')

{{-- En-tête --}}
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Statistiques</h1>
        <p class="text-[13px] text-ink-muted mt-1">
            Évolution de la présence et des montants collectés
        </p>
    </div>
</div>

{{--
    Point de montage BilanStatistiques.vue — plage de dates + graphique
    (Chart.js) + cartes de stats. Les données sont chargées via GET
    /bilan/statistiques/data (BilanController::statistiquesData).
--}}
<div id="vue-bilan-statistiques"></div>

@endsection

@push('scripts')
<script>
    window.BilanStatistiquesConfig = {
        csrf: document.querySelector('meta[name="csrf-token"]').content,
        routes: {
            data: '{{ route('bilan.statistiques.data') }}',
        },
    };
</script>
@endpush
