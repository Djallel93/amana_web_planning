{{-- resources/views/planning/mon-planning.blade.php --}}
@extends('layouts.app')

@section('title', 'Mon planning — AMANA')

@push('styles')
    <style>
        /* ── Timeline layout ── */
        .timeline-wrapper {
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        .month-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .month-label {
            font-family: var(--font-heading);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--ink-muted);
        }

        .month-divider {
            flex: 1;
            height: 1px;
            background: var(--surface-border);
        }

        .month-count {
            font-size: 11px;
            color: var(--ink-faint);
            font-weight: 600;
            white-space: nowrap;
        }

        /* ── Cards ── */
        .creneau-cards {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .creneau-card {
            background: var(--surface);
            border: 1px solid var(--surface-border);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 18px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .creneau-card:hover { box-shadow: var(--shadow); transform: translateY(-1px); }
        .creneau-card.is-future { border-left: 3px solid var(--app-accent); }
        .creneau-card.is-past   { opacity: 0.72; }
        .creneau-card.is-today  { border-left: 3px solid var(--emerald); background: var(--emerald-bg); }

        /* Has a pending swap */
        .creneau-card.has-echange {
            border-left: 3px solid var(--amber);
        }

        .echange-pending-badge {
            position: absolute;
            top: 10px;
            right: 14px;
            font-size: 10.5px;
            font-weight: 700;
            color: #92400e;
            background: var(--amber-bg);
            border: 1px solid var(--amber-border);
            padding: 2px 8px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Left: date block */
        .date-block {
            flex-shrink: 0;
            width: 56px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1px;
        }

        .date-day-num {
            font-family: var(--font-heading);
            font-size: 26px;
            font-weight: 700;
            line-height: 1;
            color: var(--ink);
        }

        .date-month-str {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: var(--ink-muted);
        }

        .date-jour-str {
            font-size: 10px;
            color: var(--ink-faint);
            margin-top: 2px;
            font-weight: 600;
        }

        .card-sep {
            width: 1px;
            height: 44px;
            background: var(--surface-3);
            flex-shrink: 0;
        }

        /* Middle: task info */
        .task-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .task-main {
            display: flex;
            align-items: center;
            gap: 9px;
            flex-wrap: wrap;
        }

        .semaine-badge {
            font-size: 11px;
            color: var(--ink-muted);
            background: var(--surface-2);
            border: 1px solid var(--surface-border);
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        .task-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .evt-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11.5px;
            color: var(--amber);
            background: var(--amber-bg);
            border: 1px solid var(--amber-border);
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        /* Right: status + action col */
        .status-col {
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 7px;
        }

        .badge-futur  { background:var(--sky-bg);    color:var(--sky);     border:1px solid var(--sky-border);     padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; gap:4px; }
        .badge-passe  { background:var(--surface-3); color:var(--ink-faint);border:1px solid var(--ink-faint);    padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; display:inline-flex; align-items:center; gap:4px; }
        .badge-today  { background:var(--emerald-bg);color:var(--emerald); border:1px solid var(--emerald-border);padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; gap:4px; }

        .btn-swap {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: var(--radius-sm);
            font-size: 11.5px;
            font-weight: 600;
            font-family: var(--font-body);
            cursor: pointer;
            border: 1.5px solid var(--app-accent);
            color: var(--app-accent);
            background: transparent;
            transition: var(--transition);
            white-space: nowrap;
        }

        .btn-swap:hover {
            background: var(--sky-bg);
        }

        /* ── Stats strip ── */
        .stats-strip {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 26px;
        }

        .stat-pill {
            background: var(--surface);
            border: 1px solid var(--surface-border);
            border-radius: var(--radius-lg);
            padding: 13px 18px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 110px;
        }

        .stat-pill-value {
            font-family: var(--font-heading);
            font-size: 24px;
            font-weight: 700;
            color: var(--ink);
            line-height: 1;
        }

        .stat-pill-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--ink-muted);
        }

        /* ── Swap modal ── */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(13,17,23,0.5);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
        }

        .modal-backdrop.open {
            opacity: 1;
            pointer-events: all;
        }

        .modal {
            background: var(--surface);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 520px;
            overflow: hidden;
            transform: translateY(14px) scale(0.98);
            transition: transform 0.22s cubic-bezier(0.34,1.56,0.64,1);
            border: 1px solid var(--surface-border);
        }

        .modal-backdrop.open .modal {
            transform: translateY(0) scale(1);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--surface-3);
        }

        .modal-title {
            font-family: var(--font-heading);
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--ink-muted);
            font-size: 18px;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            line-height: 1;
        }

        .modal-close:hover { background: var(--surface-2); color: var(--ink); }

        .modal-body { padding: 20px; }

        .my-slot-preview {
            background: var(--sky-bg);
            border: 1.5px solid var(--sky-border);
            border-radius: var(--radius);
            padding: 13px 16px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .my-slot-preview-icon {
            font-size: 22px;
            flex-shrink: 0;
        }

        .my-slot-info-date { font-weight: 700; color: var(--ink); font-size: 14px; }
        .my-slot-info-tache { font-size: 12.5px; color: var(--ink-muted); margin-top: 2px; }

        .slots-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 320px;
            overflow-y: auto;
        }

        .slot-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border: 1.5px solid var(--surface-border);
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .slot-option:hover {
            border-color: var(--app-accent);
            background: var(--sky-bg);
        }

        .slot-option.selected {
            border-color: var(--app-accent);
            background: var(--sky-bg);
            box-shadow: 0 0 0 3px var(--app-accent-glow);
        }

        .slot-option input[type="radio"] {
            accent-color: var(--app-accent);
            width: 15px;
            height: 15px;
            flex-shrink: 0;
            -webkit-appearance: auto;
            appearance: auto;
        }

        .slot-option-date { font-weight: 700; color: var(--ink); font-size: 13px; }
        .slot-option-meta { font-size: 12px; color: var(--ink-muted); margin-top: 1px; }

        .slots-loading {
            text-align: center;
            padding: 32px;
            color: var(--ink-muted);
            font-size: 13.5px;
        }

        .slots-empty {
            text-align: center;
            padding: 28px;
            color: var(--ink-muted);
            font-size: 13.5px;
            background: var(--surface-2);
            border-radius: var(--radius);
            border: 1px solid var(--surface-border);
        }

        .toast-container {
            position: fixed;
            bottom: 22px;
            right: 22px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .toast {
            background: var(--ink);
            color: white;
            padding: 11px 16px;
            border-radius: var(--radius-lg);
            font-size: 13px;
            font-weight: 500;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 240px;
            animation: toastIn 0.28s cubic-bezier(0.34,1.56,0.64,1);
        }

        .toast.success { border-left: 3px solid var(--emerald); }
        .toast.error   { border-left: 3px solid var(--rose); }

        @keyframes toastIn {
            from { opacity:0; transform:translateX(16px); }
            to   { opacity:1; transform:translateX(0); }
        }
        @keyframes toastOut {
            from { opacity:1; transform:translateX(0); }
            to   { opacity:0; transform:translateX(16px); }
        }

        @media (max-width: 600px) {
            .creneau-card { flex-wrap: wrap; gap: 12px; }
            .card-sep { display: none; }
            .status-col { margin-left: auto; }
        }
    </style>
@endpush

@section('content')

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Mon planning</div>
            <div class="page-subtitle">Vos permanences — un an glissant + futur</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('echanges.index') }}" class="btn btn-secondary">🔄 Mes échanges</a>
            <a href="{{ route('planning.index') }}" class="btn btn-secondary">📅 Planning complet</a>
        </div>
    </div>

    {{-- Stats strip --}}
    <div class="stats-strip">
        <div class="stat-pill">
            <div class="stat-pill-value">{{ $total }}</div>
            <div class="stat-pill-label">Total créneaux</div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-value" style="color:var(--app-accent);">{{ $futures }}</div>
            <div class="stat-pill-label">À venir</div>
        </div>
        @php
            $tacheLabels = [
                'entree'     => ['Entrée',     '🚪'],
                'mektaba'    => ['Mektaba',    '📚'],
                'salle'      => ['Salle',      '🏛️'],
                'amana_food' => ['Amana Food', '🥪'],
                'cours'      => ['Cours',      '🎓'],
            ];
        @endphp
        @foreach($parTache as $code => $count)
            @if(isset($tacheLabels[$code]))
                <div class="stat-pill">
                    <div class="stat-pill-value">{{ $count }}</div>
                    <div class="stat-pill-label">{{ $tacheLabels[$code][1] }} {{ $tacheLabels[$code][0] }}</div>
                </div>
            @endif
        @endforeach
    </div>

    @if($parMois->isEmpty())
        <div class="card">
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <div class="empty-title">Aucune permanence</div>
                <div class="empty-desc">
                    Vous n'avez pas encore été assigné à des créneaux sur les 12 derniers mois.
                </div>
            </div>
        </div>
    @else
        <div class="timeline-wrapper">
            @foreach($parMois as $moisKey => $lignes)
                @php
                    $firstDate = $lignes->first()->creneau->date;
                    $moisLabel = ucfirst($firstDate->locale('fr')->isoFormat('MMMM YYYY'));
                @endphp

                <div class="month-section">
                    <div class="month-header">
                        <div class="month-label">{{ $moisLabel }}</div>
                        <div class="month-divider"></div>
                        <div class="month-count">{{ $lignes->count() }} créneau{{ $lignes->count() > 1 ? 'x' : '' }}</div>
                    </div>

                    <div class="creneau-cards">
                        @foreach($lignes as $ligne)
                            @php
                                $creneau = $ligne->creneau;
                                $tache   = $ligne->tache;
                                $date    = $creneau->date;

                                $isToday  = $date->isToday();
                                $isFuture = $date->isFuture() && !$isToday;
                                $isPast   = $date->isPast() && !$isToday;

                                $cardClass = $isToday ? 'is-today' : ($isFuture ? 'is-future' : 'is-past');

                                $evtStr = $creneau->evenements?->pluck('nom')->implode(', ');

                                // Check for pending swap on this slot
                                $echangeEnAttente = $echangesEnAttente
                                    ->first(fn($e) =>
                                        ($e->id_creneau_demandeur === $creneau->id && $e->id_tache_demandeur === $tache?->id)
                                        || ($e->id_creneau_cible === $creneau->id && $e->id_tache_cible === $tache?->id)
                                    );
                            @endphp

                            <div class="creneau-card {{ $cardClass }} {{ $echangeEnAttente ? 'has-echange' : '' }}">

                                @if($echangeEnAttente)
                                    <div class="echange-pending-badge">⏳ Échange en attente</div>
                                @endif

                                {{-- Date block --}}
                                <div class="date-block">
                                    <div class="date-day-num">{{ $date->format('d') }}</div>
                                    <div class="date-month-str">{{ $date->locale('fr')->isoFormat('MMM') }}</div>
                                    <div class="date-jour-str">{{ $creneau->jour }}</div>
                                </div>

                                <div class="card-sep"></div>

                                {{-- Task info --}}
                                <div class="task-info">
                                    <div class="task-main">
                                        @if($tache)
                                            <span class="tache-chip {{ $tache->code }}">
                                                @php $icons = ['entree'=>'🚪','mektaba'=>'📚','salle'=>'🏛️','amana_food'=>'🥪','cours'=>'🎓']; @endphp
                                                {{ $icons[$tache->code] ?? '' }} {{ $tache->libelle }}
                                            </span>
                                        @endif
                                        <span class="semaine-badge">S{{ $creneau->semaine }}</span>
                                    </div>
                                    <div class="task-meta">
                                        <span style="font-size:12px;color:var(--ink-muted);">
                                            {{ $date->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                                        </span>
                                        @if($evtStr)
                                            <span class="evt-badge">🎉 {{ $evtStr }}</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Status + swap action --}}
                                <div class="status-col">
                                    @if($isToday)
                                        <span class="badge-today">● Aujourd'hui</span>
                                    @elseif($isFuture)
                                        <span class="badge-futur">→ À venir</span>
                                    @else
                                        <span class="badge-passe">✓ Effectué</span>
                                    @endif

                                    {{-- Swap button: only for future slots without pending swap --}}
                                    @if($isFuture && !$echangeEnAttente && $tache)
                                        <button class="btn-swap"
                                            data-creneau-id="{{ $creneau->id }}"
                                            data-tache-id="{{ $tache->id }}"
                                            data-tache-libelle="{{ $tache->libelle }}"
                                            data-date="{{ $date->locale('fr')->isoFormat('dddd D MMMM YYYY') }}"
                                            onclick="openSwapModal(this)">
                                            🔄 Échanger
                                        </button>
                                    @endif
                                </div>

                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── Swap Modal ── --}}
    <div class="modal-backdrop" id="swapModalBackdrop" onclick="closeSwapOnBackdrop(event)">
        <div class="modal" id="swapModal">
            <div class="modal-header">
                <div class="modal-title">
                    <div style="width:28px;height:28px;background:var(--sky-bg);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;">🔄</div>
                    Demander un échange de créneau
                </div>
                <button class="modal-close" onclick="closeSwapModal()">×</button>
            </div>
            <div class="modal-body">

                {{-- My slot preview --}}
                <div class="my-slot-preview">
                    <div class="my-slot-preview-icon">📅</div>
                    <div>
                        <div class="my-slot-info-date" id="swapMyDate">—</div>
                        <div class="my-slot-info-tache" id="swapMyTache">—</div>
                    </div>
                </div>

                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--ink-muted);margin-bottom:10px;">
                    Choisir le créneau avec lequel échanger
                </div>

                <div id="slotsContainer">
                    <div class="slots-loading">⏳ Chargement des créneaux disponibles…</div>
                </div>

                <div style="display:flex;gap:9px;margin-top:16px;">
                    <button class="btn btn-primary" style="flex:1;justify-content:center;"
                        id="swapConfirmBtn" onclick="submitSwap()" disabled>
                        🔄 Envoyer la demande
                    </button>
                    <button class="btn btn-secondary" onclick="closeSwapModal()">Annuler</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

