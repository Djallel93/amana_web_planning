{{-- resources/views/planning/generate.blade.php --}}
@extends('layouts.app')

@section('title', 'Générer le planning — AMANA')

@push('styles')
<style>
    .info-box {
        background: var(--sky-bg);
        border: 1px solid #bae6fd;
        border-radius: var(--radius-lg);
        padding: 16px 20px;
        margin-bottom: 24px;
    }
    .info-box-title {
        font-weight: 700;
        color: var(--sky);
        font-size: 13px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 7px;
    }
    .info-box ul {
        padding-left: 18px;
        color: #0c4a6e;
        font-size: 13px;
        line-height: 1.9;
    }
    .preview-box {
        background: var(--violet-bg);
        border: 1px solid #ddd6fe;
        border-radius: var(--radius);
        padding: 13px 16px;
        margin: 20px 0;
        font-size: 13px;
        color: #5b21b6;
        font-weight: 500;
        min-height: 42px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .preview-box strong { color: #4c1d95; }

    /* Rollback section */
    .rollback-panel {
        background: var(--surface);
        border: 1.5px solid #fde68a;
        border-radius: var(--radius-lg);
        padding: 24px;
        margin-top: 24px;
        box-shadow: var(--shadow);
    }
    .rollback-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }
    .rollback-icon {
        width: 42px; height: 42px;
        background: var(--amber-bg);
        border-radius: var(--radius);
        display: flex; align-items: center; justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }
    .rollback-title { font-size: 16px; font-weight: 700; color: var(--ink); }
    .rollback-desc  { font-size: 13px; color: var(--ink-muted); margin-top: 2px; }

    .rollback-options {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        margin-bottom: 20px;
    }
    .rollback-option {
        border: 2px solid var(--surface-3);
        border-radius: var(--radius-lg);
        padding: 16px;
        cursor: pointer;
        transition: var(--transition);
        position: relative;
    }
    .rollback-option:hover {
        border-color: var(--amber);
        background: var(--amber-bg);
    }
    .rollback-option.selected {
        border-color: var(--primary);
        background: var(--violet-bg);
    }
    .rollback-option input[type="radio"] {
        position: absolute; top: 14px; right: 14px;
        accent-color: var(--primary);
        width: 16px; height: 16px;
    }
    .rollback-option-title { font-weight: 700; font-size: 13.5px; color: var(--ink); margin-bottom: 4px; }
    .rollback-option-desc  { font-size: 12.5px; color: var(--ink-muted); line-height: 1.5; }

    .week-checklist {
        display: none;
        background: var(--surface-2);
        border-radius: var(--radius);
        padding: 14px 16px;
        margin-top: 14px;
        max-height: 280px;
        overflow-y: auto;
    }
    .week-checklist.visible { display: block; }
    .week-check-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 7px 0;
        border-bottom: 1px solid var(--surface-3);
        font-size: 13px;
        color: var(--ink-light);
    }
    .week-check-item:last-child { border-bottom: none; }
    .week-check-item input[type="checkbox"] {
        width: 15px; height: 15px;
        accent-color: var(--rose);
        cursor: pointer;
        flex-shrink: 0;
    }
    .checklist-controls {
        display: flex;
        gap: 8px;
        margin-bottom: 10px;
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Générer le planning</div>
        <div class="page-subtitle">Génération automatique par rotation des tâches</div>
    </div>
    <a href="{{ route('planning.index') }}" class="btn btn-secondary">← Retour</a>
</div>

<div style="display: grid; grid-template-columns: 1fr 380px; gap: 24px; align-items: start;">

    {{-- Generate Form --}}
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon" style="background:var(--violet-bg);">⚙️</div>
                    Paramètres de génération
                </div>
            </div>
            <div class="card-body">
                <div class="info-box">
                    <div class="info-box-title">ℹ️ Comment ça marche</div>
                    <ul>
                        <li>Le premier <strong>vendredi</strong> après la date choisie sera utilisé</li>
                        <li><strong>amana_food</strong> : rotation stricte par cycle global</li>
                        <li><strong>entree, mektaba, salle</strong> : score d'équilibrage adaptatif</li>
                        <li>Les créneaux existants à partir de cette date seront <strong>remplacés</strong></li>
                    </ul>
                </div>

                <form action="{{ route('planning.generate') }}" method="POST" id="generateForm">
                    @csrf
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="date_debut">📆 Date de début <span class="req">*</span></label>
                            <input type="date" id="date_debut" name="date_debut"
                                   value="{{ old('date_debut', now()->toDateString()) }}"
                                   min="{{ now()->toDateString() }}" required>
                            <span class="form-hint">Le prochain vendredi sera automatiquement trouvé</span>
                            @error('date_debut')<span class="form-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label for="semaines">📊 Nombre de semaines <span class="req">*</span></label>
                            <input type="number" id="semaines" name="semaines"
                                   value="{{ old('semaines', 4) }}" min="1" max="52" required>
                            <span class="form-hint">Chaque semaine = vendredi + samedi</span>
                            @error('semaines')<span class="form-error">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="preview-box" id="previewBox">
                        <span>🗓️</span>
                        <span id="previewText">Remplissez les champs pour voir l'aperçu</span>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%; justify-content:center;" id="submitBtn">
                        ✨ Générer le planning
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Rollback Panel (shown after generation) --}}
    <div>
        @if(session('last_generated_creneaux'))
        <div class="rollback-panel">
            <div class="rollback-header">
                <div class="rollback-icon">↩️</div>
                <div>
                    <div class="rollback-title">Annuler la génération</div>
                    <div class="rollback-desc">{{ count(session('last_generated_creneaux')) }} créneaux générés</div>
                </div>
            </div>

            <form action="{{ route('planning.rollback') }}" method="POST" id="rollbackForm">
                @csrf
                <div class="rollback-options">
                    <label class="rollback-option" id="opt-total">
                        <input type="radio" name="rollback_type" value="total" onchange="onRollbackTypeChange(this)" checked>
                        <div class="rollback-option-title">🗑️ Annulation totale</div>
                        <div class="rollback-option-desc">Supprime tous les créneaux générés lors de cette session</div>
                    </label>
                    <label class="rollback-option" id="opt-partial">
                        <input type="radio" name="rollback_type" value="partial" onchange="onRollbackTypeChange(this)">
                        <div class="rollback-option-title">✂️ Annulation partielle</div>
                        <div class="rollback-option-desc">Choisissez les semaines à supprimer</div>
                    </label>
                </div>

                {{-- Week checklist for partial rollback --}}
                @php
                    $generated = session('last_generated_creneaux', []);
                    // Group by week
                    $byWeek = [];
                    foreach ($generated as $item) {
                        $weekKey = $item['week_label'];
                        $byWeek[$weekKey][] = $item;
                    }
                @endphp

                <div class="week-checklist" id="weekChecklist">
                    <div class="checklist-controls">
                        <button type="button" class="btn btn-ghost btn-sm" onclick="checkAll(true)">Tout sélectionner</button>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="checkAll(false)">Tout déselectionner</button>
                    </div>
                    @foreach($byWeek as $weekLabel => $items)
                        <div class="week-check-item">
                            <input type="checkbox" name="selected_weeks[]"
                                   value="{{ $weekLabel }}" id="week_{{ $loop->index }}">
                            <label for="week_{{ $loop->index }}" style="cursor:pointer; flex:1;">
                                {{ $weekLabel }}
                                <span style="color:var(--ink-muted); font-size:11.5px; margin-left:6px;">
                                    ({{ count($items) }} créneaux)
                                </span>
                            </label>
                        </div>
                    @endforeach

                    {{-- Pass creneau IDs --}}
                    @foreach($generated as $item)
                        <input type="hidden" name="creneau_ids[{{ $item['week_label'] }}][]" value="{{ $item['id'] }}">
                    @endforeach
                </div>

                <button type="submit" class="btn btn-warning" style="width:100%; justify-content:center; margin-top:4px;"
                        onclick="return confirmRollback()">
                    ↩️ Annuler la génération
                </button>
            </form>

            <div style="margin-top:12px; padding-top:12px; border-top:1px solid var(--surface-3);">
                <form action="{{ route('planning.rollback.dismiss') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm" style="width:100%; justify-content:center;">
                        ✓ Conserver et fermer
                    </button>
                </form>
            </div>
        </div>
        @else
        <div class="card">
            <div class="card-body">
                <div style="text-align:center; padding:20px 0;">
                    <div style="font-size:40px; margin-bottom:12px; opacity:0.4;">↩️</div>
                    <div style="font-size:13.5px; color:var(--ink-muted); line-height:1.6;">
                        Après la génération, vous pourrez annuler totalement ou partiellement les créneaux créés.
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
// Preview
function updatePreview() {
    const d = document.getElementById('date_debut').value;
    const s = parseInt(document.getElementById('semaines').value) || 0;
    const el = document.getElementById('previewText');
    if (!d || s < 1) { el.textContent = 'Remplissez les champs pour voir l\'aperçu'; return; }

    const dt = new Date(d + 'T00:00:00');
    while (dt.getDay() !== 5) dt.setDate(dt.getDate() + 1);
    const fin = new Date(dt);
    fin.setDate(fin.getDate() + (s - 1) * 7 + 1);
    const fmt = d => d.toLocaleDateString('fr-FR', { day:'numeric', month:'long', year:'numeric' });

    el.innerHTML = `<strong>${s * 2} créneaux</strong> (${s} vendredis + ${s} samedis) du <strong>${fmt(dt)}</strong> au <strong>${fmt(fin)}</strong>`;
}

