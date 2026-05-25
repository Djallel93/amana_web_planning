{{-- resources/views/personnes/form.blade.php --}}
{{-- Utilisé pour la création ET l'édition --}}
{{-- Si $personne est défini → édition, sinon → création --}}
@extends('layouts.app')

@section('title', isset($personne) ? 'Modifier une personne — AMANA' : 'Ajouter une personne — AMANA')

@section('content')
@php $edit = isset($personne); @endphp

<div class="page-header">
    <div>
        <div class="page-title">
            {{ $edit ? '✏️ Modifier ' . $personne->prenom . ' ' . $personne->nom : '➕ Ajouter une personne' }}
        </div>
    </div>
    <a href="{{ route('personnes.index') }}" class="btn btn-secondary">← Retour</a>
</div>

<div style="max-width: 760px;">
    <div class="card">
        <form
            action="{{ $edit ? route('personnes.update', $personne->id) : route('personnes.store') }}"
            method="POST"
        >
            @csrf
            @if($edit) @method('PUT') @endif

            {{-- Identité --}}
            <div class="card-Fheader">
                <span class="card-title">👤 Identité</span>
            </div>
            <div class="form-grid" style="margin-bottom: 24px;">
                <div class="form-group">
                    <label for="prenom">Prénom <span class="required">*</span></label>
                    <input type="text" id="prenom" name="prenom"
                           value="{{ old('prenom', $personne->prenom ?? '') }}" required maxlength="100">
                    @error('prenom') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label for="nom">Nom <span class="required">*</span></label>
                    <input type="text" id="nom" name="nom"
                           value="{{ old('nom', $personne->nom ?? '') }}" required maxlength="100">
                    @error('nom') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email"
                           value="{{ old('email', $personne->email ?? '') }}" required maxlength="255">
                    @error('email') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label for="telephone">Téléphone</label>
                    <input type="tel" id="telephone" name="telephone"
                           value="{{ old('telephone', $personne->telephone ?? '') }}" maxlength="20">
                    @error('telephone') <span class="form-error">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Planning --}}
            <div class="card-header" style="margin-top: 8px;">
                <span class="card-title">📅 Planning</span>
            </div>
            <div class="form-grid" style="margin-bottom: 24px;">
                <div class="form-group">
                    <label for="statut">Statut <span class="required">*</span></label>
                    <select id="statut" name="statut" required>
                        @foreach($statuts as $s)
                            <option value="{{ $s }}"
                                {{ old('statut', $personne->statut ?? 'En attente') === $s ? 'selected' : '' }}>
                                {{ $s }}
                            </option>
                        @endforeach
                    </select>
                    @error('statut') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label for="date_debut_planning">Date de début (planning)</label>
                    <input type="date" id="date_debut_planning" name="date_debut_planning"
value="{{ old('date_debut_planning', isset($personne) ? $personne->date_debut_planning?->toDateString() : '') }}"
                    <span class="form-hint">Laisser vide si non membre officiel</span>
                    @error('date_debut_planning') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label for="date_inscription_benevole">Date d'inscription bénévole</label>
                    <input type="date" id="date_inscription_benevole" name="date_inscription_benevole"
                           value="{{ old('date_inscription_benevole', isset($personne) ? $personne->date_inscription_benevole?->toDateString() : '') }}"
                    <span class="form-hint">Laisser vide si non bénévole</span>
                    @error('date_inscription_benevole') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label for="id_vehicule">Véhicule</label>
                    <select id="id_vehicule" name="id_vehicule">
                        <option value="">— Aucun —</option>
                        @foreach($vehicules as $v)
                            <option value="{{ $v->id }}"
                                {{ old('id_vehicule', $personne->id_vehicule ?? '') == $v->id ? 'selected' : '' }}>
                                {{ $v->type }} ({{ $v->capacite_kg }} kg)
                            </option>
                        @endforeach
                    </select>
                    @error('id_vehicule') <span class="form-error">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Options --}}
            <div class="card-header" style="margin-top: 8px;">
                <span class="card-title">⚙️ Options</span>
            </div>
            <div style="margin-bottom: 28px;">
                <div class="checkbox-group">
                    <input type="hidden" name="tirelire" value="0">
                    <input type="checkbox" id="tirelire" name="tirelire" value="1"
                           {{ old('tirelire', $personne->tirelire ?? false) ? 'checked' : '' }}>
                    <label for="tirelire">Participe à la tirelire</label>
                </div>
            </div>

            <div style="display:flex; gap:12px;">
                <button type="submit" class="btn btn-primary">
                    {{ $edit ? '💾 Enregistrer les modifications' : '➕ Créer la personne' }}
                </button>
                <a href="{{ route('personnes.index') }}" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
@endsection
