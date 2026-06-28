{{-- resources/views/statistics/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Statistiques — AMANA')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Statistiques</h1>
        @if($stats['dateDebut'] && $stats['dateFin'])
            <p class="text-[13px] text-ink-muted mt-1">
                Du {{ \Carbon\Carbon::parse($stats['dateDebut'])->locale('fr')->isoFormat('D MMM YYYY') }}
                au {{ \Carbon\Carbon::parse($stats['dateFin'])->locale('fr')->isoFormat('D MMM YYYY') }}
                — {{ $stats['totalDays'] }} créneaux
            </p>
        @endif
    </div>
    <a href="{{ route('planning.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2.5 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
        ← Planning
    </a>
</div>

@if(empty($stats['personnes']))
    <div class="bg-white rounded-xl border border-surface-border shadow-sm">
        <div class="text-center py-16 px-8">
            <div class="text-5xl mb-3 opacity-40">📊</div>
            <h3 class="font-heading text-base font-semibold text-ink mb-1.5">Aucune donnée</h3>
            <p class="text-ink-muted text-[13.5px] mb-6">Générez d'abord un planning pour voir les statistiques.</p>
            @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                <a href="{{ route('planning.generate.form') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-accent hover:bg-accent-dark text-white text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
                    ✨ Générer un planning
                </a>
            @endif
        </div>
    </div>
@else

    {{-- KPIs --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 mb-5">
        @foreach([
            ['value' => $stats['totalTasks'],        'label' => 'Total assignations',    'color' => 'text-accent'],
            ['value' => $stats['nbPersonnes'],        'label' => 'Personnes actives',     'color' => 'text-sky-500'],
            ['value' => $stats['moyenneTaches'],      'label' => 'Moyenne / personne',    'color' => 'text-emerald-600'],
            ['value' => $stats['maxConsecutif'],      'label' => 'Max jours consécutifs', 'color' => 'text-amber-500'],
            ['value' => $stats['tauxUtilisation'].'%','label' => "Taux d'utilisation",   'color' => 'text-violet-600'],
            ['value' => $stats['totalAbsenceDays'],   'label' => "Jours d'absence",       'color' => 'text-rose-500'],
        ] as $kpi)
            <div class="bg-white rounded-xl border border-surface-border shadow-sm p-4 flex flex-col gap-1">
                <div class="font-heading text-2xl font-bold {{ $kpi['color'] }}">{{ $kpi['value'] }}</div>
                <div class="text-[11px] font-bold uppercase tracking-[0.6px] text-ink-muted">{{ $kpi['label'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Fairness band --}}
    <div class="relative bg-sidebar rounded-xl overflow-hidden p-6 sm:p-7 mb-5">
        {{-- Glows --}}
        <div class="absolute -top-14 -right-14 w-56 h-56 rounded-full bg-accent/35 blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-10 left-10 w-40 h-40 rounded-full bg-sky-400/20 blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex items-start justify-between mb-5 gap-4 flex-wrap">
            <div>
                <h2 class="font-heading text-xl font-semibold text-white mb-1">
                    Score d'équité
                    @if($stats['fairnessScore'] >= 90) 🏆
                    @elseif($stats['fairnessScore'] >= 70) 👍
                    @else ⚠️
                    @endif
                </h2>
                <p class="text-[13px] text-white/50">
                    @if($stats['fairnessScore'] >= 90) Excellent — distribution très équilibrée
                    @elseif($stats['fairnessScore'] >= 70) Bon — quelques déséquilibres mineurs
                    @else À améliorer — distribution déséquilibrée
                    @endif
                </p>
            </div>
            <div class="text-right">
                <div class="font-heading text-5xl font-bold text-white leading-none tracking-tight">{{ $stats['fairnessScore'] }}</div>
                <div class="text-[11px] text-white/40 uppercase tracking-[0.8px] mt-1">/ 100</div>
            </div>
        </div>

        <div class="relative z-10 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-2.5 mb-4">
            @foreach([
                ['label' => 'Écart-type',        'value' => $stats['ecartType'],             'sub' => 'Plus bas = meilleur'],
                ['label' => 'Coeff. variation',  'value' => $stats['coefficientVariation'].'%','sub' => 'Écart relatif'],
                ['label' => 'Déséq. Ven./Sam.',  'value' => $stats['desequilibreMoyen'],     'sub' => 'Moy. par personne'],
                ['label' => 'Amana Food',         'value' => $stats['minAmanaFood'].'-'.$stats['maxAmanaFood'],'sub' => 'Moy. '.$stats['avgAmanaFood']],
                ['label' => 'Plage distrib.',    'value' => $stats['minTaches'].'-'.$stats['maxTaches'],'sub' => 'Écart '.($stats['maxTaches']-$stats['minTaches'])],
                ['label' => 'Jours consécutifs', 'value' => $stats['persAvecHautConsec'],    'sub' => 'Pers. > 2 jours'],
            ] as $m)
                <div class="bg-white/[0.06] border border-white/[0.08] rounded-lg p-3">
                    <div class="text-[10px] font-bold uppercase tracking-[0.7px] text-white/40 mb-1.5">{{ $m['label'] }}</div>
                    <div class="font-heading text-xl font-bold text-white leading-none">{{ $m['value'] }}</div>
                    <div class="text-[11px] text-white/30 mt-1">{{ $m['sub'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="relative z-10">
            <div class="h-[5px] rounded-full bg-white/10 overflow-hidden mb-1.5">
                <div class="h-full rounded-full bg-sky-400 transition-all" style="width:{{ $stats['fairnessScore'] }}%"></div>
            </div>
            <div class="flex justify-between text-[10.5px] text-white/25">
                <span>0</span><span>25</span><span>50</span><span>75</span><span>100</span>
            </div>
        </div>
    </div>

    {{-- Table détail --}}
    <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden mb-5">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">📋</div>
            <span class="font-heading text-[14px] font-semibold text-ink">Détail par personne</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[13px]">
                <thead>
                    <tr>
                        <th class="text-left px-5 py-2.5 text-[10px] font-bold text-ink-muted uppercase tracking-[0.7px] bg-surface-2 border-b border-surface-3 whitespace-nowrap font-body">Personne</th>
                        @foreach([
                            ['Total',''],['Vendredis',''],['Samedis',''],
                            ['Entrée','text-[#2563eb]'],['Mektaba','text-[#059669]'],['Salle','text-[#d97706]'],
                            ['Amana Food','text-[#e11d48]'],['Cours','text-[#7c3aed]'],
                            ['Consécutifs',''],['Absences',''],
                        ] as [$col, $cls])
                            <th class="text-right px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.7px] bg-surface-2 border-b border-surface-3 whitespace-nowrap font-body {{ $cls ?: 'text-ink-muted' }}">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($stats['personnes'] as $nom)
                        @php
                            $total  = $stats['taskCounts'][$nom] ?? 0;
                            $dc     = $stats['dayCounts'][$nom] ?? ['vendredis'=>0,'samedis'=>0];
                            $tp     = $stats['tasksByPerson'][$nom] ?? [];
                            $consec = $stats['consecutiveDays'][$nom] ?? 0;
                            $abs    = $stats['absenceDays'][$nom] ?? 0;
                        @endphp
                        <tr class="border-b border-surface-3 last:border-0 hover:bg-surface-2 transition-colors">
                            <td class="px-5 py-2.5 font-semibold text-ink whitespace-nowrap">{{ $nom }}</td>
                            <td class="px-4 py-2.5 text-right font-bold text-ink">{{ $total }}</td>
                            <td class="px-4 py-2.5 text-right text-ink-muted">{{ $dc['vendredis'] }}</td>
                            <td class="px-4 py-2.5 text-right text-ink-muted">{{ $dc['samedis'] }}</td>
                            <td class="px-4 py-2.5 text-right text-ink-muted">{{ $tp['entree'] ?? 0 }}</td>
                            <td class="px-4 py-2.5 text-right text-ink-muted">{{ $tp['mektaba'] ?? 0 }}</td>
                            <td class="px-4 py-2.5 text-right text-ink-muted">{{ $tp['salle'] ?? 0 }}</td>
                            <td class="px-4 py-2.5 text-right text-ink-muted">{{ $tp['amana_food'] ?? 0 }}</td>
                            <td class="px-4 py-2.5 text-right text-ink-muted">{{ $tp['cours'] ?? 0 }}</td>
                            <td class="px-4 py-2.5 text-right {{ $consec > 2 ? 'text-rose-600 font-bold' : 'text-ink-muted' }}">{{ $consec }}</td>
                            <td class="px-4 py-2.5 text-right {{ $abs > 0 ? 'text-amber-600' : 'text-ink-faint' }}">{{ $abs ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Explications --}}
    <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-violet-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">📖</div>
            <span class="font-heading text-[14px] font-semibold text-ink">Comment lire ces statistiques</span>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach([
                    ['📊 Total assignations', 'Nombre total de fois qu\'une personne a été assignée à une tâche. C\'est l\'indicateur brut de charge de travail.', 'Toutes les personnes devraient avoir un total proche de la moyenne.'],
                    ['➗ Moyenne / personne', 'Nombre moyen d\'assignations par personne. Sert de référence pour évaluer si une personne est sur- ou sous-chargée.', 'Toute valeur très éloignée de '.$stats['moyenneTaches'].' signale un déséquilibre.'],
                    ['📉 Écart-type', 'Mesure la dispersion des totaux autour de la moyenne. Plus il est faible, plus la distribution est homogène.', 'Valeur actuelle : '.$stats['ecartType'].'. En dessous de 2 = très bonne équité ; au-dessus de 4 = déséquilibre notable.'],
                    ['📐 Coefficient de variation', 'L\'écart-type en % de la moyenne. Permet de comparer l\'équité quelle que soit la durée. En dessous de 15% = équité satisfaisante.', 'Valeur actuelle : '.$stats['coefficientVariation'].'%. Intervient directement dans le score d\'équité (−30 pts max).'],
                    ['⚖️ Déséquilibre Ven./Sam.', 'Différence moyenne par personne entre vendredis et samedis travaillés. Proche de 0 = bonne alternance.', 'Valeur actuelle : '.$stats['desequilibreMoyen'].'.'],
                    ['🔢 Plage de distribution', 'Fourchette entre la personne la moins et la plus assignée. Un écart faible indique une bonne équité globale.', 'Actuel : '.$stats['minTaches'].' à '.$stats['maxTaches'].' (écart de '.($stats['maxTaches']-$stats['minTaches']).').'],
                    ['🥪 Distribution Amana Food', 'Répartition spécifique suivant une rotation stricte par cycle global. Min / Max / Moyenne indiquent à quel point le cycle est respecté.', 'Actuel : min '.$stats['minAmanaFood'].', max '.$stats['maxAmanaFood'].', moy. '.$stats['avgAmanaFood'].'. Un écart min–max ≤ 1 confirme que le cycle tourne correctement.'],
                    ['📅 Jours consécutifs', 'Nombre maximum de créneaux consécutifs travaillés d\'affilée. Une valeur supérieure à 2 est signalée (fatigue potentielle).', 'Le score d\'équité est pénalisé de 5 pts par personne dans cette situation (−20 pts max).'],
                    ['📈 Taux d\'utilisation', 'Pourcentage de slots effectivement assignés parmi tous les slots disponibles. Un taux bas signale des tâches non assignées.', 'Actuel : '.$stats['tauxUtilisation'].'%. En dessous de 85%, vérifier les restrictions et absences.'],
                    ['🏆 Score d\'équité', 'Score composite sur 100 calculé à partir du coefficient de variation (−30 pts), jours consécutifs (−20 pts) et déséquilibre Ven./Sam. (−20 pts).', '90–100 = excellent · 70–89 = bon · <70 = à améliorer.'],
                ] as [$term, $def, $ex])
                    <div class="bg-surface-2 border border-surface-border rounded-lg p-4">
                        <div class="font-heading text-[13px] font-semibold text-ink mb-1.5">{{ $term }}</div>
                        <p class="text-[12.5px] text-ink-muted leading-relaxed mb-1.5">{{ $def }}</p>
                        <p class="text-[11.5px] text-accent italic">{{ $ex }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

@endif
@endsection