@endsection

@push('scripts')
<script>
const CSRF  = document.querySelector('meta[name="csrf-token"]').content;
const ROUTE_SLOTS  = '{{ route("echanges.slots") }}';
const ROUTE_STORE  = '{{ route("echanges.store") }}';

let swapCreneauId = null;
let swapTacheId   = null;
let selectedSlot  = null;

// ── Open modal ──────────────────────────────────────────────────────────────
async function openSwapModal(btn) {
    swapCreneauId = btn.dataset.creneauId;
    swapTacheId   = btn.dataset.tacheId;
    selectedSlot  = null;

    document.getElementById('swapMyDate').textContent  = btn.dataset.date;
    document.getElementById('swapMyTache').textContent = '🔄 Tâche : ' + btn.dataset.tacheLibelle;
    document.getElementById('swapConfirmBtn').disabled = true;
    document.getElementById('slotsContainer').innerHTML =
        '<div class="slots-loading">⏳ Chargement des créneaux disponibles…</div>';

    document.getElementById('swapModalBackdrop').classList.add('open');
    document.body.style.overflow = 'hidden';

    await loadSlots();
}

function closeSwapModal() {
    document.getElementById('swapModalBackdrop').classList.remove('open');
    document.body.style.overflow = '';
    swapCreneauId = null;
    swapTacheId   = null;
    selectedSlot  = null;
}

