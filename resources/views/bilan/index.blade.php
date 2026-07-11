{{-- resources/views/bilan/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Bilan — AMANA')

@section('content')

{{-- En-tête --}}
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Bilan</h1>
        <p class="text-[13px] text-ink-muted mt-1">
            Amana food et présences — choisissez une date pour consulter ou saisir le bilan
        </p>
    </div>
</div>

{{--
    Point de montage BilanView.vue — date picker + sections "Amana food" et
    "Présences", chacune avec son propre bouton d'enregistrement. Les
    données sont chargées via GET /bilan/data et enregistrées via
    POST /bilan/data/amana-food ou /bilan/data/presence (BilanController),
    indépendamment l'une de l'autre — pas de propriétaire (n'importe quel
    utilisateur connecté peut consulter et modifier n'importe quelle date),
    mais deux personnes peuvent éditer les deux groupes en parallèle sans
    s'écraser.
--}}
<div id="vue-bilan"></div>

@endsection

@push('scripts')
<script>
    window.BilanConfig = {
        csrf: document.querySelector('meta[name="csrf-token"]').content,
        routes: {
            data: '{{ route('bilan.data.show') }}',
            storeAmanaFood: '{{ route('bilan.data.store.amana-food') }}',
            storePresence: '{{ route('bilan.data.store.presence') }}',
        },
    };
</script>
@endpush
