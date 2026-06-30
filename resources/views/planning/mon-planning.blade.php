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

{{--
    Point de montage Vue — SwapRequestModal.vue remplace le bloc modal
    et le script inline ci-dessous. Le composant est monté par app.ts
    sur #vue-swap-modal.
--}}
<div id="vue-swap-modal"></div>

{{--
    toastContainer supprimé : les toasts sont maintenant gérés par
    Toast.vue monté globalement sur #vue-toast dans layouts/app.blade.php.
--}}

@endsection

@push('scripts')
<script>
{{--
    MonPlanningConfig : injecte les routes Laravel dans window pour que
    SwapRequestModal.vue puisse les consommer sans dépendre de Blade.
    C'est le même pattern que window.PlanningConfig dans PlanningGrid.vue.
--}}
window.MonPlanningConfig = {
    routeSlots: '{{ route("echanges.slots") }}',
    routeStore: '{{ route("echanges.store") }}',
};
</script>
@endpush

