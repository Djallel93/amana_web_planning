{{-- resources/views/personnes/form.blade.php --}}
@extends('layouts.app')

@section('title', isset($personne) ? 'Modifier une personne — AMANA' : 'Ajouter une personne — AMANA')

@section('content')
@php $edit = isset($personne); @endphp

{{-- En-tête --}}
<div class="flex flex-wrap items-center justify-between gap-4 mb-7">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">
            {{ $edit ? 'Modifier ' . $personne->prenom . ' ' . $personne->nom : 'Ajouter une personne' }}
        </h1>
        @if($edit)
            <p class="text-[13px] text-ink-muted mt-1">Modification des informations</p>
        @endif
    </div>
    <a href="{{ route('personnes.index') }}"
       class="inline-flex items-center gap-2 px-4 py-2.5 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
        ← Retour
    </a>
</div>

<div class="max-w-[760px]">
    <form action="{{ $edit ? route('personnes.update', $personne->id) : route('personnes.store') }}"
          method="POST" id="personneForm">
        @csrf
        @if($edit) @method('PUT') @endif

        {{-- Identité --}}
        <div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden mb-4">
            <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
                <div class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">👤</div>
                <span class="font-heading text-[14px] font-semibold text-ink">Identité</span>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <div class="flex flex-col gap-1.5">
                        <label for="prenom" class="text-xs font-bold text-ink tracking-[0.2px]">
                            Prénom <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="prenom" name="prenom"
                               value="{{ old('prenom', $personne->prenom ?? '') }}"
                               required maxlength="100" placeholder="Prénom"
                               class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                      focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] hover:border-ink-muted">
                        @error('prenom')<span class="text-xs text-rose-600 mt-0.5">{{ $message }}</span>@enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="nom" class="text-xs font-bold text-ink tracking-[0.2px]">
                            Nom <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="nom" name="nom"
                               value="{{ old('nom', $personne->nom ?? '') }}"
                               required maxlength="100" placeholder="Nom de famille"
                               class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                      focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] hover:border-ink-muted">
                        @error('nom')<span class="text-xs text-rose-600 mt-0.5">{{ $message }}</span>@enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="email" class="text-xs font-bold text-ink tracking-[0.2px]">
                            Email <span class="text-rose-500">*</span>
                        </label>
                        <input type="email" id="email" name="email"
                               value="{{ old('email', $personne->email ?? '') }}"
                               required maxlength="255" placeholder="email@exemple.fr" autocomplete="off"
                               class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                      focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] hover:border-ink-muted">
                        @error('email')<span class="text-xs text-rose-600 mt-0.5">{{ $message }}</span>@enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="telephone" class="text-xs font-bold text-ink tracking-[0.2px]">
                            Téléphone <span class="text-ink-muted font-normal">(optionnel)</span>
                        </label>
                        <input type="tel" id="telephone" name="telephone"
                               value="{{ old('telephone', $personne->telephone ?? '') }}"
                               maxlength="20" placeholder="06 12 34 56 78"
                               class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                      focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] hover:border-ink-muted">
                        <span class="text-[11.5px] text-ink-muted">
                            Formats : <code class="bg-surface-3 px-1 rounded">06 12 34 56 78</code>
                            &nbsp;·&nbsp; <code class="bg-surface-3 px-1 rounded">+33 6 12 34 56 78</code>
                        </span>
                        @error('telephone')<span class="text-xs text-rose-600 mt-0.5">{{ $message }}</span>@enderror
                    </div>

                </div>
            </div>
        </div>

        {{-- Rôle & Statut --}}
        <div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden mb-4">
            <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
                <div class="w-7 h-7 bg-rose-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">🛡️</div>
                <span class="font-heading text-[14px] font-semibold text-ink">Rôle &amp; Statut</span>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <div class="flex flex-col gap-1.5">
                        <label for="role" class="text-xs font-bold text-ink tracking-[0.2px]">
                            Rôle planning <span class="text-rose-500">*</span>
                        </label>
                        <select id="role" name="role" required
                                class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                       focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] hover:border-ink-muted cursor-pointer">
                            @foreach($roles as $r)
                                @php
                                    $selected = old('role', $currentRole ?? 'membre') === $r->code;
                                    $labels = [
                                        'admin'        => '🛡️ Administrateur',
                                        'gestionnaire' => '⚙️ Gestionnaire',
                                        'membre'       => '👤 Membre',
                                        'benevole'     => '🤝 Bénévole',
                                    ];
                                @endphp
                                <option value="{{ $r->code }}" {{ $selected ? 'selected' : '' }}>
                                    {{ $labels[$r->code] ?? $r->libelle }}
                                </option>
                            @endforeach
                        </select>
                        <span class="text-[11.5px] text-ink-muted leading-relaxed">
                            Admin : accès complet &nbsp;·&nbsp;
                            Gestionnaire : planning + événements &nbsp;·&nbsp;
                            Membre : lecture + ses données
                        </span>
                        @error('role')<span class="text-xs text-rose-600 mt-0.5">{{ $message }}</span>@enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="statut" class="text-xs font-bold text-ink tracking-[0.2px]">
                            Statut <span class="text-rose-500">*</span>
                        </label>
                        <select id="statut" name="statut" required
                                class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                       focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] hover:border-ink-muted cursor-pointer">
                            @foreach($statuts as $s)
                                @php
                                    $icons = [
                                        'Validé'     => '✅',
                                        'En attente' => '⏳',
                                        'Suspendu'   => '⏸️',
                                        'Archivé'    => '📦',
                                    ];
                                @endphp
                                <option value="{{ $s }}" {{ old('statut', $personne->statut ?? 'En attente') === $s ? 'selected' : '' }}>
                                    {{ ($icons[$s] ?? '') . ' ' . $s }}
                                </option>
                            @endforeach
                        </select>
                        @error('statut')<span class="text-xs text-rose-600 mt-0.5">{{ $message }}</span>@enderror
                    </div>

                </div>
            </div>
        </div>

        {{-- Planning --}}
        <div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden mb-6">
            <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
                <div class="w-7 h-7 bg-emerald-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">📅</div>
                <span class="font-heading text-[14px] font-semibold text-ink">Planning</span>
            </div>
            <div class="p-5">
                <div class="flex flex-col gap-1.5 max-w-[260px]">
                    <label for="date_debut_planning" class="text-xs font-bold text-ink tracking-[0.2px]">
                        Date de début planning
                    </label>
                    <input type="date" id="date_debut_planning" name="date_debut_planning"
                           value="{{ old('date_debut_planning', isset($personne) ? $personne->date_debut_planning?->toDateString() : '') }}"
                           class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                  focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] hover:border-ink-muted">
                    <span class="text-[11.5px] text-ink-muted leading-relaxed">
                        Laisser vide pour inclure ce membre immédiatement lors de la génération.
                    </span>
                    @error('date_debut_planning')<span class="text-xs text-rose-600 mt-0.5">{{ $message }}</span>@enderror
                </div>
            </div>
        </div>

        {{-- Boutons --}}
        <div class="flex flex-wrap gap-3 items-center">
            <button type="submit" id="submitBtn"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-accent hover:bg-accent-dark text-white font-bold text-[13.5px] rounded-lg
                           shadow-[0_3px_14px_rgba(3,105,161,0.35)] hover:shadow-[0_6px_20px_rgba(3,105,161,0.45)]
                           hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer min-h-[48px]">
                {{ $edit ? '💾 Enregistrer les modifications' : '➕ Créer la personne' }}
            </button>
            <a href="{{ route('personnes.index') }}"
               class="inline-flex items-center gap-2 px-6 py-3 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink font-semibold text-[13.5px] rounded-lg transition-colors no-underline min-h-[48px]">
                Annuler
            </a>
        </div>

    </form>
</div>

@endsection

@push('scripts')
<script>
    document.getElementById('personneForm').addEventListener('submit', function () {
        const btn = document.getElementById('submitBtn');
        if (!this.checkValidity()) return;
        btn.disabled = true;
        btn.innerHTML = '⏳ {{ $edit ? "Enregistrement…" : "Création en cours…" }}';
        btn.style.opacity = '0.75';
        btn.style.cursor = 'not-allowed';
    });
</script>
@endpush
