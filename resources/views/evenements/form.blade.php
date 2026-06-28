{{-- resources/views/evenements/form.blade.php --}}
@extends('layouts.app')

@section('title', isset($evenement) ? 'Modifier un événement — AMANA' : 'Créer un événement — AMANA')

@section('content')
@php $edit = isset($evenement); @endphp

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">
            {{ $edit ? "Modifier l'événement" : 'Créer un événement' }}
        </h1>
        @if($edit)<p class="text-[13px] text-ink-muted mt-1">{{ $evenement->nom }}</p>@endif
    </div>
    <a href="{{ route('evenements.index') }}"
       class="inline-flex items-center gap-2 px-4 py-2.5 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
        ← Retour
    </a>
</div>

<div class="max-w-[680px]">
    <form action="{{ $edit ? route('evenements.update', $evenement->id) : route('evenements.store') }}"
          method="POST">
        @csrf
        @if($edit) @method('PUT') @endif

        {{-- ── Informations générales --}}
        <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden mb-4">
            <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
                <div class="w-7 h-7 bg-amber-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">🎉</div>
                <span class="font-heading text-[14px] font-semibold text-ink">Informations de l'événement</span>
            </div>
            <div class="p-5 flex flex-col gap-4">

                {{-- Nom --}}
                <div class="flex flex-col gap-1.5">
                    <label for="nom" class="text-xs font-bold text-ink tracking-[0.2px]">
                        Nom de l'événement <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="nom" name="nom" value="{{ old('nom', $evenement->nom ?? '') }}"
                           required maxlength="150" placeholder="Ex : Vacances Noël, Ramadan, Conférence…"
                           class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                  focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] hover:border-ink-muted">
                    <span class="text-[11.5px] text-ink-muted">Le nom doit être unique et précis</span>
                    @error('nom')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="date_debut" class="text-xs font-bold text-ink tracking-[0.2px]">Date de début <span class="text-rose-500">*</span></label>
                        <input type="date" id="date_debut" name="date_debut"
                               value="{{ old('date_debut', isset($evenement) ? $evenement->date_debut?->toDateString() : '') }}"
                               required
                               class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                      focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
                        @error('date_debut')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="date_fin" class="text-xs font-bold text-ink tracking-[0.2px]">Date de fin <span class="text-rose-500">*</span></label>
                        <input type="date" id="date_fin" name="date_fin"
                               value="{{ old('date_fin', isset($evenement) ? $evenement->date_fin?->toDateString() : '') }}"
                               required
                               class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                      focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
                        @error('date_fin')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    </div>
                </div>

                {{-- Description --}}
                <div class="flex flex-col gap-1.5">
                    <label for="description" class="text-xs font-bold text-ink tracking-[0.2px]">
                        Description <span class="text-ink-muted font-normal">(optionnel)</span>
                    </label>
                    <textarea id="description" name="description" rows="3" placeholder="Notes complémentaires…"
                              class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition resize-y
                                     focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">{{ old('description', $evenement->description ?? '') }}</textarea>
                    @error('description')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                </div>

            </div>
        </div>

        {{-- ── Synchronisation Google Calendar --}}
        <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden mb-4">
            <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
                <div class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">📆</div>
                <span class="font-heading text-[14px] font-semibold text-ink">Synchronisation Google Calendar</span>
                <span class="ml-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-surface-3 text-ink-muted">optionnel</span>
            </div>
            <div class="p-5">
                <p class="text-[12.5px] text-ink-muted mb-4 leading-relaxed">
                    Si vous renseignez un nom de calendrier, un événement sera automatiquement créé (ou mis à jour / supprimé)
                    dans Google Calendar via Make.com. Laissez vide pour ne pas synchroniser.
                </p>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-ink tracking-[0.2px]">Nom du calendrier Google Calendar</label>
                    <input type="hidden" id="calendar_name" name="calendar_name"
                           value="{{ old('calendar_name', $evenement->calendar_name ?? '') }}">
                    <div style="position:relative;">
                        <button type="button" id="calendar_name_trigger" class="cs-trigger" aria-haspopup="listbox" aria-expanded="false">
                            <span class="cs-trigger-text {{ old('calendar_name', $evenement->calendar_name ?? '') ? '' : 'placeholder' }}">
                                {{ old('calendar_name', $evenement->calendar_name ?? '') ?: 'Sélectionner un calendrier…' }}
                            </span>
                            <span class="cs-trigger-arrow">▼</span>
                        </button>
                    </div>
                    <span class="text-[11.5px] text-ink-muted">
                        Sélectionnez le calendrier Google Calendar cible.
                        @if($edit && $evenement->calendar_name)
                            <strong class="text-emerald-600">✓ Synchronisation active</strong>
                        @endif
                    </span>
                    @error('calendar_name')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                </div>

                @if($edit && $evenement->calendar_name)
                    <div class="flex items-start gap-2.5 mt-3 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-lg text-[12.5px] text-emerald-800">
                        <span class="flex-shrink-0">📅</span>
                        <span>
                            Cet événement est synchronisé avec le calendrier <strong>« {{ $evenement->calendar_name }} »</strong>.
                            Modifier ou supprimer cet événement mettra également à jour Google Calendar.
                        </span>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Tâches bloquées --}}
        <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden mb-6">
            <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
                <div class="w-7 h-7 bg-rose-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">🚫</div>
                <span class="font-heading text-[14px] font-semibold text-ink">Tâches bloquées pendant cet événement</span>
            </div>
            <div class="p-5">
                <p class="text-[12.5px] text-ink-muted mb-4 leading-relaxed">
                    Cochez les tâches qui <strong class="text-ink-light">ne seront pas assignées</strong> lors de la génération du planning pour les créneaux couverts par cet événement.<br>
                    <span class="text-amber-600 font-semibold">Si aucune tâche n'est cochée</span>, l'événement est purement informatif.
                </p>

                @php
                    $tachesBloquéesIds = isset($evenement) ? $evenement->tachesBloquees->pluck('id')->toArray() : [];
                    $oldTaches = old('taches', $tachesBloquéesIds);
                @endphp

                <div class="border border-surface-border rounded-lg overflow-hidden mb-3">
                    <div class="flex items-center gap-2 px-4 py-2.5 bg-surface-2 border-b border-surface-3">
                        <button type="button" onclick="toutCocher(true)"
                                class="px-3 py-1.5 text-[12px] font-semibold text-ink-muted border border-surface-border rounded-md hover:bg-surface-3 transition-colors bg-transparent cursor-pointer min-h-[44px]">
                            Tout bloquer
                        </button>
                        <button type="button" onclick="toutCocher(false)"
                                class="px-3 py-1.5 text-[12px] font-semibold text-ink-muted border border-surface-border rounded-md hover:bg-surface-3 transition-colors bg-transparent cursor-pointer min-h-[44px]">
                            Tout libérer
                        </button>
                        <span id="blockedCount" class="ml-auto text-[12px] text-ink-muted"></span>
                    </div>

                    @foreach($taches as $tache)
                        @php $checked = in_array($tache->id, (array) $oldTaches); @endphp
                        <label id="label-{{ $tache->id }}"
                               class="tache-block-item flex items-center gap-4 px-5 py-3.5 border-b border-surface-3 last:border-0 cursor-pointer transition-colors min-h-[52px]
                                      {{ $checked ? 'bg-rose-50' : 'hover:bg-rose-50/50' }}">
                            <input type="checkbox" name="taches[]" value="{{ $tache->id }}"
                                   id="tache_{{ $tache->id }}" class="tache-checkbox w-4 h-4 accent-rose-500 cursor-pointer flex-shrink-0"
                                   {{ $checked ? 'checked' : '' }}>
                            <span class="chip-{{ $tache->code }} inline-flex items-center px-2.5 py-0.5 rounded-full text-[12.5px] font-semibold flex-1">
                                {{ $tache->libelle }}
                            </span>
                            <span class="block-status text-[11.5px] font-semibold whitespace-nowrap
                                         {{ $checked ? 'text-rose-600' : 'text-emerald-600' }}">
                                {{ $checked ? '🚫 Bloquée' : '✅ Libre' }}
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('taches')<span class="text-xs text-rose-600 block">{{ $message }}</span>@enderror
            </div>
        </div>

        {{-- Boutons --}}
        <div class="flex flex-wrap gap-3 items-center">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-accent hover:bg-accent-dark text-white font-bold text-[13.5px] rounded-lg
                           shadow-[0_3px_14px_rgba(3,105,161,0.35)] hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer min-h-[48px]">
                {{ $edit ? '💾 Enregistrer' : "➕ Créer l'événement" }}
            </button>
            <a href="{{ route('evenements.index') }}"
               class="inline-flex items-center gap-2 px-6 py-3 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink font-semibold text-[13.5px] rounded-lg transition-colors no-underline min-h-[48px]">
                Annuler
            </a>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
    document.getElementById('date_debut').addEventListener('change', function () {
        const fin = document.getElementById('date_fin');
        if (!fin.value || fin.value < this.value) fin.value = this.value;
        fin.min = this.value;
    });

    function updateStatus() {
        const checkboxes = document.querySelectorAll('.tache-checkbox');
        let blocked = 0;
        checkboxes.forEach(cb => {
            const label = cb.closest('.tache-block-item');
            const status = label.querySelector('.block-status');
            if (cb.checked) {
                blocked++;
                label.classList.add('bg-rose-50');
                status.textContent = '🚫 Bloquée';
                status.className = status.className.replace('text-emerald-600','') + ' text-rose-600';
            } else {
                label.classList.remove('bg-rose-50');
                status.textContent = '✅ Libre';
                status.className = status.className.replace('text-rose-600','') + ' text-emerald-600';
            }
        });
        const el = document.getElementById('blockedCount');
        if (blocked === 0) { el.textContent = 'Événement informatif'; el.className = 'ml-auto text-[12px] text-amber-600 font-semibold'; }
        else if (blocked === checkboxes.length) { el.textContent = 'Toutes les tâches bloquées'; el.className = 'ml-auto text-[12px] text-rose-600 font-semibold'; }
        else { el.textContent = `${blocked} tâche${blocked > 1 ? 's' : ''} bloquée${blocked > 1 ? 's' : ''}`; el.className = 'ml-auto text-[12px] text-amber-600 font-semibold'; }
    }

    function toutCocher(state) {
        document.querySelectorAll('.tache-checkbox').forEach(cb => cb.checked = state);
        updateStatus();
    }

    document.querySelectorAll('.tache-checkbox').forEach(cb => cb.addEventListener('change', updateStatus));
    updateStatus();
</script>

<script src="{{ asset('js/calendar-select.js') }}"></script>
<script>
    CalendarSelect.init({
        inputId:      'calendar_name',
        triggerId:    'calendar_name_trigger',
        apiUrl:       '{{ route("calendriers.index") }}',
        currentValue: '{{ addslashes(old("calendar_name", $evenement->calendar_name ?? "")) }}',
    });
</script>
@endpush
