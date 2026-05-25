{{-- resources/views/absences/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Absences — AMANA')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">🏖️ Absences</div>
        <div class="page-subtitle">Les personnes absentes ne seront pas assignées pendant leur absence</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 360px; gap: 24px; align-items: start;">

    {{-- Liste des absences --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">📋 Liste des absences</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Personne</th>
                        <th>Du</th>
                        <th>Au</th>
                        <th>Durée</th>
                        <th>Raison</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($absences as $absence)
                        @php
                            $jours = $absence->date_debut->diffInDays($absence->date_fin) + 1;
                            $estActive = now()->between($absence->date_debut, $absence->date_fin);
                        @endphp
                        <tr @if($estActive) style="background: #fffaf0;" @endif>
                            <td>
                                <strong>
                                    @if($absence->personne)
                                        {{ $absence->personne->prenom }} {{ $absence->personne->nom }}
                                    @else
                                        Personne inconnue
                                    @endif
                                </strong>
                                @if($estActive)
                                    <span class="badge badge-warning" style="margin-left:6px;">En cours</span>
                                @endif
                            </td>
                            <td>{{ $absence->date_debut->locale('fr')->isoFormat('D MMM YYYY') }}</td>
                            <td>{{ $absence->date_fin->locale('fr')->isoFormat('D MMM YYYY') }}</td>
                            <td>{{ $jours }} jour{{ $jours > 1 ? 's' : '' }}</td>
                            <td>{{ $absence->raison ?? '—' }}</td>
                            <td>
                                <form action="{{ route('absences.destroy', $absence->id) }}"
                                      method="POST" class="form-delete"
                                      onsubmit="return confirm('Supprimer cette absence ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; color:#a0aec0; padding:40px;">
                                Aucune absence enregistrée.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Formulaire d'ajout --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">➕ Ajouter une absence</span>
        </div>
        <form action="{{ route('absences.store') }}" method="POST">
            @csrf

            <div class="form-group" style="margin-bottom:16px;">
                <label for="id_personne">Personne <span class="required">*</span></label>
                <select id="id_personne" name="id_personne" required>
                    <option value="">— Choisir —</option>
                    @foreach($personnes as $p)
                        <option value="{{ $p->id }}"
                            {{ old('id_personne') == $p->id ? 'selected' : '' }}>
                            {{ $p->prenom }} {{ $p->nom }}
                        </option>
                    @endforeach
                </select>
                @error('id_personne') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label for="date_debut">Début <span class="required">*</span></label>
                <input type="date" id="date_debut" name="date_debut"
                       value="{{ old('date_debut') }}" required>
                @error('date_debut') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label for="date_fin">Fin <span class="required">*</span></label>
                <input type="date" id="date_fin" name="date_fin"
                       value="{{ old('date_fin') }}" required>
                @error('date_fin') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label for="raison">Raison (optionnel)</label>
                <input type="text" id="raison" name="raison"
                       value="{{ old('raison') }}" maxlength="255"
                       placeholder="Vacances, maladie...">
                @error('raison') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">
                ➕ Ajouter l'absence
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Synchroniser date_fin ≥ date_debut
    document.getElementById('date_debut').addEventListener('change', function() {
        const fin = document.getElementById('date_fin');
        if (!fin.value || fin.value < this.value) {
            fin.value = this.value;
        }
        fin.min = this.value;
    });
</script>
@endpush
