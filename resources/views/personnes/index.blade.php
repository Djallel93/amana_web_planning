{{-- resources/views/personnes/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Personnes — AMANA')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">👥 Personnes</div>
        <div class="page-subtitle">Membres officiels et bénévoles</div>
    </div>
    <a href="{{ route('personnes.create') }}" class="btn btn-primary">+ Ajouter une personne</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Statut</th>
                    <th>Début planning</th>
                    <th>Tirelire</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($personnes as $personne)
                    <tr>
                        <td>
                            <strong>{{ $personne->prenom }} {{ $personne->nom }}</strong>
                        </td>
                        <td>{{ $personne->email }}</td>
                        <td>{{ $personne->telephone ?? '—' }}</td>
                        <td>
                            @php
                                $badgeClass = match($personne->statut) {
                                    'Validé'     => 'badge-success',
                                    'En attente' => 'badge-warning',
                                    'Suspendu'   => 'badge-danger',
                                    'Archivé'    => 'badge-muted',
                                    default      => 'badge-muted',
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $personne->statut }}</span>
                        </td>
                        <td>
                            {{ $personne->date_debut_planning
                                ? $personne->date_debut_planning->locale('fr')->isoFormat('D MMM YYYY')
                                : '—' }}
                        </td>
                        <td>
                            @if($personne->tirelire)
                                <span class="badge badge-success">✓</span>
                            @else
                                <span style="color:#a0aec0;">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('personnes.edit', $personne->id) }}"
                                   class="btn btn-secondary btn-sm">✏️ Modifier</a>
                                <form action="{{ route('personnes.destroy', $personne->id) }}"
                                      method="POST" class="form-delete"
                                      onsubmit="return confirm('Supprimer {{ $personne->prenom }} {{ $personne->nom }} ? Cette action est irréversible.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center; color:#a0aec0; padding: 40px;">
                            Aucune personne enregistrée.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
