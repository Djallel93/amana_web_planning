{{-- resources/views/absences/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Absences — AMANA')

@section('content')
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Absences</div>
            <div class="page-subtitle">
                @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                    Gérez les absences de toute l'équipe
                @else
                    Consultez les absences de l'équipe et gérez les vôtres
                @endif
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:22px;align-items:start;">

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
                                            $jours = $absence->date_debut->diffInDays($absence->date_fin) + 1;
                                            $actuelle = now()->between($absence->date_debut, $absence->date_fin);
                                            $isMine = $absence->id_personne === auth()->id();
                                            $canDelete = auth()->user()->isAdmin() || auth()->user()->isGestionnaire() || $isMine;
                                        @endphp
                                        <tr style="{{ $isMine ? 'background:#f0f9ff;' : '' }}">
                                            <td>
                                                <div style="display:flex;align-items:center;gap:10px;">
                                                    <div style="
                                                                                    width:30px;height:30px;
                                                                                    background:{{ $isMine ? 'var(--app-accent)' : '#9ca3af' }};
                                                                                    border-radius:50%;
                                                                                    display:flex;align-items:center;justify-content:center;
                                                                                    color:white;font-size:11px;font-weight:700;flex-shrink:0;
                                                                                ">
                                                        {{ strtoupper(substr($absence->personne->prenom ?? '?', 0, 1)) }}
                                                    </div>
                                                    <div>
                                                        <div class="td-primary" style="{{ $isMine ? 'color:var(--app-accent);' : '' }}">
                                                            {{ $absence->personne
                            ? $absence->personne->prenom . ' ' . $absence->personne->nom
                            : 'Inconnu' }}
                                                            @if($isMine)
                                                                <span
                                                                    style="font-size:11px;font-weight:400;color:var(--app-accent);margin-left:4px;">(moi)</span>
                                                            @endif
                                                        </div>
                                                        @if($actuelle)
                                                            <span class="badge badge-warning badge-dot" style="font-size:10px;">En cours</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="color:var(--ink-muted);font-size:12.5px;">
                                                {{ $absence->date_debut->locale('fr')->isoFormat('D MMM YYYY') }}
                                            </td>
                                            <td style="color:var(--ink-muted);font-size:12.5px;">
                                                {{ $absence->date_fin->locale('fr')->isoFormat('D MMM YYYY') }}
                                            </td>
                                            <td><span class="badge badge-muted">{{ $jours }}j</span></td>
                                            <td style="color:var(--ink-muted);font-size:12.5px;">{{ $absence->raison ?? '—' }}</td>
                                            <td>
                                                @if($canDelete)
                                                    <form action="{{ route('absences.destroy', $absence->id) }}" method="POST"
                                                        class="form-delete" onsubmit="return confirm('Supprimer cette absence ?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm btn-icon"
                                                            title="Supprimer">🗑️</button>
                                                    </form>
                                                @else
                                                    <span style="display:inline-block;width:32px;"></span>
                                                @endif
                                            </td>
                                        </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state" style="padding:36px;">
                                        <div class="empty-icon">🏖️</div>
                                        <div class="empty-title">Aucune absence</div>
                                        <div class="empty-desc">Ajoutez une absence via le formulaire ci-contre.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Form --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon" style="background:var(--emerald-bg);">➕</div>
                    @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                        Ajouter une absence
                    @else
                        Déclarer mon absence
                    @endif
                </div>
            </div>
            <div class="card-body">

                @if(!auth()->user()->isAdmin() && !auth()->user()->isGestionnaire())
                    <div
                        style="background:var(--sky-bg);border:1px solid var(--sky-border);border-radius:var(--radius);padding:11px 14px;margin-bottom:16px;font-size:12.5px;color:#0c4a6e;display:flex;align-items:flex-start;gap:8px;">
                        <span style="flex-shrink:0;">ℹ️</span>
                        <span>Vous pouvez uniquement déclarer vos propres absences.</span>
                    </div>
                @endif

                <form action="{{ route('absences.store') }}" method="POST">
                    @csrf

                    {{-- Personne selector --}}
                    <div class="form-group" style="margin-bottom:14px;">
                        @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                            <label for="id_personne">Personne <span class="req">*</span></label>
                            <select id="id_personne" name="id_personne" required>
                                <option value="">— Choisir —</option>
                                @foreach($personnes as $p)
                                    <option value="{{ $p->id }}" {{ old('id_personne') == $p->id ? 'selected' : '' }}>
                                        {{ $p->prenom }} {{ $p->nom }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input type="hidden" name="id_personne" value="{{ auth()->id() }}">
                            <div
                                style="padding:9px 13px;background:var(--surface-2);border:1.5px solid var(--ink-faint);border-radius:var(--radius);font-size:13.5px;color:var(--ink);font-weight:600;">
                                {{ auth()->user()->prenom }} {{ auth()->user()->nom }}
                            </div>
                        @endif
                        @error('id_personne')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    {{-- Dates: single-column layout to avoid overflow in narrow sidebar --}}
                    <div class="form-group" style="margin-bottom:14px;">
                        <label for="date_debut">Début <span class="req">*</span></label>
                        <input type="date" id="date_debut" name="date_debut" value="{{ old('date_debut') }}" required>
                        @error('date_debut')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group" style="margin-bottom:14px;">
                        <label for="date_fin">Fin <span class="req">*</span></label>
                        <input type="date" id="date_fin" name="date_fin" value="{{ old('date_fin') }}" required>
                        @error('date_fin')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group" style="margin-bottom:20px;">
                        <label for="raison">Raison <span
                                style="color:var(--ink-muted);font-weight:400;">(optionnel)</span></label>
                        <input type="text" id="raison" name="raison" value="{{ old('raison') }}" maxlength="255"
                            placeholder="Vacances, maladie, congé…">
                        @error('raison')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                        ➕
                        {{ (auth()->user()->isAdmin() || auth()->user()->isGestionnaire()) ? 'Ajouter l\'absence' : 'Déclarer mon absence' }}
                    </button>
                </form>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('date_debut')?.addEventListener('change', function () {
            const fin = document.getElementById('date_fin');
            if (!fin.value || fin.value < this.value) fin.value = this.value;
            fin.min = this.value;
        });
    </script>
@endpush