{{-- resources/views/restrictions/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Restrictions — AMANA')

@push('styles')
<style>
    .restrictions-table th.jour-header {
        text-align: center;
        background: linear-gradient(135deg, var(--primary) 0%, var(--violet) 100%);
        color: white;
        font-size: 12px;
        padding: 10px 8px;
        text-transform: none;
        letter-spacing: 0;
        font-weight: 700;
    }
    .restrictions-table th.sub-header {
        text-align: center;
        font-size: 10.5px;
        padding: 7px 4px;
        background: var(--surface-2);
        white-space: nowrap;
        font-weight: 700;
        letter-spacing: 0.3px;
    }
    .restrictions-table td {
        text-align: center;
        padding: 9px 6px;
    }
    .restrictions-table td:first-child {
        text-align: left;
        padding-left: 18px;
        font-weight: 600;
        white-space: nowrap;
        color: var(--ink);
    }
    .restrictions-table input[type="checkbox"] {
        width: 17px; height: 17px;
        cursor: pointer;
        accent-color: var(--primary);
    }
    .sub-entree     { color: #2563eb; }
    .sub-mektaba    { color: #059669; }
    .sub-salle      { color: #d97706; }
    .sub-amana_food { color: #e11d48; }

    .toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--surface-3);
    }
    .hint-text {
        margin-left: auto;
        font-size: 12.5px;
        color: var(--ink-muted);
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .hint-item { display: flex; align-items: center; gap: 5px; }
</style>
@endpush

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Restrictions de disponibilité</div>
        <div class="page-subtitle">
            Case cochée ✓ = la personne <strong>peut</strong> effectuer la tâche ce jour-là
        </div>
    </div>
</div>

@if($personnes->isEmpty())
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">🔒</div>
            <div class="empty-title">Aucun membre actif</div>
            <div class="empty-desc">Ajoutez des personnes avec statut "Validé" et une date de début planning.</div>
            <a href="{{ route('personnes.create') }}" class="btn btn-primary">+ Ajouter une personne</a>
        </div>
    </div>
@else
    <div class="card">
        <div class="card-body" style="padding:0;">
            <form action="{{ route('restrictions.update') }}" method="POST" id="restrictionsForm">
                @csrf
                <div style="overflow-x:auto;">
                    <table class="restrictions-table" style="min-width:700px;">
                        <thead>
                            <tr>
                                <th rowspan="2" style="vertical-align:middle; min-width:170px; text-align:left; padding-left:18px;">
                                    Personne
                                </th>
                                @foreach(['Vendredi', 'Samedi'] as $jour)
                                    <th colspan="{{ $taches->count() }}" class="jour-header">
                                        {{ $jour }}
                                    </th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach(['Vendredi', 'Samedi'] as $jour)
                                    @foreach($taches as $tache)
                                        <th class="sub-header sub-{{ $tache->code }}">
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
                                        <div style="display:flex; align-items:center; gap:9px;">
                                            <div style="
                                                width:28px; height:28px;
                                                background:linear-gradient(135deg,var(--primary),var(--violet));
                                                border-radius:50%;
                                                display:flex; align-items:center; justify-content:center;
                                                color:white; font-size:11px; font-weight:700;
                                                flex-shrink:0;
                                            ">{{ strtoupper(substr($personne->prenom, 0, 1)) }}</div>
                                            {{ $personne->prenom }} {{ $personne->nom }}
                                        </div>
                                    </td>
                                    @foreach(['Vendredi', 'Samedi'] as $jour)
                                        @foreach($taches as $tache)
                                            @php $autorise = $restrictionsMap[$personne->id][$tache->id][$jour] ?? true; @endphp
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

                <div style="padding:20px 24px;">
                    <div class="toolbar">
                        <button type="submit" class="btn btn-primary">
                            💾 Enregistrer les restrictions
                        </button>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="toggleAll(true)">
                            Tout cocher
                        </button>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="toggleAll(false)">
                            Tout décocher
                        </button>
                        <div class="hint-text">
                            <div class="hint-item">
                                <input type="checkbox" checked disabled style="width:14px;height:14px;accent-color:var(--primary);">
                                <span>Autorisé</span>
                            </div>
                            <div class="hint-item">
                                <input type="checkbox" disabled style="width:14px;height:14px;">
                                <span>Interdit</span>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
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
