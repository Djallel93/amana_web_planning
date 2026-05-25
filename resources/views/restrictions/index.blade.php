{{-- resources/views/restrictions/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Restrictions — AMANA')

@push('styles')
<style>
    .grid-restrictions {
        overflow-x: auto;
    }
    .grid-restrictions table {
        min-width: 700px;
    }
    .grid-restrictions th.jour-header {
        text-align: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-size: 13px;
        padding: 10px 8px;
    }
    .grid-restrictions th.tache-sub {
        text-align: center;
        font-size: 11px;
        text-transform: uppercase;
        padding: 6px 4px;
        background: #f0f4ff;
        color: #4a5568;
        white-space: nowrap;
    }
    .grid-restrictions td {
        text-align: center;
        padding: 8px 6px;
    }
    .grid-restrictions td:first-child {
        text-align: left;
        padding-left: 14px;
        font-weight: 600;
        white-space: nowrap;
    }
    .grid-restrictions input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #667eea;
    }
    .tache-entree-h    { color: #3182ce; }
    .tache-mektaba-h   { color: #276749; }
    .tache-salle-h     { color: #c05621; }
    .tache-amana_food-h { color: #c53030; }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">🔒 Restrictions de disponibilité</div>
        <div class="page-subtitle">
            Cocher ✓ = la personne <strong>PEUT</strong> effectuer la tâche ce jour-là
        </div>
    </div>
</div>

@if($personnes->isEmpty())
    <div class="card" style="text-align:center; padding:40px; color:#718096;">
        Aucun membre officiel actif. Ajoutez des personnes avec statut "Validé" et une date de début planning.
    </div>
@else
    <div class="card">
        <form action="{{ route('restrictions.update') }}" method="POST" id="restrictionsForm">
            @csrf

            <div class="grid-restrictions">
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2" style="vertical-align:middle; min-width:160px;">Personne</th>
                            @foreach(['Vendredi', 'Samedi'] as $jour)
                                <th colspan="{{ $taches->count() }}" class="jour-header">
                                    {{ $jour }}
                                </th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach(['Vendredi', 'Samedi'] as $jour)
                                @foreach($taches as $tache)
                                    <th class="tache-sub tache-{{ $tache->code }}-h">
                                        {{ $tache->libelle }}
                                    </th>
                                @endforeach
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($personnes as $personne)
                            <tr>
                                <td>
                                    {{ $personne->prenom }} {{ $personne->nom }}
                                </td>
                                @foreach(['Vendredi', 'Samedi'] as $jour)
                                    @foreach($taches as $tache)
                                        @php
                                            $autorise = $restrictionsMap[$personne->id][$tache->id][$jour] ?? true;
                                        @endphp
                                        <td>
                                            <input
                                                type="checkbox"
                                                name="checkboxes[{{ $personne->id }}][{{ $tache->id }}][{{ $jour }}]"
                                                value="1"
                                                title="{{ $personne->prenom }} — {{ $tache->libelle }} — {{ $jour }}"
                                                {{ $autorise ? 'checked' : '' }}
                                            >
                                        </td>
                                    @endforeach
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 24px; display:flex; align-items:center; gap:16px;">
                <button type="submit" class="btn btn-primary">
                    💾 Enregistrer les restrictions
                </button>
                <div style="font-size:13px; color:#718096;">
                    ✓ = autorisé &nbsp;|&nbsp; ☐ = interdit
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAll(true)">
                    Tout cocher
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAll(false)">
                    Tout décocher
                </button>
            </div>
        </form>
    </div>
@endif
@endsection

@push('scripts')
<script>
    function toggleAll(state) {
        document.querySelectorAll('#restrictionsForm input[type="checkbox"]')
            .forEach(cb => cb.checked = state);
    }
</script>
@endpush
