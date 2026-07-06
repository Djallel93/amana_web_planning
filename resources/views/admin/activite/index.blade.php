{{-- resources/views/admin/activite/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Statistiques d\'activité — AMANA')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Statistiques d'activité</h1>
        <p class="text-[13px] text-ink-muted mt-1">
            Utilisation de l'application : actions, connexions, échanges
        </p>
    </div>
</div>

{{--
    Point de montage ActiviteStatistiques.vue — plage de dates + graphique
    + répartitions + cartes de synthèse. Données via GET /admin/activite/data
    (Admin\ActiviteController::data), calculées depuis audit_logs.
--}}
<div id="vue-activite-statistiques"></div>

@endsection

@push('scripts')
<script>
    window.ActiviteStatistiquesConfig = {
        csrf: document.querySelector('meta[name="csrf-token"]').content,
        routes: {
            data: '{{ route('admin.activite.data') }}',
        },
    };
</script>
@endpush
