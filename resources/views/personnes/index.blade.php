{{-- resources/views/personnes/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Personnes — AMANA')

@section('content')
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Toutes les personnes</div>
            <div class="page-subtitle">Membres, administrateurs et candidats enregistrés dans le système</div>
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
                        <th>Rôle planning</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Statut</th>
                        <th>Début planning</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($personnes as $personne)
                                @php
                                    // Get the planning role (first one since one role per app)
                                    $planningRole = $personne->roles->first();
                                    $roleLabels = [
                                        'admin' => ['label' => 'Admin', 'class' => 'badge-danger', 'icon' => '🛡️'],
                                        'gestionnaire' => ['label' => 'Gestionnaire', 'class' => 'badge-warning', 'icon' => '⚙️'],
                                        'membre' => ['label' => 'Membre', 'class' => 'badge-primary', 'icon' => '👤'],
                                        'benevole' => ['label' => 'Bénévole', 'class' => 'badge-info', 'icon' => '🤝'],
                                    ];
                                    $roleInfo = $planningRole ? ($roleLabels[$planningRole->code] ?? ['label' => $planningRole->libelle, 'class' => 'badge-muted', 'icon' => '❓']) : null;
                                @endphp
                                <tr>
                                    <td class="td-primary">{{ $personne->prenom }} {{ $personne->nom }}</td>
                                    <td>
                                        @if($roleInfo)
                                            <span class="badge {{ $roleInfo['class'] }}">
                                                {{ $roleInfo['icon'] }} {{ $roleInfo['label'] }}
                                            </span>
                                        @else
                                            <span style="color:var(--ink-faint); font-size:12px; font-style:italic;">Aucun rôle</span>
                                        @endif
                                    </td>
                                    <td style="color:var(--ink-muted);">{{ $personne->email }}</td>
                                    <td style="color:var(--ink-muted);">{{ $personne->telephone ?? '—' }}</td>
                                    <td>
                                        @php
                                            $cls = match ($personne->statut) {
                                                'Validé' => 'badge-success',
                                                'En attente' => 'badge-warning',
                                                'Suspendu' => 'badge-danger',
                                                'Archivé' => 'badge-muted',
                                                default => 'badge-muted',
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
                                        <div class="actions">
                                            <a href="{{ route('personnes.edit', $personne->id) }}"
                                                class="btn btn-secondary btn-sm btn-icon" title="Modifier">✏️</a>
                                            <form action="{{ route('personnes.destroy', $personne->id) }}" method="POST"
                                                class="form-delete"
                                                onsubmit="return confirm('Supprimer {{ $personne->prenom }} {{ $personne->nom }} ?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm btn-icon"
                                                    title="Supprimer">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state" style="padding:40px;">
                                    <div class="empty-icon">👥</div>
                                    <div class="empty-title">Aucune personne enregistrée</div>
                                    <div class="empty-desc">Ajoutez des personnes ou attendez de nouvelles candidatures.</div>
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