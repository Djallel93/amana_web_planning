{{-- resources/views/evenements/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Événements — AMANA')

@section('content')
    @include('partials.tache-colors')

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Événements organisationnels</div>
            <div class="page-subtitle">Vacances, Ramadan, événements spéciaux…</div>
        </div>
        @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
            <a href="{{ route('evenements.create') }}" class="btn btn-primary">+ Créer un événement</a>
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon" style="background:var(--amber-bg);">🎉</div>
                {{ $evenements->count() }} événement{{ $evenements->count() !== 1 ? 's' : '' }}
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Événement</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th>Durée</th>
                        <th>Tâches bloquées</th>
                        @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                            <th>Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($evenements as $evt)
                        @php
                            $jours = $evt->date_debut->diffInDays($evt->date_fin) + 1;
                            $actif = now()->between($evt->date_debut, $evt->date_fin);
                            $futur = now()->lt($evt->date_debut);
                            $nbBloquees = $evt->tachesBloquees->count();
                        @endphp
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div style="
                                                        width:34px;height:34px;
                                                        background:{{ $nbBloquees > 0 ? 'var(--rose-bg)' : 'var(--amber-bg)' }};
                                                        border-radius:var(--radius-sm);
                                                        display:flex;align-items:center;justify-content:center;
                                                        font-size:16px;flex-shrink:0;">
                                        {{ $nbBloquees > 0 ? '🚫' : '📢' }}
                                    </div>
                                    <div>
                                        <div class="td-primary">{{ $evt->nom }}</div>
                                        @if($actif)
                                            <span class="badge badge-warning badge-dot" style="font-size:10px;">En cours</span>
                                        @elseif($futur)
                                            <span class="badge badge-info badge-dot" style="font-size:10px;">À venir</span>
                                        @else
                                            <span class="badge badge-muted" style="font-size:10px;">Passé</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td style="color:var(--ink-muted);font-size:12.5px;">
                                {{ $evt->date_debut->locale('fr')->isoFormat('D MMM YYYY') }}
                            </td>
                            <td style="color:var(--ink-muted);font-size:12.5px;">
                                {{ $evt->date_fin->locale('fr')->isoFormat('D MMM YYYY') }}
                            </td>
                            <td><span class="badge badge-muted">{{ $jours }}j</span></td>
                            <td>
                                @if($nbBloquees === 0)
                                    <span class="badge badge-info" style="font-size:11px;">📢 Informatif</span>
                                @else
                                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                        @foreach($evt->tachesBloquees as $tache)
                                            @php
                                                $s = $tacheColors[$tache->code] ?? ['bg' => 'var(--surface-3)', 'color' => 'var(--ink)'];
                                            @endphp
                                            <span style="
                                                                                display:inline-flex;align-items:center;
                                                                                padding:2px 8px;border-radius:20px;
                                                                                font-size:11px;font-weight:600;
                                                                                background:{{ $s['bg'] }};color:{{ $s['color'] }};">
                                                {{ $tache->libelle }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                                <td>
                                    <div class="actions">
                                        <a href="{{ route('evenements.edit', $evt->id) }}" class="btn btn-secondary btn-sm btn-icon"
                                            title="Modifier">✏️</a>
                                        <form action="{{ route('evenements.destroy', $evt->id) }}" method="POST" class="form-delete"
                                            onsubmit="return confirm('Supprimer « {{ $evt->nom }} » ?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm btn-icon"
                                                title="Supprimer">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ (auth()->user()->isAdmin() || auth()->user()->isGestionnaire()) ? 6 : 5 }}">
                                <div class="empty-state" style="padding:40px;">
                                    <div class="empty-icon">🎉</div>
                                    <div class="empty-title">Aucun événement</div>
                                    <div class="empty-desc">
                                        @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                                            Créez des événements pour bloquer certaines tâches du planning ou informer l'équipe.
                                        @else
                                            Aucun événement n'a encore été créé.
                                        @endif
                                    </div>
                                    @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                                        <a href="{{ route('evenements.create') }}" class="btn btn-primary">+ Créer un événement</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection