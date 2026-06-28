{{-- resources/views/planning/partials/_week-block.blade.php --}}
{{-- Variables : $semaineCle, $creneauxSemaine, $bannièresParSemaine --}}
@php
    $first          = $creneauxSemaine->first();
    $last           = $creneauxSemaine->last();
    $weekYear       = $first->date->year;
    $weekMonth      = $first->date->month;
    $weekIds        = $creneauxSemaine->pluck('id')->join(',');
    $weekMonday     = $first->date->clone()->subDays($first->date->isoWeekday() - 1)->startOfDay();
    $weekSunday     = $weekMonday->clone()->addDays(6)->endOfDay();
    $existingDates  = $creneauxSemaine->pluck('date')->map(fn($d) => $d->toDateString())->toJson();
    $bannièresSemaine  = $bannièresParSemaine[$semaineCle] ?? [];
    $nbTachesActives   = $creneauxSemaine->first()?->taches->count() ?? 5;
    $evtToutBloque     = collect($bannièresSemaine)->first(
        fn($b) => !$b['informatif'] && $b['evenement']->tachesBloquees->count() >= $nbTachesActives
    );

    $tachesMeta = [
        'entree'     => ['label' => '🚪 Entrée',     'color' => 'text-[#2563eb]'],
        'mektaba'    => ['label' => '📚 Mektaba',    'color' => 'text-[#059669]'],
        'salle'      => ['label' => '🏛️ Salle',      'color' => 'text-[#d97706]'],
        'amana_food' => ['label' => '🥪 Amana Food', 'color' => 'text-[#e11d48]'],
        'cours'      => ['label' => '🎓 Cours',      'color' => 'text-[#7c3aed]'],
    ];
@endphp

