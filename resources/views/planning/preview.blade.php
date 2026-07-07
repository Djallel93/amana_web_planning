{{-- resources/views/planning/preview.blade.php --}}
@extends('layouts.app')

@section('title', 'Aperçu du planning — AMANA')

@section('content')

{{-- Filigrane --}}
<div class="fixed inset-0 flex items-center justify-center pointer-events-none z-0 select-none overflow-hidden">
    <span class="font-heading text-[96px] font-black text-amber-400/[0.05] rotate-[-30deg] whitespace-nowrap">APERÇU</span>
</div>

{{-- Bannière --}}
<div class="flex flex-wrap items-center gap-4 px-5 py-4 mb-6 bg-amber-50 border-[1.5px] border-amber-300 rounded-xl relative z-10">
    <span class="text-3xl flex-shrink-0">👁️</span>
    <div class="flex-1 min-w-0">
        <h2 class="font-heading text-[15px] font-bold text-amber-900 mb-0.5">Aperçu — aucune donnée enregistrée</h2>
        <p class="text-[12.5px] text-amber-700 leading-relaxed">
            Ce planning est une simulation. Rien n'a été modifié en base.
            Vérifiez les assignations puis confirmez si tout vous convient.<br>
            <strong>{{ count($propositions['creneaux']) }} créneaux</strong>
            proposés · durée du calcul : {{ $propositions['duree_ms'] }}ms
            · {{ $propositions['non_assignes'] }} non assigné(s)
        </p>
    </div>
    <div class="flex flex-wrap gap-2 flex-shrink-0">
        <form action="{{ route('planning.generate') }}" method="POST">
            @csrf
            <input type="hidden" name="date_debut" value="{{ $dateDebut }}">
            <input type="hidden" name="semaines"   value="{{ $semaines }}">
            <input type="hidden" name="confirmed"  value="1">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-accent hover:bg-accent-dark text-white text-[13px] font-bold rounded-lg
                           shadow-[0_3px_12px_rgba(3,105,161,0.3)] transition-all cursor-pointer min-h-[44px]">
                ✨ Confirmer et générer
            </button>
        </form>
        <a href="{{ route('planning.generate.form') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2.5 border-[1.5px] border-amber-300 text-amber-800 hover:bg-amber-100 text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
            ← Modifier
        </a>
    </div>
</div>

{{-- Blocs semaine --}}
@php
    $parSemaine = collect($propositions['creneaux'])
        ->groupBy(fn($c) => $c['semaine'] . '-' . \Carbon\Carbon::parse($c['date'])->year);
    $tachesMeta = [
        'entree'     => ['🚪 Entrée',     'text-[#2563eb]'],
        'mektaba'    => ['📚 Mektaba',    'text-[#059669]'],
        'salle'      => ['🏛️ Salle',      'text-[#d97706]'],
        'amana_food' => ['🥪 Amana Food', 'text-[#e11d48]'],
        'cours'      => ['🎓 Cours',      'text-[#7c3aed]'],
    ];
@endphp

@foreach($parSemaine as $semaineKey => $jours)
    @php
        $firstJour  = $jours->first();
        $lastJour   = $jours->last();
        $firstDate  = \Carbon\Carbon::parse($firstJour['date']);
        $lastDate   = \Carbon\Carbon::parse($lastJour['date']);
    @endphp
    <div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden mb-4 relative z-10">
        <div class="flex items-center justify-between px-5 py-3 bg-sidebar border-b border-white/[0.06]">
            <div class="flex items-center gap-2.5 font-heading text-[13px] font-semibold text-white">
                📅
                <span class="bg-white/10 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold font-body">S{{ $firstJour['semaine'] }}</span>
                {{ $firstDate->locale('fr')->isoFormat('D MMMM') }} — {{ $lastDate->locale('fr')->isoFormat('D MMMM YYYY') }}
            </div>
            <span class="text-[12px] text-white/40">{{ $jours->count() }} jours</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[13px]" style="min-width:680px;">
                <thead>
                    <tr>
                        <th class="text-left px-5 py-2.5 text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] bg-surface-2 border-b border-surface-3 font-body w-40">Jour</th>
                        @foreach($tachesMeta as $code => [$label, $color])
                            <th class="text-left px-3 py-2.5 text-[11px] font-bold bg-surface-2 border-b border-surface-3 font-body {{ $color }}">{{ $label }}</th>
                        @endforeach
                        <th class="text-left px-3 py-2.5 text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] bg-surface-2 border-b border-surface-3 font-body">Événements</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($jours as $jour)
                        <tr class="border-b border-surface-3 last:border-0 hover:bg-surface-2 transition-colors">
                            <td class="px-5 py-2.5">
                                <div class="flex items-center gap-2">
                                    <strong class="font-heading text-[13px] text-ink">{{ $jour['jour'] }}</strong>
                                    <span class="text-ink-muted text-[12px]">{{ $jour['date_label'] }}</span>
                                </div>
                            </td>
                            @foreach(['entree','mektaba','salle','amana_food','cours'] as $code)
                                @php $td = $jour['taches'][$code] ?? null; @endphp
                                <td class="px-3 py-2.5 {{ ($td['bloquee'] ?? false) ? 'bg-orange-50' : '' }}">
                                    @if(!$td || (!$td['bloquee'] && !$td['nom_complet']))
                                        <span class="tache-vide">—</span>
                                    @elseif($td['bloquee'])
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700 border border-amber-200">🚫 Bloqué</span>
                                    @else
                                        <span class="chip-{{ $code }} inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold">{{ $td['nom_complet'] }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-3 py-2.5">
                                @if($jour['evenements'])
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700 border border-amber-200">🎉 {{ $jour['evenements'] }}</span>
                                @else
                                    <span class="text-ink-faint text-xs">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endforeach

{{-- Bande de confirmation bas de page --}}
<div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 bg-surface border-[1.5px] border-amber-300 rounded-xl shadow-sm relative z-10">
    <div>
        <p class="font-heading text-[14px] font-semibold text-ink">Ce planning vous convient ?</p>
        <p class="text-[12.5px] text-ink-muted mt-0.5">Cliquez sur "Confirmer et générer" pour l'enregistrer définitivement.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <form action="{{ route('planning.generate') }}" method="POST">
            @csrf
            <input type="hidden" name="date_debut" value="{{ $dateDebut }}">
            <input type="hidden" name="semaines"   value="{{ $semaines }}">
            <input type="hidden" name="confirmed"  value="1">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-5 py-3 bg-accent hover:bg-accent-dark text-white font-bold text-[13.5px] rounded-lg
                           shadow-[0_3px_14px_rgba(3,105,161,0.35)] hover:-translate-y-px transition-all cursor-pointer min-h-[48px]">
                ✨ Confirmer et générer
            </button>
        </form>
        <a href="{{ route('planning.generate.form') }}"
           class="inline-flex items-center gap-1.5 px-5 py-3 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink font-semibold text-[13.5px] rounded-lg transition-colors no-underline min-h-[48px]">
            ← Modifier
        </a>
    </div>
</div>

@endsection
