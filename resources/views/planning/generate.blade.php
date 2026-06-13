{{-- resources/views/planning/generate.blade.php --}}
@extends('layouts.app')

@section('title', 'Générer le planning — AMANA')

@push('styles')
    <style>
        .info-box {
            background: var(--sky-bg);
            border: 1px solid var(--sky-border);
            border-radius: var(--radius);
            padding: 14px 18px;
            margin-bottom: 22px;
        }

        .info-box-title {
            font-family: var(--font-heading);
            font-weight: 600;
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
            margin: 0;
        }

        .preview-box {
            background: var(--sky-bg);
            border: 1px solid var(--sky-border);
            border-radius: var(--radius);
            padding: 12px 16px;
            margin: 18px 0;
            font-size: 13px;
            color: var(--sky);
            font-weight: 500;
            min-height: 40px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-box strong {
            color: #0c4a6e;
        }

        /* ── Overlap warning ── */
        .overlap-warning {
            background: #fff7ed;
            border: 1.5px solid #fed7aa;
            border-radius: var(--radius-lg);
            padding: 20px 22px;
            margin-bottom: 22px;
            box-shadow: var(--shadow-sm);
        }

        .overlap-warning-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
        }

        .overlap-warning-icon {
            width: 40px;
            height: 40px;
            background: #ffedd5;
            border: 1px solid #fed7aa;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .overlap-warning-title {
            font-family: var(--font-heading);
            font-size: 15px;
            font-weight: 700;
            color: #9a3412;
            margin-bottom: 4px;
        }

        .overlap-warning-sub {
            font-size: 12.5px;
            color: #c2410c;
            line-height: 1.6;
        }

        .overlap-weeks-list {
            background: #fff;
            border: 1px solid #fed7aa;
            border-radius: var(--radius);
            overflow: hidden;
            margin-bottom: 16px;
        }

        .overlap-week-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 15px;
            border-bottom: 1px solid #fff7ed;
            font-size: 13px;
        }

        .overlap-week-item:last-child {
            border-bottom: none;
        }

        .overlap-week-label {
            font-weight: 600;
            color: #7c2d12;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .overlap-week-dates {
            font-size: 12px;
            color: #c2410c;
        }

        .overlap-week-count {
            font-size: 11.5px;
            font-weight: 600;
            color: #c2410c;
            background: #ffedd5;
            padding: 2px 8px;
            border-radius: 20px;
            border: 1px solid #fed7aa;
        }

        .overlap-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* ── Rollback panel ── */
        .rollback-panel {
            background: var(--surface);
            border: 1.5px solid var(--amber-border);
            border-radius: var(--radius-lg);
            padding: 22px;
            box-shadow: var(--shadow);
        }

        .rollback-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .rollback-icon {
            width: 40px;
            height: 40px;
            background: var(--amber-bg);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            flex-shrink: 0;
        }

        .rollback-title {
            font-family: var(--font-heading);
            font-size: 15px;
            font-weight: 600;
            color: var(--ink);
        }

        .rollback-desc {
            font-size: 12.5px;
            color: var(--ink-muted);
            margin-top: 2px;
        }

        .rollback-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 18px;
        }

        .rollback-option {
            border: 1.5px solid var(--surface-border);
            border-radius: var(--radius);
            padding: 14px;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .rollback-option:hover {
            border-color: var(--amber);
            background: var(--amber-bg);
        }

        .rollback-option.selected {
            border-color: var(--app-accent);
            background: var(--sky-bg);
        }

        .rollback-option input[type="radio"] {
            position: absolute;
            top: 12px;
            right: 12px;
            accent-color: var(--app-accent);
            width: 15px;
            height: 15px;
            -webkit-appearance: auto;
            appearance: auto;
        }

        .rollback-option-title {
            font-weight: 700;
            font-size: 13px;
            color: var(--ink);
            margin-bottom: 3px;
        }

        .rollback-option-desc {
            font-size: 12px;
            color: var(--ink-muted);
            line-height: 1.5;
        }

        .week-checklist {
            display: none;
            background: var(--surface-2);
            border-radius: var(--radius);
            padding: 12px 14px;
            margin-top: 12px;
            max-height: 260px;
            overflow-y: auto;
        }

        .week-checklist.visible {
            display: block;
        }

        .week-check-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 0;
            border-bottom: 1px solid var(--surface-3);
            font-size: 13px;
            color: var(--ink-light);
        }

        .week-check-item:last-child {
            border-bottom: none;
        }

        .week-check-item input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: var(--rose);
            cursor: pointer;
            flex-shrink: 0;
            -webkit-appearance: auto;
            appearance: auto;
        }

        .checklist-controls {
            display: flex;
            gap: 8px;
            margin-bottom: 9px;
        }

        /* Generate action buttons */
        .generate-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
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

    <div style="display:grid;grid-template-columns:1fr 360px;gap:22px;align-items:start;">

        <div style="display:flex;flex-direction:column;gap:18px;">

            {{-- ── Overlap warning (shown when session has pending_generation) ── --}}
            @if(session('pending_generation'))
                @php $pending = session('pending_generation'); @endphp
                <div class="overlap-warning">
                    <div class="overlap-warning-header">
                        <div class="overlap-warning-icon">⚠️</div>
                        <div>
                            <div class="overlap-warning-title">
                                Des créneaux existants vont être supprimés
                            </div>
                            <div class="overlap-warning-sub">
                                La génération à partir du
                                <strong>{{ \Carbon\Carbon::parse($pending['date_debut'])->locale('fr')->isoFormat('D MMMM YYYY') }}</strong>
                                va écraser <strong>{{ $pending['nb_total'] }} créneau(x)</strong>
                                sur <strong>{{ count($pending['semaines_affectees']) }} semaine(s)</strong>.
                                Cette action est irréversible (sauf rollback post-génération).
                            </div>
                        </div>
                    </div>

                    {{-- Affected weeks list --}}
                    <div class="overlap-weeks-list">
                        @foreach($pending['semaines_affectees'] as $sem)
                            <div class="overlap-week-item">
                                <div class="overlap-week-label">
                                    🗓️ {{ $sem['label'] }}
                                    <span class="overlap-week-dates">{{ $sem['dates'] }}</span>
                                </div>
                                <span class="overlap-week-count">
                                    {{ $sem['nb_creneaux'] }} créneau{{ $sem['nb_creneaux'] > 1 ? 'x' : '' }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="overlap-actions">
                        {{-- Confirm: re-submit with confirmed=1 --}}
                        <form action="{{ route('planning.generate') }}" method="POST">
                            @csrf
                            <input type="hidden" name="date_debut" value="{{ $pending['date_debut'] }}">
                            <input type="hidden" name="semaines" value="{{ $pending['semaines'] }}">
                            <input type="hidden" name="confirmed" value="1">
                            <button type="submit" class="btn btn-danger">
                                🗑️ Confirmer et écraser
                            </button>
                        </form>

                        {{-- Cancel: clear the pending session --}}
                        <form action="{{ route('planning.overlap.cancel') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-secondary">Annuler</button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Generate form --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="card-title-icon" style="background:var(--sky-bg);">⚙️</div>
                        Paramètres de génération
                    </div>
                </div>
                <div class="card-body">
                    <div class="info-box">
                        <div class="info-box-title">ℹ️ Comment ça marche</div>
                        <ul>
                            <li>Le premier <strong>vendredi</strong> après la date choisie sera utilisé</li>
                            <li><strong>amana_food</strong> : rotation stricte par cycle global</li>
                            <li><strong>entree, mektaba, salle, cours</strong> : score d'équilibrage adaptatif</li>
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
                                    min="{{ now()->toDateString() }}"
                                    required>
                                <span class="form-hint">Le prochain vendredi sera automatiquement trouvé</span>
                                @error('date_debut')<span class="form-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="form-group">
                                <label for="semaines">📊 Nombre de semaines <span class="req">*</span></label>
                                <input type="number" id="semaines" name="semaines"
                                    value="{{ old('semaines', 4) }}"
                                    min="1" max="52" required>
                                <span class="form-hint">Chaque semaine = vendredi + samedi</span>
                                @error('semaines')<span class="form-error">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <div class="preview-box" id="previewBox">
                            <span>🗓️</span>
                            <span id="previewText">Remplissez les champs pour voir l'aperçu</span>
                        </div>

                        <div class="generate-actions">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                ✨ Générer le planning
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg" id="previewBtn"
                                onclick="submitPreview()">
                                👁 Aperçu
                            </button>
                        </div>
                    </form>

                    {{-- Hidden form for preview (posts to different route) --}}
                    <form action="{{ route('planning.preview') }}" method="POST" id="previewForm" style="display:none;">
                        @csrf
                        <input type="hidden" name="date_debut" id="preview_date_debut">
                        <input type="hidden" name="semaines"   id="preview_semaines">
                    </form>
                </div>
            </div>

        </div>

        {{-- Rollback panel --}}
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
                            <label class="rollback-option selected" id="opt-total">
                                <input type="radio" name="rollback_type" value="total"
                                    onchange="onRollbackTypeChange(this)" checked>
                                <div class="rollback-option-title">🗑️ Totale</div>
                                <div class="rollback-option-desc">Supprime tous les créneaux de la session</div>
                            </label>
                            <label class="rollback-option" id="opt-partial">
                                <input type="radio" name="rollback_type" value="partial"
                                    onchange="onRollbackTypeChange(this)">
                                <div class="rollback-option-title">✂️ Partielle</div>
                                <div class="rollback-option-desc">Choisissez les semaines à supprimer</div>
                            </label>
                        </div>

                        @php
                            $generated = session('last_generated_creneaux', []);
                            $byWeek    = [];
                            foreach ($generated as $item) {
                                $byWeek[$item['week_label']][] = $item;
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
                                    <label for="week_{{ $loop->index }}" style="cursor:pointer;flex:1;">
                                        {{ $weekLabel }}
                                        <span style="color:var(--ink-muted);font-size:11.5px;margin-left:6px;">
                                            ({{ count($items) }} créneaux)
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                            @foreach($generated as $item)
                                <input type="hidden"
                                    name="creneau_ids[{{ $item['week_label'] }}][]"
                                    value="{{ $item['id'] }}">
                            @endforeach
                        </div>

                        <button type="submit" class="btn btn-warning"
                            style="width:100%;justify-content:center;margin-top:14px;"
                            onclick="return confirmRollback()">
                            ↩️ Annuler la génération
                        </button>
                    </form>

                    <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--surface-3);">
                        <form action="{{ route('planning.rollback.dismiss') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm"
                                style="width:100%;justify-content:center;">
                                ✓ Conserver et fermer
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body" style="text-align:center;padding:28px 20px;">
                        <div style="font-size:36px;margin-bottom:12px;opacity:0.3;">↩️</div>
                        <div style="font-size:13px;color:var(--ink-muted);line-height:1.65;">
                            Après la génération, vous pourrez annuler totalement ou partiellement
                            les créneaux créés.
                        </div>
                    </div>
                </div>
            @endif
        </div>

    </div>
@endsection

@push('scripts')
    <script>
        function updatePreview() {
            const d  = document.getElementById('date_debut').value;
            const s  = parseInt(document.getElementById('semaines').value) || 0;
            const el = document.getElementById('previewText');
            if (!d || s < 1) { el.textContent = 'Remplissez les champs pour voir l\'aperçu'; return; }
            const dt = new Date(d + 'T00:00:00');
            while (dt.getDay() !== 5) dt.setDate(dt.getDate() + 1);
            const fin = new Date(dt);
            fin.setDate(fin.getDate() + (s - 1) * 7 + 1);
            const fmt = d => d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
            el.innerHTML = `<strong>${s * 2} créneaux</strong> (${s} vendredis + ${s} samedis) du <strong>${fmt(dt)}</strong> au <strong>${fmt(fin)}</strong>`;
        }

        document.getElementById('date_debut').addEventListener('change', updatePreview);
        document.getElementById('semaines').addEventListener('input', updatePreview);
        updatePreview();

        // Generate: disable button on submit
        document.getElementById('generateForm').addEventListener('submit', function (e) {
            // Don't lock if it came from the preview button
            if (e.submitter && e.submitter.id === 'previewBtn') return;
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '⏳ Génération en cours…';
            btn.style.opacity = '0.75';
        });

        // Preview: copy values to hidden form and submit it
        function submitPreview() {
            const dateDebut = document.getElementById('date_debut').value;
            const semaines  = document.getElementById('semaines').value;

            if (!dateDebut || !semaines || parseInt(semaines) < 1) {
                alert('Veuillez remplir la date et le nombre de semaines avant de prévisualiser.');
                return;
            }

            document.getElementById('preview_date_debut').value = dateDebut;
            document.getElementById('preview_semaines').value   = semaines;

            const btn = document.getElementById('previewBtn');
            btn.disabled = true;
            btn.innerHTML = '⏳ Calcul en cours…';
            btn.style.opacity = '0.75';

            document.getElementById('previewForm').submit();
        }

        // Rollback helpers
        function onRollbackTypeChange(radio) {
            const checklist = document.getElementById('weekChecklist');
            document.getElementById('opt-total').classList.toggle('selected', radio.value === 'total');
            document.getElementById('opt-partial').classList.toggle('selected', radio.value === 'partial');
            if (checklist) checklist.classList.toggle('visible', radio.value === 'partial');
        }

        function checkAll(state) {
            document.querySelectorAll('#weekChecklist input[type="checkbox"]')
                .forEach(cb => cb.checked = state);
        }

        function confirmRollback() {
            const type    = document.querySelector('input[name="rollback_type"]:checked')?.value;
            if (type === 'partial') {
                const checked = document.querySelectorAll('#weekChecklist input[type="checkbox"]:checked').length;
                if (checked === 0) { alert('Sélectionnez au moins une semaine.'); return false; }
                return confirm(`Supprimer ${checked} semaine(s) sélectionnée(s) ?`);
            }
            return confirm('Annuler toute la génération ? Cette action est irréversible.');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const checked = document.querySelector('input[name="rollback_type"]:checked');
            if (checked) onRollbackTypeChange(checked);
        });
    </script>
@endpush