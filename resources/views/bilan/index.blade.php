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
    "Présences". Les données sont chargées/enregistrées via GET/POST
    /bilan/data (BilanController), un enregistrement unique et partagé par
    date (pas de propriétaire — n'importe quel utilisateur connecté peut
    consulter et modifier n'importe quelle date).
--}}
<div id="vue-bilan"></div>

@endsection

@push('scripts')
<script>
    window.BilanConfig = {
        csrf: document.querySelector('meta[name="csrf-token"]').content,
        routes: {
            data: '{{ route('bilan.data.show') }}',
        },
    };
</script>
@endpush
