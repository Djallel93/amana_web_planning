{{-- resources/views/planning/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Planning — AMANA')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">📅 Planning des permanences</div>
        <div class="page-subtitle">Vendredis et samedis — rotation automatique des tâches</div>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="{{ route('planning.generate.form') }}" class="btn btn-primary">
            ✨ Générer le planning
        </a>
        <a href="{{ route('planning.statistics') }}" class="btn btn-secondary">
            📊 Statistiques
        </a>
    </div>
</div>

@if($creneaux->isEmpty())
    <div class="card" style="text-align:center; padding: 60px;">
        <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
        <div style="font-size: 18px; font-weight: 600; color: #718096; margin-bottom: 12px;">
            Aucun planning généré
        </div>
        <p style="color: #a0aec0; margin-bottom: 24px;">
            Cliquez sur "Générer le planning" pour créer le premier planning automatique.
        </p>
        <a href="{{ route('planning.generate.form') }}" class="btn btn-primary">
            ✨ Générer maintenant
        </a>
    </div>
@else
    @foreach($creneaux as $semaineCle => $creneauxSemaine)
        @php
            $premierCreneau = $creneauxSemaine->first();
            $semaine = $premierCreneau->semaine;
        @endphp
        <div class="card" style="margin-bottom: 16px;">
            {{-- En-tête de semaine --}}
            <div style="
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin: -24px -24px 20px -24px;
                padding: 14px 24px;
                border-radius: 10px 10px 0 0;
                display: flex;
                align-items: center;
                justify-content: space-between;
            ">
                <span style="color:white; font-weight:700; font-size:15px;">
                    📅 Semaine {{ $semaine }}
                </span>
                <span style="color:rgba(255,255,255,0.8); font-size:13px;">
                    {{ $creneauxSemaine->first()->date->locale('fr')->isoFormat('D MMM') }}
                    —
                    {{ $creneauxSemaine->last()->date->locale('fr')->isoFormat('D MMM YYYY') }}
                </span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Jour</th>
                            <th>Date</th>
                            <th style="color:#3182ce;">🚪 Entrée</th>
                            <th style="color:#276749;">📚 Mektaba</th>
                            <th style="color:#c05621;">🏛️ Salle</th>
                            <th style="color:#c53030;">🥪 Amana Food</th>
                            <th>Événements</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($creneauxSemaine as $creneau)
                            @php
                                // Indexer les tâches par code pour accès facile
                                $tachesMap = $creneau->taches->keyBy(fn($t) => $t->tache?->code);
                                $evenementsStr = $creneau->evenements->pluck('nom')->implode(', ');
                            @endphp
                            <tr @if($creneau->evenements->where('bloque_planning', true)->count())
                                style="background: #fff5f5; opacity: 0.75;"
                                @endif>
                                <td>
                                    <strong>{{ $creneau->jour }}</strong>
                                </td>
                                <td>
                                    {{ $creneau->date->locale('fr')->isoFormat('D MMMM YYYY') }}
                                </td>
                                {{-- Entrée --}}
                                <td>
                                    @if($tachesMap->has('entree'))
                                        @if($tachesMap['entree']->personne)
                                            <span class="tache-entree">
                                                {{ $tachesMap['entree']->personne->prenom }}
                                                {{ $tachesMap['entree']->personne->nom }}
                                            </span>
                                        @else
                                            <span class="tache-vide">Non assigné</span>
                                        @endif
                                    @else
                                        <span class="tache-vide">—</span>
                                    @endif
                                </td>
                                {{-- Mektaba --}}
                                <td>
                                    @if($tachesMap->has('mektaba'))
                                        @if($tachesMap['mektaba']->personne)
                                            <span class="tache-mektaba">
                                                {{ $tachesMap['mektaba']->personne->prenom }}
                                                {{ $tachesMap['mektaba']->personne->nom }}
                                            </span>
                                        @else
                                            <span class="tache-vide">Non assigné</span>
                                        @endif
                                    @else
                                        <span class="tache-vide">—</span>
                                    @endif
                                </td>
                                {{-- Salle --}}
                                <td>
                                    @if($tachesMap->has('salle'))
                                        @if($tachesMap['salle']->personne)
                                            <span class="tache-salle">
                                                {{ $tachesMap['salle']->personne->prenom }}
                                                {{ $tachesMap['salle']->personne->nom }}
                                            </span>
                                        @else
                                            <span class="tache-vide">Non assigné</span>
                                        @endif
                                    @else
                                        <span class="tache-vide">—</span>
                                    @endif
                                </td>
                                {{-- Amana Food --}}
                                <td>
                                    @if($tachesMap->has('amana_food'))
                                        @if($tachesMap['amana_food']->personne)
                                            <span class="tache-amana_food">
                                                {{ $tachesMap['amana_food']->personne->prenom }}
                                                {{ $tachesMap['amana_food']->personne->nom }}
                                            </span>
                                        @else
                                            <span class="tache-vide">Non assigné</span>
                                        @endif
                                    @else
                                        <span class="tache-vide">—</span>
                                    @endif
                                </td>
                                {{-- Événements --}}
                                <td>
                                    @if($evenementsStr)
                                        <span class="badge badge-warning">{{ $evenementsStr }}</span>
                                    @else
                                        <span style="color:#a0aec0;">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endif
@endsection
