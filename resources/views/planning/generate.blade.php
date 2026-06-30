{{-- resources/views/planning/generate.blade.php --}}
@extends('layouts.app')

@section('title', 'Générer le planning — AMANA')

@section('content')

{{--
    Point de montage GeneratePreview.vue — gère l'aperçu de génération,
    le spinner du bouton submit, le formulaire d'aperçu caché, et toutes
    les interactions du panneau de rollback (type, checklist, confirmations).
--}}
<div id="vue-generate-preview"></div>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Générer le planning</h1>
        <p class="text-[13px] text-ink-muted mt-1">Génération automatique par rotation des tâches</p>
    </div>
    <a href="{{ route('planning.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2.5 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
        ← Retour
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-5 items-start">

    {{-- Colonne principale --}}
    <div class="flex flex-col gap-4">

        {{-- ── Avertissement chevauchement --}}
        @if(session('pending_generation'))
            @php $pending = session('pending_generation'); @endphp
            <div class="bg-orange-50 border-[1.5px] border-orange-200 rounded-xl p-5 shadow-sm">
                <div class="flex items-start gap-3 mb-4">
                    <div class="w-10 h-10 bg-orange-100 border border-orange-200 rounded-lg flex items-center justify-center text-xl flex-shrink-0">⚠️</div>
                    <div>
                        <h3 class="font-heading text-[15px] font-bold text-orange-900 mb-1">Des créneaux existants vont être supprimés</h3>
                        <p class="text-[12.5px] text-orange-700 leading-relaxed">
                            La génération à partir du
                            <strong>{{ \Carbon\Carbon::parse($pending['date_debut'])->locale('fr')->isoFormat('D MMMM YYYY') }}</strong>
                            va écraser <strong>{{ $pending['nb_total'] }} créneau(x)</strong>
                            sur <strong>{{ count($pending['semaines_affectees']) }} semaine(s)</strong>.
                            Cette action est irréversible (sauf rollback post-génération).
                        </p>
                    </div>
                </div>

                <div class="bg-white border border-orange-200 rounded-lg overflow-hidden mb-4">
                    @foreach($pending['semaines_affectees'] as $sem)
                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-orange-50 last:border-0 text-[13px]">
                            <div class="flex items-center gap-2 font-semibold text-orange-900">
                                🗓️ {{ $sem['label'] }}
                                <span class="text-[12px] text-orange-600 font-normal">{{ $sem['dates'] }}</span>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-orange-100 text-orange-700 border border-orange-200">
                                {{ $sem['nb_creneaux'] }} créneau{{ $sem['nb_creneaux'] > 1 ? 'x' : '' }}
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-2">
                    <form action="{{ route('planning.generate') }}" method="POST">
                        @csrf
                        <input type="hidden" name="date_debut" value="{{ $pending['date_debut'] }}">
                        <input type="hidden" name="semaines"   value="{{ $pending['semaines'] }}">
                        <input type="hidden" name="confirmed"  value="1">
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-[13px] font-bold rounded-lg cursor-pointer transition-colors min-h-[44px]">
                            🗑️ Confirmer et écraser
                        </button>
                    </form>
                    <form action="{{ route('planning.overlap.cancel') }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg cursor-pointer transition-colors bg-transparent min-h-[44px]">
                            Annuler
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- ── Formulaire de génération --}}
        <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden">
            <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
                <div class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">⚙️</div>
                <span class="font-heading text-[14px] font-semibold text-ink">Paramètres de génération</span>
            </div>
            <div class="p-5">
                <div class="flex items-start gap-2.5 bg-sky-50 border border-sky-200 rounded-lg px-4 py-3.5 mb-5 text-[12.5px] text-sky-900 leading-relaxed">
                    <span class="text-base flex-shrink-0 mt-px">ℹ️</span>
                    <ul class="space-y-1 list-none">
                        <li>Le premier <strong>vendredi</strong> après la date choisie sera utilisé</li>
                        <li><strong>amana_food</strong> : rotation stricte par cycle global</li>
                        <li><strong>entree, mektaba, salle, cours</strong> : score d'équilibrage adaptatif</li>
                        <li>Les créneaux existants à partir de cette date seront <strong>remplacés</strong></li>
                    </ul>
                </div>

                <form action="{{ route('planning.generate') }}" method="POST" id="generateForm">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="date_debut" class="text-xs font-bold text-ink tracking-[0.2px]">
                                📆 Date de début <span class="text-rose-500">*</span>
                            </label>
                            <input type="date" id="date_debut" name="date_debut"
                                   value="{{ old('date_debut', now()->toDateString()) }}"
                                   min="{{ now()->toDateString() }}" required
                                   class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                          focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
                            <span class="text-[11.5px] text-ink-muted">Le prochain vendredi sera automatiquement trouvé</span>
                            @error('date_debut')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="semaines" class="text-xs font-bold text-ink tracking-[0.2px]">
                                📊 Nombre de semaines <span class="text-rose-500">*</span>
                            </label>
                            <input type="number" id="semaines" name="semaines"
                                   value="{{ old('semaines', 4) }}" min="1" max="52" required
                                   class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                          focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
                            <span class="text-[11.5px] text-ink-muted">Chaque semaine = vendredi + samedi</span>
                            @error('semaines')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    {{-- Preview box --}}
                    <div id="previewBox" class="flex items-center gap-2.5 px-4 py-3 bg-sky-50 border border-sky-200 rounded-lg text-[13px] text-sky-900 font-medium mb-5 min-h-[48px]">
                        <span>🗓️</span>
                        <span id="previewText">Remplissez les champs pour voir l'aperçu</span>
                    </div>

                    <div class="flex flex-wrap gap-2.5">
                        <button type="submit" id="submitBtn"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-accent hover:bg-accent-dark text-white font-bold text-[13.5px] rounded-lg
                                       shadow-[0_3px_14px_rgba(3,105,161,0.35)] hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer min-h-[48px]">
                            ✨ Générer le planning
                        </button>
                        <button type="button" id="previewBtn" onclick="submitPreview()"
                                class="inline-flex items-center gap-2 px-5 py-2.5 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink font-semibold text-[13.5px] rounded-lg transition-colors cursor-pointer min-h-[48px] bg-transparent">
                            👁 Aperçu
                        </button>
                    </div>
                </form>

                <form action="{{ route('planning.preview') }}" method="POST" id="previewForm" class="hidden">
                    @csrf
                    <input type="hidden" name="date_debut" id="preview_date_debut">
                    <input type="hidden" name="semaines"   id="preview_semaines">
                </form>
            </div>
        </div>
    </div>

    {{-- Colonne rollback --}}
    <div>
        @if(session('last_generated_creneaux'))
            @php
                $generated = session('last_generated_creneaux', []);
                $byWeek    = [];
                foreach ($generated as $item) $byWeek[$item['week_label']][] = $item;
            @endphp
            <div class="bg-white rounded-xl border-[1.5px] border-amber-300 shadow overflow-hidden">
                <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
                    <div class="w-8 h-8 bg-amber-50 rounded-md flex items-center justify-center text-lg flex-shrink-0">↩️</div>
                    <div>
                        <div class="font-heading text-[14px] font-semibold text-ink">Annuler la génération</div>
                        <div class="text-[12px] text-ink-muted">{{ count($generated) }} créneaux générés</div>
                    </div>
                </div>
                <div class="p-4">
                    <form action="{{ route('planning.rollback') }}" method="POST" id="rollbackForm">
                        @csrf

                        {{-- Options rollback --}}
                        <div class="grid grid-cols-2 gap-2.5 mb-4">
                            <label id="opt-total"
                                   class="rollback-opt border-[1.5px] border-accent bg-sky-50 rounded-lg p-3.5 cursor-pointer transition-colors">
                                <input type="radio" name="rollback_type" value="total"
                                       onchange="onRollbackTypeChange(this)" checked
                                       class="w-4 h-4 accent-accent float-right">
                                <div class="font-bold text-[13px] text-ink mb-1 pr-5">🗑️ Totale</div>
                                <div class="text-[11.5px] text-ink-muted leading-relaxed">Supprime tous les créneaux de la session</div>
                            </label>
                            <label id="opt-partial"
                                   class="rollback-opt border-[1.5px] border-surface-border rounded-lg p-3.5 cursor-pointer transition-colors hover:border-amber-300 hover:bg-amber-50">
                                <input type="radio" name="rollback_type" value="partial"
                                       onchange="onRollbackTypeChange(this)"
                                       class="w-4 h-4 accent-accent float-right">
                                <div class="font-bold text-[13px] text-ink mb-1 pr-5">✂️ Partielle</div>
                                <div class="text-[11.5px] text-ink-muted leading-relaxed">Choisissez les semaines à supprimer</div>
                            </label>
                        </div>

                        {{-- Week checklist (hidden by default) --}}
                        <div id="weekChecklist" class="hidden bg-surface-2 rounded-lg p-3.5 mb-4 max-h-[240px] overflow-y-auto">
                            <div class="flex gap-2 mb-3">
                                <button type="button" onclick="checkAll(true)"
                                        class="px-2.5 py-1 text-[11.5px] font-semibold text-ink-muted border border-surface-border rounded-md hover:bg-surface-3 transition-colors bg-transparent cursor-pointer min-h-[44px]">
                                    Tout sélectionner
                                </button>
                                <button type="button" onclick="checkAll(false)"
                                        class="px-2.5 py-1 text-[11.5px] font-semibold text-ink-muted border border-surface-border rounded-md hover:bg-surface-3 transition-colors bg-transparent cursor-pointer min-h-[44px]">
                                    Tout déselectionner
                                </button>
                            </div>
                            @foreach($byWeek as $weekLabel => $items)
                                <label class="flex items-center gap-2.5 py-2 border-b border-surface-3 last:border-0 text-[13px] text-ink-light cursor-pointer min-h-[44px]">
                                    <input type="checkbox" name="selected_weeks[]" value="{{ $weekLabel }}"
                                           id="week_{{ $loop->index }}"
                                           class="w-4 h-4 accent-rose-500 cursor-pointer flex-shrink-0">
                                    <span class="flex-1">
                                        {{ $weekLabel }}
                                        <span class="text-ink-muted text-[11.5px] ml-1">({{ count($items) }} créneaux)</span>
                                    </span>
                                </label>
                            @endforeach
                            @foreach($generated as $item)
                                <input type="hidden" name="creneau_ids[{{ $item['week_label'] }}][]" value="{{ $item['id'] }}">
                            @endforeach
                        </div>

                        <button type="submit"
                                onclick="return confirmRollback()"
                                class="w-full min-h-[44px] px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-bold text-[13px] rounded-lg
                                       transition-colors cursor-pointer flex items-center justify-center gap-1.5 mb-3">
                            ↩️ Annuler la génération
                        </button>
                    </form>

                    <form action="{{ route('planning.rollback.dismiss') }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="w-full min-h-[44px] px-4 py-2 text-[12.5px] font-semibold text-ink-muted hover:bg-surface-3 hover:text-ink rounded-lg transition-colors bg-transparent border-0 cursor-pointer">
                            ✓ Conserver et fermer
                        </button>
                    </form>
                </div>
            </div>
        @else
            <div class="bg-white rounded-xl border border-surface-border shadow-sm p-6 text-center">
                <div class="text-4xl opacity-25 mb-3">↩️</div>
                <p class="text-[13px] text-ink-muted leading-relaxed">
                    Après la génération, vous pourrez annuler totalement ou partiellement les créneaux créés.
                </p>
            </div>
        @endif
    </div>
</div>

@endsection

{{--
    Aucun script inline ici désormais — tout est géré par GeneratePreview.vue
    (aperçu, soumission, et interactions du panneau de rollback).
--}}
