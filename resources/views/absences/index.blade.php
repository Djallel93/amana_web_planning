{{-- resources/views/absences/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Absences — AMANA')

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Absences</div>
        <div class="page-subtitle">Les personnes absentes ne seront pas assignées pendant leur période d'absence</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 360px; gap: 24px; align-items: start;">

    {{-- List --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon" style="background:var(--amber-bg);">📋</div>
                {{ $absences->count() }} absence{{ $absences->count() !== 1 ? 's' : '' }}
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Personne</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th>Durée</th>
                        <th>Raison</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($absences as $absence)
                        @php
                            $jours    = $absence->date_debut->diffInDays($absence->date_fin) + 1;
                            $actuelle = now()->between($absence->date_debut, $absence->date_fin);
                        @endphp
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="
                                        width:32px; height:32px;
                                        background:linear-gradient(135deg,var(--primary),var(--violet));
                                        border-radius:50%;
                                        display:flex; align-items:center; justify-content:center;
                                        color:white; font-size:12px; font-weight:700;
                                        flex-shrink:0;
                                    ">
                                        {{ strtoupper(substr($absence->personne->prenom ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="td-primary">
                                            {{ $absence->personne ? $absence->personne->prenom . ' ' . $absence->personne->nom : 'Inconnu' }}
                                        </div>
                                        @if($actuelle)
                                            <span class="badge badge-warning badge-dot" style="font-size:10px;">En cours</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td style="color:var(--ink-muted); font-size:12.5px;">
                                {{ $absence->date_debut->locale('fr')->isoFormat('D MMM YYYY') }}
                            </td>
                            <td style="color:var(--ink-muted); font-size:12.5px;">
                                {{ $absence->date_fin->locale('fr')->isoFormat('D MMM YYYY') }}
                            </td>
                            <td>
                                <span class="badge badge-muted">{{ $jours }}j</span>
                            </td>
                            <td style="color:var(--ink-muted); font-size:12.5px;">
                                {{ $absence->raison ?? '—' }}
                            </td>
                            <td>
                                <form action="{{ route('absences.destroy', $absence->id) }}"
                                      method="POST" class="form-delete"
                                      onsubmit="return confirm('Supprimer cette absence ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Supprimer">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">
                            <div class="empty-state" style="padding:36px;">
                                <div class="empty-icon">🏖️</div>
                                <div class="empty-title">Aucune absence</div>
                                <div class="empty-desc">Ajoutez une absence via le formulaire ci-contre.</div>
                            </div>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add form --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon" style="background:var(--emerald-bg);">➕</div>
                Ajouter une absence
            </div>
        </div>
        <div class="card-body">
            <form action="{{ route('absences.store') }}" method="POST">
                @csrf
                <div class="form-group" style="margin-bottom:16px;">
                    <label for="id_personne">Personne <span class="req">*</span></label>
                    <select id="id_personne" name="id_personne" required>
                        <option value="">— Choisir —</option>
                        @foreach($personnes as $p)
                            <option value="{{ $p->id }}" {{ old('id_personne') == $p->id ? 'selected' : '' }}>
                                {{ $p->prenom }} {{ $p->nom }}
                            </option>
                        @endforeach
                    </select>
                    @error('id_personne')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-grid" style="margin-bottom:16px;">
                    <div class="form-group">
                        <label for="date_debut">Début <span class="req">*</span></label>
                        <input type="date" id="date_debut" name="date_debut"
                               value="{{ old('date_debut') }}" required>
                        @error('date_debut')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="date_fin">Fin <span class="req">*</span></label>
                        <input type="date" id="date_fin" name="date_fin"
                               value="{{ old('date_fin') }}" required>
                        @error('date_fin')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:22px;">
                    <label for="raison">Raison <span style="color:var(--ink-muted); font-weight:400;">(optionnel)</span></label>
                    <input type="text" id="raison" name="raison"
                           value="{{ old('raison') }}" maxlength="255"
                           placeholder="Vacances, maladie, congé...">
                    @error('raison')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">
                    ➕ Ajouter l'absence
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('date_debut').addEventListener('change', function() {
    const fin = document.getElementById('date_fin');
    if (!fin.value || fin.value < this.value) fin.value = this.value;
    fin.min = this.value;
});
</script>
@endpush