function closeSwapOnBackdrop(e) {
    if (e.target === document.getElementById('swapModalBackdrop')) closeSwapModal();
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSwapModal(); });

// ── Load available slots via AJAX ────────────────────────────────────────────
async function loadSlots() {
    try {
        const url = ROUTE_SLOTS + '?creneau_id=' + swapCreneauId + '&tache_id=' + swapTacheId;
        const res  = await fetch(url, {
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        });
        const slots = await res.json();

        if (!slots.length) {
            document.getElementById('slotsContainer').innerHTML = `
                <div class="slots-empty">
                    😕 Aucun créneau disponible pour cet échange.<br>
                    <span style="font-size:12px;margin-top:4px;display:block;">
                        Il faut qu'un autre membre soit assigné à la même tâche dans le futur.
                    </span>
                </div>`;
            return;
        }

        const list = document.createElement('div');
        list.className = 'slots-list';

        slots.forEach((slot, i) => {
            const item = document.createElement('label');
            item.className = 'slot-option';
            item.innerHTML = `
                <input type="radio" name="slot_choice" value="${i}"
                    data-creneau-id="${slot.creneau_id}"
                    data-tache-id="${slot.tache_id}"
                    data-personne-id="${slot.personne_id}"
                    onchange="onSlotSelect(this, ${JSON.stringify(slot).replace(/"/g, '&quot;')})">
                <div style="flex:1;">
                    <div class="slot-option-date">${slot.date_label}</div>
                    <div class="slot-option-meta">
                        ${slot.tache_libelle} · avec <strong>${slot.personne_nom}</strong>
                    </div>
                </div>`;
            list.appendChild(item);
        });

        document.getElementById('slotsContainer').innerHTML = '';
        document.getElementById('slotsContainer').appendChild(list);

    } catch (err) {
        document.getElementById('slotsContainer').innerHTML =
            '<div class="slots-empty" style="color:var(--rose);">❌ Erreur lors du chargement.</div>';
    }
}

