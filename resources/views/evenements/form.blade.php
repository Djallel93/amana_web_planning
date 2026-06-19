{{-- resources/views/evenements/form.blade.php --}}
@extends('layouts.app')

@section('title', isset($evenement) ? 'Modifier un événement — AMANA' : 'Créer un événement — AMANA')

@section('content')
    @php $edit = isset($evenement); @endphp

    @include('partials.tache-colors')

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ $edit ? 'Modifier l\'événement' : 'Créer un événement' }}</div>
            @if($edit)
                <div class="page-subtitle">{{ $evenement->nom }}</div>
            @endif
        </div>
        <a href="{{ route('evenements.index') }}" class="btn btn-secondary">← Retour</a>
    </div>

    <div style="max-width:640px;">
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

                    {{-- Nom --}}
                    <div class="form-group" style="margin-bottom:18px;">
                        <label for="nom">Nom de l'événement <span class="req">*</span></label>
                        <input type="text" id="nom" name="nom" value="{{ old('nom', $evenement->nom ?? '') }}" required
                            maxlength="150" placeholder="Ex : Vacances Noël, Ramadan, Conférence…">
                        <span class="form-hint">Le nom doit être unique et précis</span>
                        @error('nom')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    {{-- Dates --}}
                    <div class="form-grid" style="margin-bottom:18px;">
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

                    {{-- Description --}}
                    <div class="form-group" style="margin-bottom:22px;">
                        <label for="description">Description
                            <span style="color:var(--ink-muted);font-weight:400;">(optionnel)</span>
                        </label>
                        <textarea id="description" name="description" rows="3"
                            placeholder="Notes complémentaires…">{{ old('description', $evenement->description ?? '') }}</textarea>
                        @error('description')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="divider"></div>

                    {{-- ── Synchronisation Google Calendar ──────────────────────────── --}}
                    <div style="margin-bottom:24px;">
                        <div style="margin-bottom:10px;">
                            <div style="font-size:13.5px;font-weight:700;color:var(--ink);margin-bottom:4px;display:flex;align-items:center;gap:8px;">
                                📆 Synchronisation Google Calendar
                                <span style="font-size:11px;font-weight:500;color:var(--ink-muted);background:var(--surface-3);padding:2px 8px;border-radius:20px;">optionnel</span>
                            </div>
                            <div style="font-size:12.5px;color:var(--ink-muted);line-height:1.6;">
                                Si vous renseignez un nom de calendrier, un événement sera automatiquement créé
                                (ou mis à jour / supprimé) dans Google Calendar via Make.com.
                                Laissez vide pour ne pas synchroniser.
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="calendar_name">Nom du calendrier Google Calendar</label>
                            <input
                                type="text"
                                id="calendar_name"
                                name="calendar_name"
                                value="{{ old('calendar_name', $evenement->calendar_name ?? '') }}"
                                maxlength="200"
                                placeholder="Ex : AMANA - Événements, AMANA - Planning…"
                                autocomplete="off">
                            <span class="form-hint">
                                Doit correspondre exactement au nom du calendrier dans Google Calendar.
                                @if($edit && $evenement->calendar_name)
                                    <strong style="color:var(--emerald);">✓ Synchronisation active</strong>
                                @endif
                            </span>
                            @error('calendar_name')<span class="form-error">{{ $message }}</span>@enderror
                        </div>

                        {{-- Visual indicator when calendar sync is active --}}
                        @if($edit && $evenement->calendar_name)
                            <div style="
                                background:var(--emerald-bg);
                                border:1px solid var(--emerald-border);
                                border-radius:var(--radius);
                                padding:10px 14px;
                                font-size:12.5px;
                                color:#065f46;
                                display:flex;
                                align-items:center;
                                gap:9px;
                                margin-top:10px;">
                                <span style="flex-shrink:0;">📅</span>
                                <span>
                                    Cet événement est synchronisé avec le calendrier
                                    <strong>« {{ $evenement->calendar_name }} »</strong>.
                                    Modifier ou supprimer cet événement mettra également à jour Google Calendar.
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="divider"></div>

                    {{-- ── Tâches bloquées ───────────────────────────────────────────── --}}
                    <div style="margin-bottom:24px;">
                        <div style="margin-bottom:10px;">
                            <div style="font-size:13.5px;font-weight:700;color:var(--ink);margin-bottom:4px;">
                                🚫 Tâches bloquées pendant cet événement
                            </div>
                            <div style="font-size:12.5px;color:var(--ink-muted);line-height:1.6;">
                                Cochez les tâches qui <strong>ne seront pas assignées</strong> lors de la génération du
                                planning pour les créneaux couverts par cet événement.<br>
                                <span style="color:var(--amber);font-weight:600;">Si aucune tâche n'est cochée</span>,
                                l'événement est purement informatif — il apparaîtra dans la bannière de la semaine
                                sans affecter les assignations.
                            </div>
                        </div>

                        @php
                            $tachesBloquéesIds = isset($evenement)
                                ? $evenement->tachesBloquees->pluck('id')->toArray()
                                : [];
                            $oldTaches = old('taches', $tachesBloquéesIds);
                        @endphp

                        <div style="background:var(--surface-2);border:1px solid var(--surface-border);border-radius:var(--radius);overflow:hidden;">
                            <div style="padding:10px 16px;border-bottom:1px solid var(--surface-3);display:flex;align-items:center;gap:10px;">
                                <button type="button" class="btn btn-ghost btn-sm" onclick="toutCocher(true)">Tout bloquer</button>
                                <button type="button" class="btn btn-ghost btn-sm" onclick="toutCocher(false)">Tout libérer</button>
                                <span id="blockedCount" style="margin-left:auto;font-size:12px;color:var(--ink-muted);"></span>
                            </div>

                            @foreach($taches as $tache)
                                @php
                                    $style = $tacheColors[$tache->code] ?? ['bg' => 'var(--surface-3)', 'color' => 'var(--ink)', 'icon' => '•'];
                                    $checked = in_array($tache->id, (array) $oldTaches);
                                @endphp
                                <label class="tache-block-item {{ $checked ? 'checked' : '' }}" id="label-{{ $tache->id }}"
                                    style="display:flex;align-items:center;gap:14px;padding:12px 16px;border-bottom:1px solid var(--surface-3);cursor:pointer;transition:background 0.15s;">
                                    <input type="checkbox" name="taches[]" value="{{ $tache->id }}" id="tache_{{ $tache->id }}"
                                        class="tache-checkbox" {{ $checked ? 'checked' : '' }}
                                        style="width:16px;height:16px;accent-color:var(--rose);cursor:pointer;flex-shrink:0;-webkit-appearance:auto;appearance:auto;">
                                    <div style="display:flex;align-items:center;gap:9px;flex:1;">
                                        <span style="
                                            display:inline-flex;align-items:center;gap:5px;
                                            padding:3px 11px;border-radius:20px;
                                            font-size:12.5px;font-weight:600;
                                            background:{{ $style['bg'] }};color:{{ $style['color'] }};
                                        ">
                                            {{ $style['icon'] }} {{ $tache->libelle }}
                                        </span>
                                    </div>
                                    <span class="block-status"
                                        style="font-size:11.5px;font-weight:600;color:{{ $checked ? 'var(--rose)' : 'var(--emerald)' }};">
                                        {{ $checked ? '🚫 Bloquée' : '✅ Libre' }}
                                    </span>
                                </label>
                            @endforeach

                            <style>
                                .tache-block-item:last-of-type { border-bottom: none !important; }
                                .tache-block-item:hover { background: var(--rose-bg) !important; }
                                .tache-block-item.checked { background: #fff1f2; }
                            </style>
                        </div>

                        @error('taches')<span class="form-error" style="margin-top:6px;display:block;">{{ $message }}</span>@enderror
                    </div>

                    <div style="display:flex;gap:11px;">
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
        document.getElementById('date_debut').addEventListener('change', function () {
            const fin = document.getElementById('date_fin');
            if (!fin.value || fin.value < this.value) fin.value = this.value;
            fin.min = this.value;
        });

        function updateStatus() {
            const checkboxes = document.querySelectorAll('.tache-checkbox');
            let blockedCount = 0;

            checkboxes.forEach(function (cb) {
                const label = cb.closest('.tache-block-item');
                const statusEl = label.querySelector('.block-status');

                if (cb.checked) {
                    blockedCount++;
                    label.classList.add('checked');
                    label.style.background = '#fff1f2';
                    statusEl.textContent = '🚫 Bloquée';
                    statusEl.style.color = 'var(--rose)';
                } else {
                    label.classList.remove('checked');
                    label.style.background = '';
                    statusEl.textContent = '✅ Libre';
                    statusEl.style.color = 'var(--emerald)';
                }
            });

            const countEl = document.getElementById('blockedCount');
            if (blockedCount === 0) {
                countEl.textContent = 'Événement informatif';
                countEl.style.color = 'var(--amber)';
            } else if (blockedCount === checkboxes.length) {
                countEl.textContent = 'Toutes les tâches bloquées';
                countEl.style.color = 'var(--rose)';
            } else {
                countEl.textContent = `${blockedCount} tâche${blockedCount > 1 ? 's' : ''} bloquée${blockedCount > 1 ? 's' : ''}`;
                countEl.style.color = 'var(--amber)';
            }
        }

        function toutCocher(state) {
            document.querySelectorAll('.tache-checkbox').forEach(cb => cb.checked = state);
            updateStatus();
        }

        document.querySelectorAll('.tache-checkbox').forEach(cb => {
            cb.addEventListener('change', updateStatus);
        });

        updateStatus();
    </script>
@endpush
