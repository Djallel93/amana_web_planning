{{-- resources/views/echanges/admin.blade.php --}}
@extends('layouts.app')

@section('title', 'Gestion des échanges — AMANA')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Gestion des échanges</h1>
        <p class="text-[13px] text-ink-muted mt-1">Approuvez ou refusez les demandes d'échange en attente</p>
    </div>
</div>

@if($nbEnAttente > 0)
    <div class="flex items-center gap-3 px-4 py-3 mb-5 bg-amber-50 border border-amber-200 rounded-lg text-[13px] text-amber-800">
        <span>⏳</span>
        <span>{{ $nbEnAttente }} demande{{ $nbEnAttente > 1 ? 's' : '' }} en attente de décision.</span>
    </div>
@endif

<div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
    <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
        <div class="w-7 h-7 bg-amber-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">🔄</div>
        <span class="font-heading text-[14px] font-semibold text-ink">Toutes les demandes d'échange</span>
    </div>

    {{-- Table desktop (≥ lg) --}}
    <div class="hidden lg:block overflow-x-auto">
        <table class="w-full border-collapse text-[13px]">
            <thead>
                <tr>
                    @foreach(['Date', 'Demandeur', 'Son créneau', '⇄', 'Cible', 'Créneau cible', 'Statut', 'Expire le', ''] as $col)
                        <th class="text-left px-4 py-2.5 text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px]
                                   bg-surface-2 border-b border-surface-3 font-body whitespace-nowrap
                                   {{ $col === '⇄' ? 'text-center' : '' }}">
                            {{ $col }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($echanges as $echange)
                    @php
                        $isAttente = $echange->statut === 'en_attente';
                        $statutConfig = [
                            'en_attente' => ['icon' => '⏳', 'label' => 'En attente', 'badge' => 'bg-amber-50 text-amber-800 border-amber-200'],
                            'accepte'    => ['icon' => '✅', 'label' => 'Accepté',    'badge' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                            'refuse'     => ['icon' => '✕',  'label' => 'Refusé',    'badge' => 'bg-rose-50 text-rose-800 border-rose-200'],
                            'expire'     => ['icon' => '⌛', 'label' => 'Expiré',    'badge' => 'bg-surface-3 text-ink-muted border-surface-border'],
                            'annule'     => ['icon' => '✗',  'label' => 'Annulé',    'badge' => 'bg-surface-3 text-ink-muted border-surface-border'],
                        ];
                        $sc = $statutConfig[$echange->statut] ?? $statutConfig['expire'];
                    @endphp
                    <tr class="border-b border-surface-3 last:border-0 transition-colors {{ $isAttente ? 'bg-amber-50 hover:bg-amber-100/70' : 'hover:bg-surface-2' }}">
                        <td class="px-4 py-3 text-[12px] text-ink-muted whitespace-nowrap">
                            {{ $echange->created_at->locale('fr')->isoFormat('D MMM HH:mm') }}
                        </td>
                        <td class="px-4 py-3 font-semibold text-ink text-[13px]">
                            {{ $echange->demandeur?->prenom }} {{ $echange->demandeur?->nom }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-semibold text-[12.5px] text-ink">
                                    {{ $echange->creneauDemandeur?->date?->locale('fr')->isoFormat('ddd D MMM YYYY') ?? '—' }}
                                </span>
                                <span class="text-ink-muted text-[11.5px]">{{ $echange->tacheDemandeur?->libelle ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center text-accent text-xl">⇄</td>
                        <td class="px-4 py-3 font-semibold text-ink text-[13px]">
                            {{ $echange->cible?->prenom }} {{ $echange->cible?->nom }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-semibold text-[12.5px] text-ink">
                                    {{ $echange->creneauCible?->date?->locale('fr')->isoFormat('ddd D MMM YYYY') ?? '—' }}
                                </span>
                                <span class="text-ink-muted text-[11.5px]">{{ $echange->tacheCible?->libelle ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold border whitespace-nowrap {{ $sc['badge'] }}">
                                {{ $sc['icon'] }} {{ $sc['label'] }}
                            </span>
                            @if($echange->approuve_par && $echange->approbateur)
                                <div class="text-[11px] text-ink-muted mt-0.5">par {{ $echange->approbateur->prenom }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-[12px] text-ink-muted whitespace-nowrap">
                            @if($isAttente)
                                {{ $echange->expires_at->locale('fr')->isoFormat('D MMM YYYY') }}
                                @if($echange->expires_at->isPast())
                                    <span class="text-rose-500 font-semibold"> (passée)</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($isAttente)
                                <div class="flex gap-1.5 flex-wrap">
                                    <form action="{{ route('admin.echanges.approuver', $echange->id) }}" method="POST"
                                          data-confirm="Approuver et exécuter cet échange immédiatement ?">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11.5px] font-semibold rounded-lg cursor-pointer transition-colors min-h-[44px]
                                                       bg-emerald-50 border border-emerald-200 text-emerald-700 hover:bg-emerald-100">
                                            ✅ Approuver
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.echanges.refuser', $echange->id) }}" method="POST"
                                          data-confirm="Refuser cet échange ? Le demandeur sera notifié." data-confirm-danger>
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11.5px] font-semibold rounded-lg cursor-pointer transition-colors min-h-[44px]
                                                       bg-rose-50 border border-rose-200 text-rose-700 hover:bg-rose-100">
                                            ✕ Refuser
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="text-ink-faint text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9">
                        <div class="text-center py-12 px-8">
                            <div class="text-4xl mb-2 opacity-40">🔄</div>
                            <p class="font-heading text-sm font-semibold text-ink mb-1">Aucune demande d'échange</p>
                            <p class="text-ink-muted text-[13px]">Les demandes apparaîtront ici.</p>
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Cartes mobile (< lg) --}}
    <div class="lg:hidden divide-y divide-surface-3">
        @forelse($echanges as $echange)
            @php
                $isAttente = $echange->statut === 'en_attente';
                $statutConfig = [
                    'en_attente' => ['icon' => '⏳', 'label' => 'En attente', 'card' => 'border-l-amber-400',  'badge' => 'bg-amber-50 text-amber-800 border-amber-200'],
                    'accepte'    => ['icon' => '✅', 'label' => 'Accepté',    'card' => 'border-l-emerald-400', 'badge' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                    'refuse'     => ['icon' => '✕',  'label' => 'Refusé',    'card' => 'border-l-rose-400',    'badge' => 'bg-rose-50 text-rose-800 border-rose-200'],
                    'expire'     => ['icon' => '⌛', 'label' => 'Expiré',    'card' => 'border-l-ink-faint',   'badge' => 'bg-surface-3 text-ink-muted border-surface-border'],
                    'annule'     => ['icon' => '✗',  'label' => 'Annulé',    'card' => 'border-l-ink-faint',   'badge' => 'bg-surface-3 text-ink-muted border-surface-border'],
                ];
                $sc = $statutConfig[$echange->statut] ?? $statutConfig['expire'];
            @endphp
            <div class="px-4 py-4 border-l-[3px] {{ $sc['card'] }} {{ $isAttente ? 'bg-amber-50/60' : '' }}">
                <div class="flex items-start justify-between gap-2 mb-2.5">
                    <div>
                        <p class="font-semibold text-[13px] text-ink">
                            {{ $echange->demandeur?->prenom }} {{ $echange->demandeur?->nom }}
                            <span class="text-ink-muted font-normal text-[12px]">→ {{ $echange->cible?->prenom }} {{ $echange->cible?->nom }}</span>
                        </p>
                        <p class="text-[11.5px] text-ink-muted mt-0.5">{{ $echange->created_at->locale('fr')->isoFormat('D MMM HH:mm') }}</p>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold border flex-shrink-0 {{ $sc['badge'] }}">
                        {{ $sc['icon'] }} {{ $sc['label'] }}
                    </span>
                </div>

                <div class="flex items-center gap-2 flex-wrap p-2.5 bg-surface border border-surface-border rounded-lg mb-2.5 text-[12px]">
                    <div class="flex flex-col gap-0.5 flex-1 min-w-[110px]">
                        <span class="font-semibold text-ink">{{ $echange->creneauDemandeur?->date?->locale('fr')->isoFormat('D MMM YYYY') ?? '—' }}</span>
                        <span class="text-ink-muted text-[11px]">{{ $echange->tacheDemandeur?->libelle ?? '—' }}</span>
                    </div>
                    <span class="text-accent font-bold">⇄</span>
                    <div class="flex flex-col gap-0.5 flex-1 min-w-[110px]">
                        <span class="font-semibold text-ink">{{ $echange->creneauCible?->date?->locale('fr')->isoFormat('D MMM YYYY') ?? '—' }}</span>
                        <span class="text-ink-muted text-[11px]">{{ $echange->tacheCible?->libelle ?? '—' }}</span>
                    </div>
                </div>

                @if($isAttente)
                    <div class="flex gap-2 flex-wrap">
                        <form action="{{ route('admin.echanges.approuver', $echange->id) }}" method="POST"
                              data-confirm="Approuver cet échange ?">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-1 px-3 py-2 text-[12.5px] font-semibold rounded-lg cursor-pointer transition-colors min-h-[44px]
                                           bg-emerald-50 border border-emerald-200 text-emerald-700 hover:bg-emerald-100">
                                ✅ Approuver
                            </button>
                        </form>
                        <form action="{{ route('admin.echanges.refuser', $echange->id) }}" method="POST"
                              data-confirm="Refuser cet échange ?" data-confirm-danger>
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-1 px-3 py-2 text-[12.5px] font-semibold rounded-lg cursor-pointer transition-colors min-h-[44px]
                                           bg-rose-50 border border-rose-200 text-rose-700 hover:bg-rose-100">
                                ✕ Refuser
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <div class="text-center py-12 px-8">
                <div class="text-4xl mb-2 opacity-40">🔄</div>
                <p class="text-ink-muted text-[13px]">Aucune demande d'échange.</p>
            </div>
        @endforelse
    </div>

    @if($echanges->hasPages())
        <div class="px-5 py-4 border-t border-surface-3">{{ $echanges->links() }}</div>
    @endif
</div>

@endsection
