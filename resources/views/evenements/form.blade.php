{{-- resources/views/evenements/form.blade.php --}}
@extends('layouts.app')

@section('title', isset($evenement) ? 'Modifier un événement — AMANA' : 'Créer un événement — AMANA')

@section('content')
@php $edit = isset($evenement); @endphp

<div class="page-header">
    <div>
        <div class="page-title">
            {{ $edit ? '✏️ Modifier : ' . $evenement->nom : '➕ Créer un événement' }}
        </div>
    </div>
    <a href="{{ route('evenements.index') }}" class="btn btn-secondary">← Retour</a>
</div>

<div style="max-width: 640px;">
    <div class="card">
        <form
            action="{{ $edit ? route('evenements.update', $evenement->id) : route('evenements.store') }}"
            method="POST"
        >
            @csrf
            @if($edit) @method('PUT') @endif

            <div class="form-group" style="margin-bottom:18px;">
                <label for="nom">Nom de l'événement <span class="required">*</span></label>
                <input type="text" id="nom" name="nom"
                       value="{{ old('nom', isset($evenement) ? $evenement->nom : '') }}"
                       required maxlength="150"
                       placeholder="Ex : Vacances Noël, Ramadan, Conférence...">
                <span class="form-hint">Le nom doit être unique et précis</span>
                @error('nom') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-grid" style="margin-bottom:18px;">
                <div class="form-group">
                    <label for="date_debut">Date de début <span class="required">*</span></label>
                    <input type="date" id="date_debut" name="date_debut"
                           value="{{ old('date_debut', isset($evenement) ? $evenement->date_debut?->toDateString() : '') }}"
                           required>
                    @error('date_debut') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label for="date_fin">Date de fin <span class="required">*</span></label>
                    <input type="date" id="date_fin" name="date_fin"
                           vvalue="{{ old('date_fin', isset($evenement) ? $evenement->date_fin?->toDateString() : '') }}"
                           required>
                    @error('date_fin') <span class="form-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="form-group" style="margin-bottom:18px;">
                <label for="description">Description (optionnel)</label>
                <textarea id="description" name="description" rows="3"
                          placeholder="Notes complémentaires...">{{ old('description', isset($evenement) ? $evenement->description : '') }}</textarea>
                @error('description') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div style="
                background:#f7fafc;
                border:1px solid #e2e8f0;
                border-radius:8px;
                padding:16px;
                margin-bottom:24px;
            ">
                <div style="font-size:13px; font-weight:600; color:#2d3748; margin-bottom:12px;">
                    Options
                </div>
                <div class="checkbox-group" style="margin-bottom:10px;">
                    <input type="hidden" name="bloque_planning" value="0">
                    <input type="checkbox" id="bloque_planning" name="bloque_planning" value="1"
{{ old('bloque_planning', isset($evenement) ? $evenement->bloque_planning : false) ? 'checked' : '' }}
                    <label for="bloque_planning">
                        ⛔ Bloque le planning
                        <span style="font-weight:400; color:#718096; font-size:12px;">
                            — aucune tâche ne sera assignée pendant cette période
                        </span>
                    </label>
                </div>
                <div class="checkbox-group">
                    <input type="hidden" name="necessite_benevoles" value="0">
                    <input type="checkbox" id="necessite_benevoles" name="necessite_benevoles" value="1"
                           {{ old('necessite_benevoles', isset($evenement) ? $evenement->necessite_benevoles : false) ? 'checked' : '' }}>
                    <label for="necessite_benevoles">
                        👥 Nécessite des bénévoles
                        <span style="font-weight:400; color:#718096; font-size:12px;">
                            — les bénévoles peuvent s'inscrire
                        </span>
                    </label>
                </div>
            </div>

            <div style="display:flex; gap:12px;">
                <button type="submit" class="btn btn-primary">
                    {{ $edit ? '💾 Enregistrer' : '➕ Créer l\'événement' }}
                </button>
                <a href="{{ route('evenements.index') }}" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('date_debut').addEventListener('change', function() {
        const fin = document.getElementById('date_fin');
        if (!fin.value || fin.value < this.value) fin.value = this.value;
        fin.min = this.value;
    });
</script>
@endpush