document.getElementById('date_debut').addEventListener('change', updatePreview);
document.getElementById('semaines').addEventListener('input', updatePreview);
updatePreview();

document.getElementById('generateForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '⏳ Génération en cours…';
    btn.style.opacity = '0.7';
});

// Rollback
function onRollbackTypeChange(radio) {
    const checklist = document.getElementById('weekChecklist');
    document.getElementById('opt-total').classList.toggle('selected', radio.value === 'total');
    document.getElementById('opt-partial').classList.toggle('selected', radio.value === 'partial');
    if (checklist) checklist.classList.toggle('visible', radio.value === 'partial');
}

function checkAll(state) {
    document.querySelectorAll('#weekChecklist input[type="checkbox"]').forEach(cb => cb.checked = state);
}

function confirmRollback() {
    const type = document.querySelector('input[name="rollback_type"]:checked')?.value;
    if (type === 'partial') {
        const checked = document.querySelectorAll('#weekChecklist input[type="checkbox"]:checked').length;
        if (checked === 0) { alert('Sélectionnez au moins une semaine.'); return false; }
        return confirm(`Supprimer ${checked} semaine(s) sélectionnée(s) ?`);
    }
    return confirm('Annuler toute la génération ? Cette action est irréversible.');
}

// Init radio state
document.addEventListener('DOMContentLoaded', () => {
    const checked = document.querySelector('input[name="rollback_type"]:checked');
    if (checked) onRollbackTypeChange(checked);
});
</script>
@endpush
