{{-- resources/views/planning/partials/_week-block.blade.php --}}
{{--
Variables attendues :
$semaineCle, $creneauxSemaine, $bannièresParSemaine
(incluse une fois par semaine depuis la boucle dans index.blade.php)
--}}
@php
    $first = $creneauxSemaine->first();
    $last = $creneauxSemaine->last();
    $weekYear = $first->date->year;
    $weekMonth = $first->date->month;
    $weekIds = $creneauxSemaine->pluck('id')->join(',');
    $weekMonday = $first->date->copy()->subDays($first->date->isoWeekday() - 1)->startOfDay();
    $weekSunday = $weekMonday->copy()->addDays(6)->endOfDay();
    $existingDates = $creneauxSemaine->pluck('date')->map(fn($d) => $d->toDateString())->toJson();

    $bannièresSemaine = $bannièresParSemaine[$semaineCle] ?? [];

    $nbTachesActives = $creneauxSemaine->first()?->taches->count() ?? 5;
    $evtToutBloque = collect($bannièresSemaine)->first(
        fn($b) => !$b['informatif']
        && $b['evenement']->tachesBloquees->count() >= $nbTachesActives
    );
@endphp

<div class="week-block" data-year="{{ $weekYear }}" data-month="{{ $weekMonth }}">
    <div class="week-header">
        <div class="week-label">
            📅 <span class="week-num">S{{ $first->semaine }}</span>
            {{ $first->date->locale('fr')->isoFormat('D MMMM') }} —
            {{ $last->date->locale('fr')->isoFormat('D MMMM YYYY') }}
            @if($evtToutBloque)
                <span style="
                            background:rgba(225,29,72,0.25);
                            border:1px solid rgba(225,29,72,0.5);
                            color:#fda4af;
                            padding:2px 10px;
                            border-radius:20px;
                            font-size:11px;
                            font-weight:600;
                            margin-left:6px;">
                    🚫 {{ $evtToutBloque['evenement']->nom }}
                </span>
            @endif
        </div>
        <div class="week-actions">
            <span class="week-dates">{{ $creneauxSemaine->count() }} créneaux</span>
            @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                <button class="btn-add-creneau" onclick="openAddCreneauModal(
                            '{{ $weekMonday->toDateString() }}',
                            '{{ $weekSunday->toDateString() }}',
                            {{ $existingDates }}
                        )">➕ Créneau</button>
                <button class="btn-delete-week" onclick="deleteWeek([{{ $weekIds }}], this)">
                    🗑️ Supprimer la semaine
                </button>
            @endif
        </div>
    </div>

    <div class="week-body">

        {{-- Bannières événements --}}
        @foreach($bannièresSemaine as $bannière)
            @php
                $evt = $bannière['evenement'];
                $debutStr = $bannière['debut_semaine']->locale('fr')->isoFormat('D MMM');
                $finStr = $bannière['fin_semaine']->locale('fr')->isoFormat('D MMM');
                $mêmeJour = $bannière['debut_semaine']->isSameDay($bannière['fin_semaine']);
                $dateStr = $mêmeJour ? $debutStr : "{$debutStr} – {$finStr}";
            @endphp

            @if($bannière['informatif'])
                <div class="event-banner-info">
                    <span class="event-banner-icon">📅</span>
                    <span class="event-banner-name">{{ $evt->nom }}</span>
                    <span class="event-banner-dates">— {{ $dateStr }}</span>
                </div>
            @else
                <div class="event-banner-blocking">
                    <span class="event-banner-icon">🚫</span>
                    <span class="event-banner-name">{{ $evt->nom }}</span>
                    <span class="event-banner-dates">— {{ $dateStr }}</span>
                    <div class="event-banner-taches">
                        @foreach($evt->tachesBloquees as $tb)
                            @php
                                $tbColors = [
                                    'entree' => ['bg' => '#eff6ff', 'color' => '#2563eb'],
                                    'mektaba' => ['bg' => '#ecfdf5', 'color' => '#059669'],
                                    'salle' => ['bg' => '#fffbeb', 'color' => '#d97706'],
                                    'amana_food' => ['bg' => '#fff1f2', 'color' => '#e11d48'],
                                    'cours' => ['bg' => '#f5f3ff', 'color' => '#7c3aed'],
                                ];
                                $s = $tbColors[$tb->code] ?? ['bg' => 'var(--surface-3)', 'color' => 'var(--ink)'];
                            @endphp
                            <span style="
                                                            display:inline-flex;align-items:center;
                                                            padding:2px 8px;border-radius:20px;
                                                            font-size:11px;font-weight:600;
                                                            background:{{ $s['bg'] }};color:{{ $s['color'] }};">
                                {{ $tb->libelle }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        {{-- Table des créneaux --}}
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Jour</th>
                        <th style="color:#2563eb;">🚪 Entrée</th>
                        <th style="color:#059669;">📚 Mektaba</th>
                        <th style="color:#d97706;">🏛️ Salle</th>
                        <th style="color:#e11d48;">🥪 Amana Food</th>
                        <th style="color:#7c3aed;">🎓 Cours</th>
                        <th>Événements</th>
                        <th style="width:36px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($creneauxSemaine as $creneau)
                        @php
                            $tachesMap = $creneau->taches->keyBy(fn($t) => $t->tache?->code);
                            $tachesBloqueesCodes = $creneau->tachesBloqueesCodes();

                            $nomEvenementsBloquants = $creneau->evenements
                                ->filter(fn($e) => $e->tachesBloquees->isNotEmpty())
                                ->pluck('nom')
                                ->implode(', ');

                            $nbTaches = $creneau->taches->count();
                            $toutBloque = $tachesBloqueesCodes->count() >= $nbTaches
                                && $tachesBloqueesCodes->isNotEmpty();

                            $evtStr = $creneau->evenements->pluck('nom')->implode(', ');
                        @endphp

                        <tr class="{{ $toutBloque ? 'day-row-blocked' : '' }}" id="row-creneau-{{ $creneau->id }}">

                            {{-- Jour + date --}}
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <strong style="color:var(--ink);font-family:var(--font-heading);font-size:13px;">
                                        {{ $creneau->jour }}
                                    </strong>
                                    <span style="color:var(--ink-muted);font-size:12px;">
                                        {{ $creneau->date->locale('fr')->isoFormat('D MMM YYYY') }}
                                    </span>
                                    @if($toutBloque)
                                        <span class="badge badge-danger badge-dot" style="font-size:10px;">Bloqué</span>
                                    @elseif($tachesBloqueesCodes->isNotEmpty())
                                        <span class="badge badge-warning badge-dot" style="font-size:10px;">Partiel</span>
                                    @endif
                                </div>
                            </td>

                            {{-- 5 tâches --}}
                            @foreach(['entree', 'mektaba', 'salle', 'amana_food', 'cours'] as $code)
                                @php
                                    $ct = $tachesMap->get($code);
                                    $tacheId = $ct?->id_tache;
                                    $personne = $ct?->personne;
                                    $estBloquee = $tachesBloqueesCodes->contains($code);
                                @endphp
                                <td class="task-cell {{ $estBloquee ? 'task-cell-blocked' : '' }}"
                                    id="cell-{{ $creneau->id }}-{{ $code }}" data-creneau-id="{{ $creneau->id }}"
                                    data-tache-id="{{ $tacheId }}" data-tache-code="{{ $code }}"
                                    data-tache-label="{{ ucfirst(str_replace('_', ' ', $code)) }}"
                                    data-jour="{{ $creneau->jour }}"
                                    data-date="{{ $creneau->date->locale('fr')->isoFormat('D MMM YYYY') }}">

                                    @if($estBloquee)
                                        <div class="task-cell-inner" style="cursor:default;">
                                            <span class="tache-blocked-label" title="{{ $nomEvenementsBloquants }}">
                                                🚫 {{ Str::limit($nomEvenementsBloquants, 20) }}
                                            </span>
                                        </div>
                                    @else
                                        @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                                            <div class="task-cell-inner" onclick="openEditModal(this.closest('td'))">
                                        @else
                                                <div class="task-cell-inner" style="cursor:default;">
                                            @endif
                                                @if($personne)
                                                    <span class="tache-chip {{ $code }}" id="chip-{{ $creneau->id }}-{{ $code }}">
                                                        {{ $personne->prenom }} {{ $personne->nom }}
                                                    </span>
                                                @else
                                                    <span class="tache-vide" id="chip-{{ $creneau->id }}-{{ $code }}">—</span>
                                                @endif
                                                @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                                                    <span class="edit-icon">✏️</span>
                                                @endif
                                            </div>
                                    @endif
                                </td>
                            @endforeach

                            {{-- Événements --}}
                            <td style="color:var(--ink-faint);">
                                @if($evtStr)
                                    <span class="event-tag {{ $toutBloque ? 'blocked' : '' }}">{{ $evtStr }}</span>
                                @else
                                    <span style="font-size:12px;">—</span>
                                @endif
                            </td>

                            {{-- Suppression créneau --}}
                            <td style="text-align:right;padding-right:12px;">
                                @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                                    <button class="btn-delete-day"
                                        onclick="deleteCreneau({{ $creneau->id }}, this)">🗑️</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>