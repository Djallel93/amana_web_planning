{{-- resources/views/personnes/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Personnes — AMANA')

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Membres &amp; Administrateurs</div>
        <div class="page-subtitle">Personnes avec rôle Admin ou Membre ayant accès au planning</div>
    </div>
    <a href="{{ route('personnes.create') }}" class="btn btn-primary">+ Ajouter</a>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <div class="card-title-icon" style="background:var(--violet-bg);">👥</div>
            <span>{{ $personnes->count() }} personne{{ $personnes->count() !== 1 ? 's' : '' }}</span>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Rôle</th>
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
                        <td class="td-primary">{{ $personne->prenom }} {{ $personne->nom }}</td>
                        <td>
                            @foreach($personne->roles as $role)
                                <span class="badge {{ $role->code === 'admin' ? 'badge-danger' : 'badge-primary' }}">
                                    {{ $role->libelle }}
                                </span>
                            @endforeach
                        </td>
                        <td style="color:var(--ink-muted);">{{ $personne->email }}</td>
                        <td style="color:var(--ink-muted);">{{ $personne->telephone ?? '—' }}</td>
                        <td>
                            @php
                                $cls = match($personne->statut) {
                                    'Validé'     => 'badge-success',
                                    'En attente' => 'badge-warning',
                                    'Suspendu'   => 'badge-danger',
                                    'Archivé'    => 'badge-muted',
                                    default      => 'badge-muted',
                                };
                            @endphp
                            <span class="badge {{ $cls }} badge-dot">{{ $personne->statut }}</span>
                        </td>
                        <td style="color:var(--ink-muted); font-size:12.5px;">
                            {{ $personne->date_debut_planning
                                ? $personne->date_debut_planning->locale('fr')->isoFormat('D MMM YYYY')
                                : '—' }}
                        </td>
                        <td>
                            @if($personne->tirelire)
                                <span class="badge badge-success">✓ Oui</span>
                            @else
                                <span style="color:var(--ink-faint);">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('personnes.edit', $personne->id) }}"
                                   class="btn btn-secondary btn-sm btn-icon" title="Modifier">✏️</a>
                                <form action="{{ route('personnes.destroy', $personne->id) }}"
                                      method="POST" class="form-delete"
                                      onsubmit="return confirm('Supprimer {{ $personne->prenom }} {{ $personne->nom }} ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Supprimer">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty-state" style="padding:40px;">
                                <div class="empty-icon">👥</div>
                                <div class="empty-title">Aucun membre ou administrateur</div>
                                <div class="empty-desc">Ajoutez des personnes avec les rôles Admin ou Membre.</div>
                                <a href="{{ route('personnes.create') }}" class="btn btn-primary">+ Ajouter une personne</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
