{{-- resources/views/personnes/form.blade.php --}}
@extends('layouts.app')

@section('title', isset($personne) ? 'Modifier une personne — AMANA' : 'Ajouter une personne — AMANA')

@section('content')
@php $edit = isset($personne); @endphp

<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">{{ $edit ? 'Modifier ' . $personne->prenom . ' ' . $personne->nom : 'Ajouter une personne' }}</div>
        @if($edit)
            <div class="page-subtitle">Modification des informations</div>
        @endif
    </div>
    <a href="{{ route('personnes.index') }}" class="btn btn-secondary">← Retour</a>
</div>

<div style="max-width: 780px;">
    <form action="{{ $edit ? route('personnes.update', $personne->id) : route('personnes.store') }}"
          method="POST">
        @csrf
        @if($edit) @method('PUT') @endif

        {{-- Identity --}}
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon" style="background:var(--violet-bg);">👤</div>
                    Identité
                </div>
            </div>
            <div class="card-body">
                <div class="form-grid" style="gap:18px;">
                    <div class="form-group">
                        <label for="prenom">Prénom <span class="req">*</span></label>
                        <input type="text" id="prenom" name="prenom"
                               value="{{ old('prenom', $personne->prenom ?? '') }}"
                               required maxlength="100" placeholder="Prénom">
                        @error('prenom')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="nom">Nom <span class="req">*</span></label>
                        <input type="text" id="nom" name="nom"
                               value="{{ old('nom', $personne->nom ?? '') }}"
                               required maxlength="100" placeholder="Nom de famille">
                        @error('nom')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="email">Email <span class="req">*</span></label>
                        <input type="email" id="email" name="email"
                               value="{{ old('email', $personne->email ?? '') }}"
                               required maxlength="255" placeholder="email@exemple.fr">
                        @error('email')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone"
                               value="{{ old('telephone', $personne->telephone ?? '') }}"
                               maxlength="20" placeholder="+33 6 00 00 00 00">
                        @error('telephone')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Planning --}}
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon" style="background:var(--sky-bg);">📅</div>
                    Planning &amp; Statut
                </div>
            </div>
            <div class="card-body">
                <div class="form-grid" style="gap:18px;">
                    <div class="form-group">
                        <label for="statut">Statut <span class="req">*</span></label>
                        <select id="statut" name="statut" required>
                            @foreach($statuts as $s)
                                <option value="{{ $s }}"
                                    {{ old('statut', $personne->statut ?? 'En attente') === $s ? 'selected' : '' }}>
                                    {{ $s }}
                                </option>
                            @endforeach
                        </select>
                        @error('statut')<span class="form-error">{{ $message }}</span>@enderror
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
                        @error('id_vehicule')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="date_debut_planning">Date de début planning</label>
                        <input type="date" id="date_debut_planning" name="date_debut_planning"
                               value="{{ old('date_debut_planning', isset($personne) ? $personne->date_debut_planning?->toDateString() : '') }}">
                        <span class="form-hint">Laisser vide si non membre officiel</span>
                        @error('date_debut_planning')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="date_inscription_benevole">Date inscription bénévole</label>
                        <input type="date" id="date_inscription_benevole" name="date_inscription_benevole"
                               value="{{ old('date_inscription_benevole', isset($personne) ? $personne->date_inscription_benevole?->toDateString() : '') }}">
                        <span class="form-hint">Laisser vide si non bénévole</span>
                        @error('date_inscription_benevole')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Options --}}
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon" style="background:var(--emerald-bg);">⚙️</div>
                    Options
                </div>
            </div>
            <div class="card-body">
                <div class="checkbox-wrap">
                    <input type="hidden" name="tirelire" value="0">
                    <input type="checkbox" id="tirelire" name="tirelire" value="1"
                           {{ old('tirelire', $personne->tirelire ?? false) ? 'checked' : '' }}>
                    <label for="tirelire">
                        <span style="font-weight:600;">Participe à la tirelire</span>
                        <span style="display:block; font-size:12px; font-weight:400; color:var(--ink-muted); margin-top:2px;">
                            Cocher si cette personne contribue à la tirelire commune
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:12px;">
            <button type="submit" class="btn btn-primary btn-lg">
                {{ $edit ? '💾 Enregistrer les modifications' : '➕ Créer la personne' }}
            </button>
            <a href="{{ route('personnes.index') }}" class="btn btn-secondary btn-lg">Annuler</a>
        </div>
    </form>
</div>
@endsection
