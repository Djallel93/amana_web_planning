<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 9px;
        color: #0f1117;
        background: #ffffff;
    }
    .pdf-header {
        background: #0f1117;
        color: white;
        padding: 14px 20px;
        display: table;
        width: 100%;
        margin-bottom: 16px;
    }
    .pdf-header-left  { display: table-cell; vertical-align: middle; }
    .pdf-header-right { display: table-cell; vertical-align: middle; text-align: right; }
    .pdf-logo { font-size: 18px; font-weight: bold; color: white; }
    .pdf-sub  { font-size: 9px; color: rgba(255,255,255,0.5); margin-top: 2px; }
    .pdf-range { font-size: 10px; color: rgba(255,255,255,0.75); }
    .pdf-generated { font-size: 8px; color: rgba(255,255,255,0.4); margin-top: 3px; }

    .week-section { margin-bottom: 14px; page-break-inside: avoid; }
    .week-title {
        background: #1e2130;
        color: white;
        padding: 6px 12px;
        font-size: 9.5px;
        font-weight: bold;
        border-radius: 4px 4px 0 0;
        display: table;
        width: 100%;
    }
    .week-title-left  { display: table-cell; }
    .week-title-right { display: table-cell; text-align: right; color: rgba(255,255,255,0.5); font-weight: normal; }

    table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #e5e7eb;
        border-radius: 0 0 4px 4px;
    }
    thead th {
        background: #f9fafb;
        padding: 6px 8px;
        text-align: left;
        font-size: 8px;
        font-weight: bold;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        border-bottom: 1px solid #e5e7eb;
    }
    tbody td {
        padding: 7px 8px;
        border-bottom: 1px solid #f3f4f6;
        font-size: 9px;
        vertical-align: middle;
    }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:nth-child(even) { background: #fafafa; }

    .day-label { font-weight: bold; font-size: 9.5px; color: #0f1117; }
    .day-date  { font-size: 8px; color: #9ca3af; margin-top: 1px; }

    .task-entree     { color: #2563eb; font-weight: 600; }
    .task-mektaba    { color: #059669; font-weight: 600; }
    .task-salle      { color: #d97706; font-weight: 600; }
    .task-amana_food { color: #e11d48; font-weight: 600; }
    .task-empty      { color: #d1d5db; font-style: italic; }

    .evt-tag {
        display: inline-block;
        background: #fffbeb;
        color: #92400e;
        border: 1px solid #fde68a;
        padding: 1px 6px;
        border-radius: 10px;
        font-size: 8px;
        font-weight: 600;
    }
    .evt-blocked { background: #fff1f2; color: #9f1239; border-color: #fecdd3; }

    .blocked-row { opacity: 0.6; }

    .pdf-footer {
        margin-top: 20px;
        padding-top: 10px;
        border-top: 1px solid #e5e7eb;
        display: table;
        width: 100%;
    }
    .pdf-footer-left  { display: table-cell; font-size: 8px; color: #9ca3af; }
    .pdf-footer-right { display: table-cell; text-align: right; font-size: 8px; color: #9ca3af; }

    .no-data {
        text-align: center;
        padding: 40px;
        color: #9ca3af;
        font-size: 11px;
    }
</style>
</head>
<body>

<div class="pdf-header">
    <div class="pdf-header-left">
        <div class="pdf-logo">📅 AMANA Planning</div>
        <div class="pdf-sub">Planning des permanences</div>
    </div>
    <div class="pdf-header-right">
        <div class="pdf-range">
            Du {{ \Carbon\Carbon::parse($dateDebut)->locale('fr')->isoFormat('D MMMM YYYY') }}
            au {{ \Carbon\Carbon::parse($dateFin)->locale('fr')->isoFormat('D MMMM YYYY') }}
        </div>
        <div class="pdf-generated">Généré le {{ now()->locale('fr')->isoFormat('D MMMM YYYY à HH:mm') }}</div>
    </div>
</div>

@if($creneaux->isEmpty())
    <div class="no-data">Aucun créneau dans cette plage de dates.</div>
@else
    @foreach($creneaux as $semaineCle => $creneauxSemaine)
    @php
        $first = $creneauxSemaine->first();
        $last  = $creneauxSemaine->last();
    @endphp
    <div class="week-section">
        <div class="week-title">
            <div class="week-title-left">
                Semaine {{ $first->semaine }} —
                {{ $first->date->locale('fr')->isoFormat('D MMMM') }} au {{ $last->date->locale('fr')->isoFormat('D MMMM YYYY') }}
            </div>
            <div class="week-title-right">{{ $creneauxSemaine->count() }} créneaux</div>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width:130px;">Jour</th>
                    <th>Entrée</th>
                    <th>Mektaba</th>
                    <th>Salle</th>
                    <th>Amana Food</th>
                    <th>Événements</th>
                </tr>
            </thead>
            <tbody>
                @foreach($creneauxSemaine as $creneau)
                @php
                    $tachesMap = $creneau->taches->keyBy(fn($t) => $t->tache?->code);
                    $isBlocked = $creneau->evenements->contains(fn($e) => $e->bloque_planning);
                    $evtStr    = $creneau->evenements->pluck('nom')->implode(', ');
                @endphp
                <tr class="{{ $isBlocked ? 'blocked-row' : '' }}">
                    <td>
                        <div class="day-label">{{ $creneau->jour }}</div>
                        <div class="day-date">{{ $creneau->date->locale('fr')->isoFormat('D MMM YYYY') }}</div>
                    </td>
                    @foreach(['entree','mektaba','salle','amana_food'] as $code)
                    <td>
                        @if($tachesMap->has($code) && $tachesMap[$code]->personne)
                            <span class="task-{{ $code }}">
                                {{ $tachesMap[$code]->personne->prenom }} {{ $tachesMap[$code]->personne->nom }}
                            </span>
                        @else
                            <span class="task-empty">—</span>
                        @endif
                    </td>
                    @endforeach
                    <td>
                        @if($evtStr)
                            <span class="evt-tag {{ $isBlocked ? 'evt-blocked' : '' }}">{{ $evtStr }}</span>
                        @else
                            <span class="task-empty">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endforeach
@endif

<div class="pdf-footer">
    <div class="pdf-footer-left">AMANA Planning — Document confidentiel</div>
    <div class="pdf-footer-right">
        Total : {{ $creneaux->flatten()->count() }} créneaux
    </div>
</div>

</body>
</html>