function onSlotSelect(radio, slot) {
    selectedSlot = slot;
    document.getElementById('swapConfirmBtn').disabled = false;
    document.querySelectorAll('.slot-option').forEach(el => el.classList.remove('selected'));
    radio.closest('.slot-option').classList.add('selected');
}

// ── Submit swap request ──────────────────────────────────────────────────────
async function submitSwap() {
    if (!selectedSlot) return;

    const btn = document.getElementById('swapConfirmBtn');
    btn.disabled = true;
    btn.textContent = '⏳ Envoi…';

    try {
        const res = await fetch(ROUTE_STORE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                creneau_demandeur_id: parseInt(swapCreneauId),
                tache_demandeur_id:   parseInt(swapTacheId),
                creneau_cible_id:     selectedSlot.creneau_id,
                tache_cible_id:       selectedSlot.tache_id,
                personne_cible_id:    selectedSlot.personne_id,
            }),
        });

        const data = await res.json();

        if (data.success) {
            closeSwapModal();
            showToast(data.message, 'success');
            // Reload after a moment so the "pending" badge appears
            setTimeout(() => window.location.reload(), 2500);
        } else {
            showToast(data.message || 'Erreur lors de la demande.', 'error');
            btn.disabled = false;
            btn.textContent = '🔄 Envoyer la demande';
        }
    } catch {
        showToast('Erreur réseau.', 'error');
        btn.disabled = false;
        btn.textContent = '🔄 Envoyer la demande';
    }
}

// ── Toasts ───────────────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span>${type === 'success' ? '✅' : '❌'}</span><span>${msg}</span>`;
    c.appendChild(t);
    setTimeout(() => {
        t.style.animation = 'toastOut 0.3s ease forwards';
        setTimeout(() => t.remove(), 300);
    }, 4000);
}
</script>
@endpush