<div class="week-block mb-4 bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden"
     data-year="{{ $weekYear }}" data-month="{{ $weekMonth }}">

    {{-- Header semaine --}}
    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-surface-3 bg-surface-2">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="font-heading text-[13px] font-semibold text-ink flex items-center gap-1.5">
                📅
                <span class="text-accent font-bold">S{{ $first->semaine }}</span>
                {{ $first->date->locale('fr')->isoFormat('D MMMM') }}
                —
                {{ $last->date->locale('fr')->isoFormat('D MMMM YYYY') }}
            </span>
            @if($evtToutBloque)
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold
                             bg-rose-500/20 border border-rose-500/40 text-rose-300">
                    🚫 {{ $evtToutBloque['evenement']->nom }}
                </span>
            @endif
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-[11.5px] text-ink-muted">{{ $creneauxSemaine->count() }} créneau{{ $creneauxSemaine->count() > 1 ? 'x' : '' }}</span>
            @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                <button onclick="openAddCreneauModal('{{ $weekMonday->toDateString() }}','{{ $weekSunday->toDateString() }}',{{ $existingDates }})"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11.5px] font-semibold rounded-lg cursor-pointer transition-colors min-h-[44px]
                               bg-sky-500/20 border border-sky-500/50 text-sky-700 hover:bg-sky-500/30">
                    ➕ Créneau
                </button>
                <button onclick="deleteWeek([{{ $weekIds }}], this)"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11.5px] font-semibold rounded-lg cursor-pointer transition-colors min-h-[44px]
                               bg-rose-500/15 border border-rose-500/40 text-rose-600 hover:bg-rose-500/25">
                    🗑️ Semaine
                </button>
            @endif
        </div>
    </div>

    {{-- Bannières événements --}}
    @foreach($bannièresSemaine as $bannière)
        @php
            $evt      = $bannière['evenement'];
            $debutStr = $bannière['debut_semaine']->locale('fr')->isoFormat('D MMM');
            $finStr   = $bannière['fin_semaine']->locale('fr')->isoFormat('D MMM');
            $mêmeJour = $bannière['debut_semaine']->isSameDay($bannière['fin_semaine']);
            $dateStr  = $mêmeJour ? $debutStr : "$debutStr – $finStr";
        @endphp
        @if($bannière['informatif'])
            <div class="flex items-center gap-2 px-4 py-2 bg-sky-50 border-b border-sky-100 text-[12.5px] text-sky-800">
                <span>📅</span>
                <span class="font-bold">{{ $evt->nom }}</span>
                <span class="opacity-70">— {{ $dateStr }}</span>
            </div>
        @else
            <div class="flex flex-wrap items-center gap-2 px-4 py-2 bg-rose-50 border-b border-rose-100 text-[12.5px] text-rose-800">
                <span>🚫</span>
                <span class="font-bold">{{ $evt->nom }}</span>
                <span class="opacity-70">— {{ $dateStr }}</span>
                <div class="flex flex-wrap gap-1 ml-1">
                    @foreach($evt->tachesBloquees as $tb)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold chip-{{ $tb->code }}">
                            {{ $tb->libelle }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach

    {{-- Table desktop (≥ md) --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full border-collapse text-[13px]" style="min-width:680px;">
            <thead>
                <tr>
                    <th class="text-left px-4 py-2.5 text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] bg-surface-2 border-b border-surface-3 whitespace-nowrap font-body w-36">
                        Jour
                    </th>
                    @foreach($tachesMeta as $code => $meta)
                        <th class="text-left px-3 py-2.5 text-[11px] font-bold bg-surface-2 border-b border-surface-3 whitespace-nowrap font-body {{ $meta['color'] }}">
                            {{ $meta['label'] }}
                        </th>
                    @endforeach
                    <th class="text-left px-3 py-2.5 text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] bg-surface-2 border-b border-surface-3 font-body">
                        Événements
                    </th>
                    <th class="w-9 bg-surface-2 border-b border-surface-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($creneauxSemaine as $creneau)
                    @php
                        $tachesMap            = $creneau->taches->keyBy(fn($t) => $t->tache?->code);
                        $tachesBloqueesCodes  = $creneau->tachesBloqueesCodes();
                        $nomEvtBloquants      = $creneau->evenements->filter(fn($e) => $e->tachesBloquees->isNotEmpty())->pluck('nom')->implode(', ');
                        $nbTaches             = $creneau->taches->count();
                        $toutBloque           = $tachesBloqueesCodes->count() >= $nbTaches && $tachesBloqueesCodes->isNotEmpty();
                        $evtStr               = $creneau->evenements->pluck('nom')->implode(', ');
                    @endphp
                    <tr class="border-b border-surface-3 last:border-0 group {{ $toutBloque ? 'bg-orange-50' : 'hover:bg-surface-2' }} transition-colors"
                        id="row-creneau-{{ $creneau->id }}">

                        {{-- Jour --}}
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <strong class="font-heading text-[13px] text-ink">{{ $creneau->jour }}</strong>
                                <span class="text-ink-muted text-[11.5px]">{{ $creneau->date->locale('fr')->isoFormat('D MMM YYYY') }}</span>
                                @if($toutBloque)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">● Bloqué</span>
                                @elseif($tachesBloqueesCodes->isNotEmpty())
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">● Partiel</span>
                                @endif
                            </div>
                        </td>

                        {{-- 5 tâches --}}
                        @foreach(['entree','mektaba','salle','amana_food','cours'] as $code)
                            @php
                                $ct         = $tachesMap->get($code);
                                $tacheId    = $ct?->id_tache;
                                $personne   = $ct?->personne;
                                $estBloquee = $tachesBloqueesCodes->contains($code);
                            @endphp
                            <td class="px-2 py-2 relative {{ $estBloquee ? 'bg-orange-50' : '' }}"
                                id="cell-{{ $creneau->id }}-{{ $code }}"
                                data-creneau-id="{{ $creneau->id }}"
                                data-tache-id="{{ $tacheId }}"
                                data-tache-code="{{ $code }}"
                                data-tache-label="{{ ucfirst(str_replace('_', ' ', $code)) }}"
                                data-jour="{{ $creneau->jour }}"
                                data-date="{{ $creneau->date->locale('fr')->isoFormat('D MMM YYYY') }}">

                                @if($estBloquee)
                                    <div class="flex items-center gap-1 px-2 py-1 rounded-md cursor-default">
                                        <span class="text-orange-500 text-xs font-semibold" title="{{ $nomEvtBloquants }}">
                                            🚫 {{ Str::limit($nomEvtBloquants, 18) }}
                                        </span>
                                    </div>
                                @else
                                    @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                                        <div class="task-cell-inner flex items-center gap-1.5 px-2 py-1 rounded-md cursor-pointer hover:bg-surface-3 transition-colors group/cell"
                                             onclick="openEditModal(this.closest('td'))">
                                    @else
                                        <div class="flex items-center gap-1.5 px-2 py-1 rounded-md cursor-default">
                                    @endif
                                            @if($personne)
                                                <span class="chip-{{ $code }} inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold"
                                                      id="chip-{{ $creneau->id }}-{{ $code }}">
                                                    {{ $personne->prenom }} {{ $personne->nom }}
                                                </span>
                                            @else
                                                <span class="tache-vide text-ink-faint italic text-xs"
                                                      id="chip-{{ $creneau->id }}-{{ $code }}">—</span>
                                            @endif
                                            @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                                                <span class="edit-icon opacity-0 group-hover/cell:opacity-100 transition-opacity text-[11px] text-ink-faint flex-shrink-0">✏️</span>
                                            @endif
                                        </div>
                                @endif
                            </td>
                        @endforeach

                        {{-- Événements --}}
                        <td class="px-3 py-2.5">
                            @if($evtStr)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium
                                             {{ $toutBloque ? 'bg-rose-100 text-rose-700' : 'bg-surface-3 text-ink-muted' }}">
                                    {{ $evtStr }}
                                </span>
                            @else
                                <span class="text-ink-faint text-xs">—</span>
                            @endif
                        </td>

                        {{-- Supprimer créneau --}}
                        <td class="pr-3 text-right">
                            @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                                <button class="btn-delete-day opacity-0 group-hover:opacity-100 transition-opacity
                                               w-7 h-7 rounded-md bg-transparent border border-transparent hover:bg-rose-50 hover:border-rose-200
                                               text-sm cursor-pointer flex items-center justify-center min-h-[44px] min-w-[44px]"
                                        onclick="deleteCreneau({{ $creneau->id }}, this)">🗑️</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Cartes mobile (< md) --}}
    <div class="md:hidden divide-y divide-surface-3">
        @foreach($creneauxSemaine as $creneau)
            @php
                $tachesMap           = $creneau->taches->keyBy(fn($t) => $t->tache?->code);
                $tachesBloqueesCodes = $creneau->tachesBloqueesCodes();
                $nomEvtBloquants     = $creneau->evenements->filter(fn($e) => $e->tachesBloquees->isNotEmpty())->pluck('nom')->implode(', ');
                $toutBloque          = $tachesBloqueesCodes->count() >= $creneau->taches->count() && $tachesBloqueesCodes->isNotEmpty();
                $evtStr              = $creneau->evenements->pluck('nom')->implode(', ');
            @endphp
            <div class="px-4 py-3 {{ $toutBloque ? 'bg-orange-50' : '' }}" id="row-creneau-{{ $creneau->id }}">

                {{-- Jour + badges --}}
                <div class="flex items-center justify-between mb-2.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <strong class="font-heading text-[13.5px] text-ink">{{ $creneau->jour }}</strong>
                        <span class="text-ink-muted text-[12px]">{{ $creneau->date->locale('fr')->isoFormat('D MMM YYYY') }}</span>
                        @if($toutBloque)
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">● Bloqué</span>
                        @elseif($tachesBloqueesCodes->isNotEmpty())
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">● Partiel</span>
                        @endif
                    </div>
                    @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                        <button onclick="deleteCreneau({{ $creneau->id }}, this)"
                                class="w-9 h-9 rounded-md border border-rose-200 bg-rose-50 hover:bg-rose-100 text-sm cursor-pointer flex items-center justify-center min-h-[44px] min-w-[44px]">
                            🗑️
                        </button>
                    @endif
                </div>

                {{-- Tâches --}}
                <div class="grid grid-cols-2 gap-2">
                    @foreach(['entree','mektaba','salle','amana_food','cours'] as $code)
                        @php
                            $ct         = $tachesMap->get($code);
                            $personne   = $ct?->personne;
                            $tacheId    = $ct?->id_tache;
                            $estBloquee = $tachesBloqueesCodes->contains($code);
                        @endphp
                        <div class="{{ $estBloquee ? 'bg-orange-50' : 'bg-surface-2' }} rounded-lg p-2.5
                                    {{ (auth()->user()->isAdmin() || auth()->user()->isGestionnaire()) && !$estBloquee ? 'cursor-pointer hover:bg-surface-3 active:bg-surface-border' : '' }} transition-colors"
                             id="cell-{{ $creneau->id }}-{{ $code }}"
                             data-creneau-id="{{ $creneau->id }}"
                             data-tache-id="{{ $tacheId }}"
                             data-tache-code="{{ $code }}"
                             data-tache-label="{{ ucfirst(str_replace('_', ' ', $code)) }}"
                             data-jour="{{ $creneau->jour }}"
                             data-date="{{ $creneau->date->locale('fr')->isoFormat('D MMM YYYY') }}"
                             @if((auth()->user()->isAdmin() || auth()->user()->isGestionnaire()) && !$estBloquee)
                                 onclick="openEditModal(this)"
                             @endif>
                            <div class="text-[10px] font-bold text-ink-muted mb-1">{{ $tachesMeta[$code]['label'] }}</div>
                            @if($estBloquee)
                                <span class="text-orange-500 text-xs font-semibold">🚫 {{ Str::limit($nomEvtBloquants, 14) }}</span>
                            @elseif($personne)
                                <span class="chip-{{ $code }} inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold"
                                      id="chip-{{ $creneau->id }}-{{ $code }}">
                                    {{ $personne->prenom }} {{ Str::limit($personne->nom, 8) }}
                                </span>
                            @else
                                <span class="text-ink-faint italic text-xs" id="chip-{{ $creneau->id }}-{{ $code }}">—</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if($evtStr)
                    <div class="mt-2 text-[11.5px] text-ink-muted">📅 {{ $evtStr }}</div>
                @endif
            </div>
        @endforeach
    </div>

</div>
