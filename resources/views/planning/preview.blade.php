{{-- resources/views/planning/preview.blade.php --}}
@extends('layouts.app')

@section('title', 'Aperçu du planning — AMANA')

@push('styles')
    <style>
        .preview-banner {
            background: linear-gradient(90deg, #fffbeb 0%, #fef3c7 100%);
            border: 1.5px solid var(--amber-border);
            border-radius: var(--radius-lg);
            padding: 16px 22px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .preview-banner-icon {
            font-size: 26px;
            flex-shrink: 0;
        }

        .preview-banner-text {
            flex: 1;
            min-width: 200px;
        }

        .preview-banner-title {
            font-family: var(--font-heading);
            font-size: 15px;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 3px;
        }

        .preview-banner-sub {
            font-size: 12.5px;
            color: #b45309;
            line-height: 1.55;
        }

        .preview-banner-actions {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
            flex-wrap: wrap;
        }

        /* Week blocks — reuse planning.index visual style */
        .week-block {
            margin-bottom: 18px;
        }

        .week-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 18px;
            background: var(--app-sidebar-bg);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .week-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: var(--font-heading);
            font-weight: 600;
            font-size: 13px;
            color: white;
        }

        .week-num {
            background: rgba(255, 255, 255, 0.12);
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 600;
            font-family: var(--font-body);
        }

        .week-body {
            background: var(--surface);
            border: 1px solid var(--surface-border);
            border-top: none;
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        /* .tache-chip (toutes variantes) et .tache-vide sont définis
                   globalement dans app.css — pas de redéfinition ici. */

        .tache-blocked {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: #fff3cd;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .event-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--amber-bg);
            color: var(--amber);
            border: 1px solid var(--amber-border);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .preview-watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 96px;
            font-weight: 900;
            color: rgba(217, 119, 6, 0.05);
            pointer-events: none;
            z-index: 0;
            white-space: nowrap;
            user-select: none;
            font-family: var(--font-heading);
        }
    </style>
@endpush

@section('content')

    {{-- Subtle watermark --}}
    <div class="preview-watermark">APERÇU</div>

    {{-- Banner --}}
    <div class="preview-banner">
        <div class="preview-banner-icon">👁️</div>
        <div class="preview-banner-text">
            <div class="preview-banner-title">Aperçu — aucune donnée enregistrée</div>
            <div class="preview-banner-sub">
                Ce planning est une simulation. Rien n'a été modifié en base.
                Vérifiez les assignations puis confirmez si tout vous convient.
                <br>
                <strong>{{ count($propositions['creneaux']) }} créneaux</strong>
                proposés · durée du calcul : {{ $propositions['duree_ms'] }}ms
                · {{ $propositions['non_assignes'] }} non assigné(s)
            </div>
        </div>
        <div class="preview-banner-actions">
            {{-- Confirm: re-submit the real generate form with confirmed=1 --}}
            <form action="{{ route('planning.generate') }}" method="POST">
                @csrf
                <input type="hidden" name="date_debut" value="{{ $dateDebut }}">
                <input type="hidden" name="semaines" value="{{ $semaines }}">
                <input type="hidden" name="confirmed" value="1">
                <button type="submit" class="btn btn-primary">
                    ✨ Confirmer et générer
                </button>
            </form>
            <a href="{{ route('planning.generate.form') }}" class="btn btn-secondary">← Modifier</a>
        </div>
    </div>

    {{-- Planning grid --}}
    @php
        // Group creneaux by ISO week for display
        $parSemaine = collect($propositions['creneaux'])->groupBy(fn($c) => $c['semaine'] . '-' . \Carbon\Carbon::parse($c['date'])->year);
    @endphp

    @foreach($parSemaine as $semaineKey => $jours)
        @php
            $firstJour = $jours->first();
            $lastJour = $jours->last();
            $firstDate = \Carbon\Carbon::parse($firstJour['date']);
            $lastDate = \Carbon\Carbon::parse($lastJour['date']);
        @endphp

        <div class="week-block">
            <div class="week-header">
                <div class="week-label">
                    📅
                    <span class="week-num">S{{ $firstJour['semaine'] }}</span>
                    {{ $firstDate->locale('fr')->isoFormat('D MMMM') }} —
                    {{ $lastDate->locale('fr')->isoFormat('D MMMM YYYY') }}
                </div>
                <span style="font-size:12px;color:rgba(255,255,255,0.4);">{{ $jours->count() }} jours</span>
            </div>

            <div class="week-body">
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
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($jours as $jour)
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <strong style="color:var(--ink);font-family:var(--font-heading);font-size:13px;">
                                                {{ $jour['jour'] }}
                                            </strong>
                                            <span style="color:var(--ink-muted);font-size:12px;">
                                                {{ $jour['date_label'] }}
                                            </span>
                                        </div>
                                    </td>

                                    @foreach(['entree', 'mektaba', 'salle', 'amana_food', 'cours'] as $code)
                                        @php $td = $jour['taches'][$code] ?? null; @endphp
                                        <td>
                                            @if(!$td)
                                                <span class="tache-vide">—</span>
                                            @elseif($td['bloquee'])
                                                <span class="tache-blocked">🚫 Bloqué</span>
                                            @elseif($td['nom_complet'])
                                                <span class="tache-chip {{ $code }}">{{ $td['nom_complet'] }}</span>
                                            @else
                                                <span class="tache-vide">—</span>
                                            @endif
                                        </td>
                                    @endforeach

                                    <td>
                                        @if($jour['evenements'])
                                            <span class="event-tag">🎉 {{ $jour['evenements'] }}</span>
                                        @else
                                            <span style="color:var(--ink-faint);font-size:12px;">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Bottom confirm strip --}}
    <div style="
                        background:var(--surface);
                        border:1.5px solid var(--amber-border);
                        border-radius:var(--radius-lg);
                        padding:18px 22px;
                        margin-top:8px;
                        display:flex;
                        align-items:center;
                        justify-content:space-between;
                        gap:16px;
                        flex-wrap:wrap;
                        box-shadow:var(--shadow-sm);
                    ">
        <div>
            <div style="font-family:var(--font-heading);font-size:14px;font-weight:600;color:var(--ink);">
                Ce planning vous convient ?
            </div>
            <div style="font-size:12.5px;color:var(--ink-muted);margin-top:3px;">
                Cliquez sur "Confirmer et générer" pour l'enregistrer définitivement.
            </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <form action="{{ route('planning.generate') }}" method="POST">
                @csrf
                <input type="hidden" name="date_debut" value="{{ $dateDebut }}">
                <input type="hidden" name="semaines" value="{{ $semaines }}">
                <input type="hidden" name="confirmed" value="1">
                <button type="submit" class="btn btn-primary btn-lg">✨ Confirmer et générer</button>
            </form>
            <a href="{{ route('planning.generate.form') }}" class="btn btn-secondary btn-lg">← Modifier</a>
        </div>
    </div>

@endsection