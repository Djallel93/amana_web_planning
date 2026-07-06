{{-- resources/views/echanges/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Mes échanges — AMANA')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Mes échanges</h1>
        <p class="text-[13px] text-ink-muted mt-1">Demandes d'échange de créneaux envoyées et reçues</p>
    </div>
    <a href="{{ route('mon-planning') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2.5 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
        ← Mon planning
    </a>
</div>

@if($echanges->isEmpty())
    <div class="bg-surface rounded-xl border border-surface-border shadow-sm">
        <div class="text-center py-16 px-8">
            <div class="text-5xl mb-3 opacity-40">🔄</div>
            <h3 class="font-heading text-base font-semibold text-ink mb-1.5">Aucun échange</h3>
            <p class="text-ink-muted text-[13.5px] mb-6">
                Vous n'avez pas encore initié ou reçu de demande d'échange.<br>
                Pour demander un échange, cliquez sur « 🔄 Échanger » dans Mon planning.
            </p>
            <a href="{{ route('mon-planning') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-accent hover:bg-accent-dark text-white text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
                📅 Aller à mon planning
            </a>
        </div>
    </div>
@else
    <div class="flex flex-col gap-3">
        @foreach($echanges as $echange)
            @php
                $isDemandeur   = $echange->id_personne_demandeur === $user->id;
                $autrePersonne = $isDemandeur ? $echange->cible      : $echange->demandeur;
                $monSlotCreneau  = $isDemandeur ? $echange->creneauDemandeur : $echange->creneauCible;
                $monSlotTache    = $isDemandeur ? $echange->tacheDemandeur   : $echange->tacheCible;
                $sonSlotCreneau  = $isDemandeur ? $echange->creneauCible     : $echange->creneauDemandeur;
                $sonSlotTache    = $isDemandeur ? $echange->tacheCible       : $echange->tacheDemandeur;

                $statutConfig = [
                    'en_attente' => ['icon' => '⏳', 'label' => 'En attente', 'card'  => 'border-l-amber-400',  'badge' => 'bg-amber-50 text-amber-800 border-amber-200'],
                    'accepte'    => ['icon' => '✅', 'label' => 'Accepté',    'card'  => 'border-l-emerald-400', 'badge' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                    'refuse'     => ['icon' => '✕',  'label' => 'Refusé',    'card'  => 'border-l-rose-400',    'badge' => 'bg-rose-50 text-rose-800 border-rose-200'],
                    'expire'     => ['icon' => '⌛', 'label' => 'Expiré',    'card'  => 'border-l-ink-faint',   'badge' => 'bg-surface-3 text-ink-muted border-surface-border'],
                    'annule'     => ['icon' => '✗',  'label' => 'Annulé',    'card'  => 'border-l-ink-faint',   'badge' => 'bg-surface-3 text-ink-muted border-surface-border'],
                ];
                $sc = $statutConfig[$echange->statut] ?? $statutConfig['expire'];
                $dimmed = in_array($echange->statut, ['refuse', 'expire', 'annule']);
            @endphp

            <div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden border-l-[3px] {{ $sc['card'] }} {{ $dimmed ? 'opacity-70' : '' }} transition-shadow hover:shadow">
                <div class="px-5 py-4">

                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
                        <div>
                            <p class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] mb-1">
                                {{ $isDemandeur ? 'Vous avez demandé' : 'Reçu de' }}
                            </p>
                            <p class="font-heading text-[14px] font-semibold text-ink">
                                @if($isDemandeur)
                                    Échange avec {{ $autrePersonne->prenom }} {{ $autrePersonne->nom }}
                                @else
                                    {{ $autrePersonne->prenom }} {{ $autrePersonne->nom }} vous propose un échange
                                @endif
                            </p>
                            <p class="text-[12px] text-ink-muted mt-0.5">
                                {{ $echange->created_at->locale('fr')->isoFormat('D MMM YYYY à HH:mm') }}
                            </p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11.5px] font-bold border flex-shrink-0 {{ $sc['badge'] }}">
                            {{ $sc['icon'] }} {{ $sc['label'] }}
                        </span>
                    </div>

                    {{-- Slots --}}
                    <div class="flex items-center gap-3 flex-wrap p-3 bg-surface-2 border border-surface-border rounded-lg">
                        <div class="flex flex-col gap-0.5 px-3 py-2 bg-surface border border-surface-border rounded-lg flex-1 min-w-[130px]">
                            <span class="font-bold text-[12.5px] text-ink">
                                {{ $monSlotCreneau->date->locale('fr')->isoFormat('ddd D MMM YYYY') }}
                            </span>
                            <span class="text-ink-muted text-[11.5px]">
                                {{ $monSlotTache->libelle }} · {{ $monSlotCreneau->jour }}
                            </span>
                        </div>
                        <span class="text-accent text-lg flex-shrink-0 font-bold">⇄</span>
                        <div class="flex flex-col gap-0.5 px-3 py-2 bg-surface border border-surface-border rounded-lg flex-1 min-w-[130px]">
                            <span class="font-bold text-[12.5px] text-ink">
                                {{ $sonSlotCreneau->date->locale('fr')->isoFormat('ddd D MMM YYYY') }}
                            </span>
                            <span class="text-ink-muted text-[11.5px]">
                                {{ $sonSlotTache->libelle }} · {{ $sonSlotCreneau->jour }}
                            </span>
                        </div>
                        @if($echange->approuve_par && $echange->approbateur)
                            <span class="text-[11.5px] text-accent font-semibold ml-auto">
                                ✓ approuvé par {{ $echange->approbateur->prenom }}
                            </span>
                        @endif
                    </div>

                    @if($echange->statut === 'en_attente')
                        <p class="text-[11.5px] text-ink-muted mt-2">
                            ⏰ Expire le {{ $echange->expires_at->locale('fr')->isoFormat('D MMMM YYYY') }}
                        </p>
                    @endif

                    {{-- Actions --}}
                    @if($echange->statut === 'en_attente' && $isDemandeur)
                        <div class="mt-3 flex gap-2 flex-wrap">
                            <form action="{{ route('echanges.destroy', $echange->id) }}" method="POST"
                                  onsubmit="return confirm('Annuler cette demande d\'échange ?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-[12.5px] font-semibold rounded-lg cursor-pointer transition-colors min-h-[44px]
                                               border border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink bg-transparent">
                                    ✗ Annuler ma demande
                                </button>
                            </form>
                        </div>
                    @endif

                </div>
            </div>
        @endforeach
    </div>
@endif

@endsection
