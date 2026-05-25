{{-- resources/views/evenements/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Événements — AMANA')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">🎉 Événements organisationnels</div>
        <div class="page-subtitle">Vacances, Ramadan, événements spéciaux…</div>
    </div>
    <a href="{{ route('evenements.create') }}" class="btn btn-primary">+ Créer un événement</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Du</th>
                    <th>Au</th>
                    <th>Durée</th>
                    <th>Bloque planning</th>
                    <th>Bénévoles</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($evenements as $evenement)
                    @php
                        $jours = $evenement->date_debut->diffInDays($evenement->date_fin) + 1;
                        $estActif = now()->between($evenement->date_debut, $evenement->date_fin);
                    @endphp
                    <tr @if($estActif) style="background:#fff5f5;" @endif>
                        <td>
                            <strong>{{ $evenement->nom }}</strong>
                            @if($estActif)
                                <span class="badge badge-warning" style="margin-left:6px;">En cours</span>
                            @endif
                        </td>
                        <td>{{ $evenement->date_debut->locale('fr')->isoFormat('D MMM YYYY') }}</td>
                        <td>{{ $evenement->date_fin->locale('fr')->isoFormat('D MMM YYYY') }}</td>
                        <td>{{ $jours }} jour{{ $jours > 1 ? 's' : '' }}</td>
                        <td>
                            @if($evenement->bloque_planning)
                                <span class="badge badge-danger">⛔ Oui</span>
                            @else
                                <span class="badge badge-muted">Non</span>
                            @endif
                        </td>
                        <td>
                            @if($evenement->necessite_benevoles)
                                <span class="badge badge-info">✓ Oui</span>
                            @else
                                <span class="badge badge-muted">Non</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('evenements.edit', $evenement->id) }}"
                                   class="btn btn-secondary btn-sm">✏️</a>
                                <form action="{{ route('evenements.destroy', $evenement->id) }}"
                                      method="POST" class="form-delete"
                                      onsubmit="return confirm('Supprimer l\'événement « {{ $evenement->nom }} » ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center; color:#a0aec0; padding:40px;">
                            Aucun événement enregistré.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
