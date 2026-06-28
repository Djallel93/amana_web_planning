{{-- resources/views/planning/mon-planning.blade.php --}}
@extends('layouts.app')

@section('title', 'Mon planning — AMANA')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Mon planning</h1>
        <p class="text-[13px] text-ink-muted mt-1">Vos permanences — un an glissant + futur</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('echanges.index') }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
            🔄 Mes échanges
        </a>
        <a href="{{ route('planning.index') }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
            📅 Planning complet
        </a>
    </div>
</div>

{{-- Stats strip --}}
@php
    $tachesMeta = [
        'entree'     => ['Entrée',     '🚪'],
        'mektaba'    => ['Mektaba',    '📚'],
        'salle'      => ['Salle',      '🏛️'],
        'amana_food' => ['Amana Food', '🥪'],
        'cours'      => ['Cours',      '🎓'],
    ];
@endphp
<div class="flex flex-wrap gap-2.5 mb-6">
    <div class="bg-white border border-surface-border rounded-xl shadow-sm px-4 py-3 flex flex-col gap-0.5 min-w-[90px]">
        <div class="font-heading text-2xl font-bold text-ink">{{ $total }}</div>
        <div class="text-[10.5px] font-bold uppercase tracking-[0.6px] text-ink-muted">Total</div>
    </div>
    <div class="bg-white border border-surface-border rounded-xl shadow-sm px-4 py-3 flex flex-col gap-0.5 min-w-[90px]">
        <div class="font-heading text-2xl font-bold text-accent">{{ $futures }}</div>
        <div class="text-[10.5px] font-bold uppercase tracking-[0.6px] text-ink-muted">À venir</div>
    </div>
    @foreach($parTache as $code => $count)
        @if(isset($tachesMeta[$code]))
            <div class="bg-white border border-surface-border rounded-xl shadow-sm px-4 py-3 flex flex-col gap-0.5 min-w-[90px]">
                <div class="font-heading text-2xl font-bold text-ink">{{ $count }}</div>
                <div class="text-[10.5px] font-bold uppercase tracking-[0.6px] text-ink-muted">
                    {{ $tachesMeta[$code][1] }} {{ $tachesMeta[$code][0] }}
                </div>
            </div>
        @endif
    @endforeach
</div>

@if($parMois->isEmpty())
    <div class="bg-white rounded-xl border border-surface-border shadow-sm">
        <div class="text-center py-16 px-8">
            <div class="text-5xl mb-3 opacity-40">📭</div>
            <h3 class="font-heading text-base font-semibold text-ink mb-1.5">Aucune permanence</h3>
            <p class="text-ink-muted text-[13.5px]">Vous n'avez pas encore été assigné à des créneaux sur les 12 derniers mois.</p>
        </div>
    </div>
@else
    <div class="flex flex-col gap-7">
        @foreach($parMois as $moisKey => $lignes)
            @php
                $firstDate = $lignes->first()->creneau->date;
                $moisLabel = ucfirst($firstDate->locale('fr')->isoFormat('MMMM YYYY'));
            @endphp
            <div>
                {{-- En-tête mois --}}
                <div class="flex items-center gap-3 mb-3.5">
                    <span class="font-heading text-[13px] font-bold uppercase tracking-[1.2px] text-ink-muted">{{ $moisLabel }}</span>
                    <div class="flex-1 h-px bg-surface-border"></div>
                    <span class="text-[11px] text-ink-faint font-semibold whitespace-nowrap">
                        {{ $lignes->count() }} créneau{{ $lignes->count() > 1 ? 'x' : '' }}
                    </span>
                </div>

                {{-- Cartes créneaux --}}
                <div class="flex flex-col gap-2.5">
                    @foreach($lignes as $ligne)
                        @php
                            $creneau  = $ligne->creneau;
                            $tache    = $ligne->tache;
                            $date     = $creneau->date;
                            $isToday  = $date->isToday();
                            $isFuture = $date->isFuture() && !$isToday;
                            $isPast   = $date->isPast() && !$isToday;
                            $evtStr   = $creneau->evenements?->pluck('nom')->implode(', ');
                            $echangeEnAttente = $echangesEnAttente->first(fn($e) =>
                                ($e->id_creneau_demandeur === $creneau->id && $e->id_tache_demandeur === $tache?->id)
                                || ($e->id_creneau_cible === $creneau->id && $e->id_tache_cible === $tache?->id)
                            );
                            $borderColor = $isToday ? 'border-l-emerald-400' : ($isFuture ? 'border-l-accent' : 'border-l-surface-3');
                            $bgColor     = $isToday ? 'bg-emerald-50' : 'bg-white';
                            $icons = ['entree'=>'🚪','mektaba'=>'📚','salle'=>'🏛️','amana_food'=>'🥪','cours'=>'🎓'];
                        @endphp

                        <div class="relative flex items-center gap-4 sm:gap-5 px-4 py-3.5 {{ $bgColor }} rounded-xl border border-surface-border border-l-[3px] {{ $borderColor }} shadow-sm
                                    {{ $isPast ? 'opacity-70' : '' }} {{ $isFuture ? 'hover:shadow transition-shadow' : '' }}">

                            @if($echangeEnAttente)
                                <span class="absolute top-2.5 right-3.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-bold bg-amber-50 border border-amber-200 text-amber-800">
                                    ⏳ Échange en attente
                                </span>
                            @endif

                            {{-- Date --}}
                            <div class="flex-shrink-0 w-14 text-center">
                                <div class="font-heading text-[26px] font-bold text-ink leading-none">{{ $date->format('d') }}</div>
                                <div class="text-[10.5px] font-bold uppercase tracking-[0.7px] text-ink-muted">{{ $date->locale('fr')->isoFormat('MMM') }}</div>
                                <div class="text-[10px] text-ink-faint font-semibold mt-0.5">{{ $creneau->jour }}</div>
                            </div>

                            <div class="w-px h-11 bg-surface-3 flex-shrink-0 hidden sm:block"></div>

                            {{-- Infos --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    @if($tache)
                                        <span class="chip-{{ $tache->code }} inline-flex items-center px-2.5 py-0.5 rounded-full text-[12px] font-semibold">
                                            {{ $icons[$tache->code] ?? '' }} {{ $tache->libelle }}
                                        </span>
                                    @endif
                                    <span class="text-[11px] text-ink-muted bg-surface-2 border border-surface-border px-2 py-0.5 rounded-full font-semibold">
                                        S{{ $creneau->semaine }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 flex-wrap text-[12px] text-ink-muted">
                                    <span>{{ $date->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</span>
                                    @if($evtStr)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 border border-amber-200 text-amber-700">
                                            🎉 {{ $evtStr }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Statut + action --}}
                            <div class="flex-shrink-0 flex flex-col items-end gap-2">
                                @if($isToday)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 border border-emerald-200 text-emerald-700">● Aujourd'hui</span>
                                @elseif($isFuture)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-sky-50 border border-sky-200 text-sky-700">→ À venir</span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-surface-3 border border-surface-border text-ink-muted">✓ Effectué</span>
                                @endif

                                @if($isFuture && !$echangeEnAttente && $tache)
                                    <button class="inline-flex items-center gap-1 px-3 py-1.5 border-[1.5px] border-accent text-accent hover:bg-sky-50 text-[11.5px] font-semibold rounded-lg cursor-pointer transition-colors bg-transparent min-h-[44px]"
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

{{-- Modale échange --}}
<div class="fixed inset-0 bg-black/45 backdrop-blur-sm z-[400] flex items-center justify-center p-4
            opacity-0 pointer-events-none transition-opacity duration-200"
     id="swapModalBackdrop"
     onclick="closeSwapOnBackdrop(event)">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-md transform scale-95 transition-transform duration-200"
         id="swapModal">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">🔄</div>
            <span class="font-heading text-[14px] font-semibold text-ink flex-1">Demander un échange de créneau</span>
            <button onclick="closeSwapModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-md text-ink-muted hover:bg-surface-3 hover:text-ink transition-colors bg-transparent border-0 cursor-pointer text-lg leading-none min-h-[44px] min-w-[44px]">
                ×
            </button>
        </div>
        <div class="p-5 flex flex-col gap-4">
            <div class="flex items-center gap-3 px-4 py-3 bg-sky-50 border border-sky-200 rounded-lg">
                <span class="text-xl flex-shrink-0">📅</span>
                <div>
                    <div class="font-bold text-[13.5px] text-ink" id="swapMyDate">—</div>
                    <div class="text-[12.5px] text-ink-muted mt-0.5" id="swapMyTache">—</div>
                </div>
            </div>

            <div>
                <p class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] mb-2">Choisir le créneau avec lequel échanger</p>
                <div id="slotsContainer">
                    <div class="text-center py-8 text-[13.5px] text-ink-muted">⏳ Chargement des créneaux disponibles…</div>
                </div>
            </div>

            <div class="flex gap-2">
                <button id="swapConfirmBtn" onclick="submitSwap()" disabled
                        class="flex-1 min-h-[48px] px-4 py-2.5 bg-accent hover:bg-accent-dark text-white text-[13px] font-bold rounded-lg
                               shadow-[0_3px_12px_rgba(3,105,161,0.3)] transition-all cursor-pointer flex items-center justify-center gap-1.5
                               disabled:opacity-40 disabled:cursor-not-allowed">
                    🔄 Envoyer la demande
                </button>
                <button onclick="closeSwapModal()"
                        class="px-4 py-2.5 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors cursor-pointer bg-transparent min-h-[48px]">
                    Annuler
                </button>
            </div>
        </div>
    </div>
</div>

<div class="fixed bottom-5 right-5 z-[500] flex flex-col gap-2 pointer-events-none" id="toastContainer"></div>

@endsection

@push('scripts')
<script>
const CSRF        = document.querySelector('meta[name="csrf-token"]').content;
const ROUTE_SLOTS = '{{ route("echanges.slots") }}';
const ROUTE_STORE = '{{ route("echanges.store") }}';

let swapCreneauId = null, swapTacheId = null, selectedSlot = null;

function openModal()  {
    const bd = document.getElementById('swapModalBackdrop');
    bd.classList.remove('opacity-0','pointer-events-none');
    bd.querySelector('#swapModal').classList.remove('scale-95');
    bd.querySelector('#swapModal').classList.add('scale-100');
    document.body.style.overflow = 'hidden';
}
function closeSwapModal() {
    const bd = document.getElementById('swapModalBackdrop');
    bd.classList.add('opacity-0','pointer-events-none');
    bd.querySelector('#swapModal').classList.add('scale-95');
    bd.querySelector('#swapModal').classList.remove('scale-100');
    document.body.style.overflow = '';
    swapCreneauId = swapTacheId = selectedSlot = null;
}
function closeSwapOnBackdrop(e) {
    if (e.target === document.getElementById('swapModalBackdrop')) closeSwapModal();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSwapModal(); });

async function openSwapModal(btn) {
    swapCreneauId = btn.dataset.creneauId;
    swapTacheId   = btn.dataset.tacheId;
    selectedSlot  = null;
    document.getElementById('swapMyDate').textContent  = btn.dataset.date;
    document.getElementById('swapMyTache').textContent = '🔄 Tâche : ' + btn.dataset.tacheLibelle;
    document.getElementById('swapConfirmBtn').disabled = true;
    document.getElementById('slotsContainer').innerHTML =
        '<div class="text-center py-8 text-[13.5px] text-ink-muted">⏳ Chargement des créneaux disponibles…</div>';
    openModal();
    await loadSlots();
}

async function loadSlots() {
    try {
        const url  = ROUTE_SLOTS + '?creneau_id=' + swapCreneauId + '&tache_id=' + swapTacheId;
        const res  = await fetch(url, { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } });
        const slots = await res.json();
        if (!slots.length) {
            document.getElementById('slotsContainer').innerHTML =
                '<div class="text-center py-8 px-4 text-[13.5px] text-ink-muted bg-surface-2 rounded-lg border border-surface-border">😕 Aucun créneau disponible pour cet échange.</div>';
            return;
        }
        const list = document.createElement('div');
        list.className = 'flex flex-col gap-2 max-h-[280px] overflow-y-auto';
        slots.forEach((slot, i) => {
            const item = document.createElement('label');
            item.className = 'flex items-center gap-3 px-4 py-3 border-[1.5px] border-surface-border rounded-lg cursor-pointer transition-colors hover:border-accent hover:bg-sky-50';
            item.innerHTML = `
                <input type="radio" name="slot_choice" value="${i}"
                    data-creneau-id="${slot.creneau_id}" data-tache-id="${slot.tache_id}" data-personne-id="${slot.personne_id}"
                    onchange="onSlotSelect(this, ${JSON.stringify(slot).replace(/"/g,'&quot;')})"
                    class="w-4 h-4 accent-accent flex-shrink-0">
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-[13px] text-ink">${slot.date_label}</div>
                    <div class="text-[12px] text-ink-muted mt-0.5">${slot.tache_libelle} · avec <strong>${slot.personne_nom}</strong></div>
                </div>`;
            list.appendChild(item);
        });
        document.getElementById('slotsContainer').innerHTML = '';
        document.getElementById('slotsContainer').appendChild(list);
    } catch {
        document.getElementById('slotsContainer').innerHTML =
            '<div class="text-center py-6 text-rose-600 text-[13px]">❌ Erreur lors du chargement.</div>';
    }
}

function onSlotSelect(radio, slot) {
    selectedSlot = slot;
    document.getElementById('swapConfirmBtn').disabled = false;
    document.querySelectorAll('#slotsContainer label').forEach(el => {
        el.classList.toggle('border-accent', false);
        el.classList.toggle('bg-sky-50', false);
    });
    radio.closest('label').classList.add('border-accent','bg-sky-50');
}

async function submitSwap() {
    if (!selectedSlot) return;
    const btn = document.getElementById('swapConfirmBtn');
    btn.disabled = true; btn.textContent = '⏳ Envoi…';
    try {
        const res = await fetch(ROUTE_STORE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
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
            setTimeout(() => window.location.reload(), 2500);
        } else {
            showToast(data.message || 'Erreur lors de la demande.', 'error');
            btn.disabled = false; btn.textContent = '🔄 Envoyer la demande';
        }
    } catch {
        showToast('Erreur réseau.', 'error');
        btn.disabled = false; btn.textContent = '🔄 Envoyer la demande';
    }
}

function showToast(msg, type = 'success') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    const colorClass = type === 'success' ? 'border-l-emerald-400' : 'border-l-rose-400';
    t.className = `pointer-events-auto flex items-center gap-2.5 bg-ink text-white px-4 py-3 rounded-xl shadow-lg border-l-[3px] ${colorClass} text-[13px] font-medium min-w-[240px]`;
    t.innerHTML = `<span>${type === 'success' ? '✅' : '❌'}</span><span>${msg}</span>`;
    c.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity 0.3s'; setTimeout(() => t.remove(), 300); }, 4000);
}
</script>
@endpush
