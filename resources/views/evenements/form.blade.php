{{-- resources/views/evenements/form.blade.php --}}
@extends('layouts.app')

@section('title', isset($evenement) ? 'Modifier un événement — AMANA' : 'Créer un événement — AMANA')

@section('content')
@php $edit = isset($evenement); @endphp

<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">{{ $edit ? 'Modifier l\'événement' : 'Créer un événement' }}</div>
        @if($edit)
            <div class="page-subtitle">{{ $evenement->nom }}</div>
        @endif
    </div>
    <a href="{{ route('evenements.index') }}" class="btn btn-secondary">← Retour</a>
</div>

<div style="max-width: 680px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon" style="background:var(--amber-bg);">🎉</div>
                Informations de l'événement
            </div>
        </div>
        <div class="card-body">
            <form action="{{ $edit ? route('evenements.update', $evenement->id) : route('evenements.store') }}"
                  method="POST">
                @csrf
                @if($edit) @method('PUT') @endif

                <div class="form-group" style="margin-bottom:20px;">
                    <label for="nom">Nom de l'événement <span class="req">*</span></label>
                    <input type="text" id="nom" name="nom"
                           value="{{ old('nom', $evenement->nom ?? '') }}"
                           required maxlength="150"
                           placeholder="Ex : Vacances Noël, Ramadan, Conférence…">
                    <span class="form-hint">Le nom doit être unique et précis</span>
                    @error('nom')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-grid" style="margin-bottom:20px;">
                    <div class="form-group">
                        <label for="date_debut">Date de début <span class="req">*</span></label>
                        <input type="date" id="date_debut" name="date_debut"
                               value="{{ old('date_debut', isset($evenement) ? $evenement->date_debut?->toDateString() : '') }}"
                               required>
                        @error('date_debut')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="date_fin">Date de fin <span class="req">*</span></label>
                        <input type="date" id="date_fin" name="date_fin"
                               value="{{ old('date_fin', isset($evenement) ? $evenement->date_fin?->toDateString() : '') }}"
                               required>
                        @error('date_fin')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:22px;">
                    <label for="description">Description <span style="color:var(--ink-muted); font-weight:400;">(optionnel)</span></label>
                    <textarea id="description" name="description" rows="3"
                              placeholder="Notes complémentaires…">{{ old('description', $evenement->description ?? '') }}</textarea>
                    @error('description')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="divider"></div>

                <div style="margin-bottom:24px;">
                    <div style="font-size:12.5px; font-weight:700; color:var(--ink); margin-bottom:14px; text-transform:uppercase; letter-spacing:0.5px;">
                        Options
                    </div>
                    <div style="
                        background:var(--surface-2);
                        border:1px solid var(--surface-3);
                        border-radius:var(--radius-lg);
                        padding:4px 0;
                    ">
                        <div class="checkbox-wrap" style="padding:12px 18px; border-bottom:1px solid var(--surface-3);">
                            <input type="hidden" name="bloque_planning" value="0">
                            <input type="checkbox" id="bloque_planning" name="bloque_planning" value="1"
                                   {{ old('bloque_planning', isset($evenement) ? $evenement->bloque_planning : false) ? 'checked' : '' }}>
                            <label for="bloque_planning" style="display:flex; flex-direction:column; gap:2px;">
                                <span>⛔ Bloque le planning</span>
                                <span style="font-size:12px; font-weight:400; color:var(--ink-muted);">Aucune tâche ne sera assignée pendant cette période</span>
                            </label>
                        </div>
                        <div class="checkbox-wrap" style="padding:12px 18px;">
                            <input type="hidden" name="necessite_benevoles" value="0">
                            <input type="checkbox" id="necessite_benevoles" name="necessite_benevoles" value="1"
                                   {{ old('necessite_benevoles', isset($evenement) ? $evenement->necessite_benevoles : false) ? 'checked' : '' }}>
                            <label for="necessite_benevoles" style="display:flex; flex-direction:column; gap:2px;">
                                <span>👥 Nécessite des bénévoles</span>
                                <span style="font-size:12px; font-weight:400; color:var(--ink-muted);">Les bénévoles peuvent s'inscrire pour cet événement</span>
                            </label>
                        </div>
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
