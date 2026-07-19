{{-- resources/views/absences/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Absences — AMANA')

@section('content')

{{-- En-tête --}}
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Absences</h1>
        <p class="text-[13px] text-ink-muted mt-1">
            @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                Gérez les absences de toute l'équipe
            @else
                Consultez les absences de l'équipe et gérez les vôtres
            @endif
        </p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5 items-start">

    {{-- ── Liste --}}
    <div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-amber-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">📋</div>
            <span class="font-heading text-[14px] font-semibold text-ink">
                {{ $absences->count() }} absence{{ $absences->count() !== 1 ? 's' : '' }}
            </span>
        </div>

        {{-- Table desktop (≥ md) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full border-collapse text-[13.5px]">
                <thead>
                    <tr>
                        @foreach(['Personne', 'Début', 'Fin', 'Durée', 'Raison', ''] as $col)
                            <th class="text-left px-5 py-2.5 text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px]
                                       bg-surface-2 border-b border-surface-3 font-body whitespace-nowrap">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($absences as $absence)
                        @php
                            $jours    = $absence->date_debut->diffInDays($absence->date_fin) + 1;
                            $actuelle = now()->between($absence->date_debut, $absence->date_fin);
                            $isMine   = $absence->id_personne === auth()->id();
                            $canManage = auth()->user()->isAdmin() || auth()->user()->isGestionnaire() || $isMine;
                        @endphp
                        <tr class="border-b border-surface-3 last:border-0 hover:bg-surface-2 transition-colors {{ $isMine ? 'bg-sky-50 hover:bg-sky-50/80' : '' }}">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-[30px] h-[30px] rounded-full flex items-center justify-center text-white text-[11px] font-bold flex-shrink-0
                                                {{ $isMine ? 'bg-accent' : 'bg-ink-faint' }}">
                                        {{ strtoupper(substr($absence->personne->prenom ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-[13px] {{ $isMine ? 'text-accent' : 'text-ink' }}">
                                            {{ $absence->personne ? $absence->personne->prenom . ' ' . $absence->personne->nom : 'Inconnu' }}
                                            @if($isMine)<span class="text-[11px] font-normal text-accent ml-1">(moi)</span>@endif
                                        </div>
                                        @if($actuelle)
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">● En cours</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-ink-muted text-[12.5px] whitespace-nowrap">
                                {{ $absence->date_debut->locale('fr')->isoFormat('D MMM YYYY') }}
                            </td>
                            <td class="px-5 py-3 text-ink-muted text-[12.5px] whitespace-nowrap">
                                {{ $absence->date_fin->locale('fr')->isoFormat('D MMM YYYY') }}
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11.5px] font-semibold bg-surface-3 text-ink-muted">
                                    {{ $jours }}j
                                </span>
                            </td>
                            <td class="px-5 py-3 text-ink-muted text-[12.5px]">{{ $absence->raison ?? '—' }}</td>
                            <td class="px-5 py-3 text-right">
                                @if($canManage)
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button"
                                                onclick="openEditAbsenceModal(this)"
                                                data-id="{{ $absence->id }}"
                                                data-id-personne="{{ $absence->id_personne }}"
                                                data-date-debut="{{ $absence->date_debut->toDateString() }}"
                                                data-date-fin="{{ $absence->date_fin->toDateString() }}"
                                                data-raison="{{ $absence->raison }}"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-sky-200 bg-sky-50 hover:bg-sky-100 text-sm transition-colors cursor-pointer min-h-[44px] min-w-[44px]"
                                                title="Modifier">✏️</button>
                                        <form action="{{ route('absences.destroy', $absence->id) }}" method="POST"
                                              data-confirm="Supprimer cette absence ?" data-confirm-danger>
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-rose-200 bg-rose-50 hover:bg-rose-100 text-sm transition-colors cursor-pointer min-h-[44px] min-w-[44px]"
                                                    title="Supprimer">🗑️</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="text-center py-12 px-8">
                                    <div class="text-4xl mb-2 opacity-40">🏖️</div>
                                    <p class="font-heading text-sm font-semibold text-ink mb-1">Aucune absence</p>
                                    <p class="text-ink-muted text-[13px]">Ajoutez une absence via le formulaire ci-contre.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Cartes mobile (< md) --}}
        <div class="md:hidden divide-y divide-surface-3">
            @forelse($absences as $absence)
                @php
                    $jours    = $absence->date_debut->diffInDays($absence->date_fin) + 1;
                    $actuelle = now()->between($absence->date_debut, $absence->date_fin);
                    $isMine   = $absence->id_personne === auth()->id();
                    $canManage = auth()->user()->isAdmin() || auth()->user()->isGestionnaire() || $isMine;
                @endphp
                <div class="px-4 py-3.5 {{ $isMine ? 'bg-sky-50' : '' }}">
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-[11px] font-bold flex-shrink-0
                                        {{ $isMine ? 'bg-accent' : 'bg-ink-faint' }}">
                                {{ strtoupper(substr($absence->personne->prenom ?? '?', 0, 1)) }}
                            </div>
                            <div>
                                <div class="font-semibold text-[13px] {{ $isMine ? 'text-accent' : 'text-ink' }}">
                                    {{ $absence->personne ? $absence->personne->prenom . ' ' . $absence->personne->nom : 'Inconnu' }}
                                    @if($isMine)<span class="text-[11px] font-normal ml-1">(moi)</span>@endif
                                </div>
                                <div class="text-[12px] text-ink-muted">
                                    {{ $absence->date_debut->locale('fr')->isoFormat('D MMM') }}
                                    →
                                    {{ $absence->date_fin->locale('fr')->isoFormat('D MMM YYYY') }}
                                    · {{ $jours }}j
                                </div>
                            </div>
                        </div>
                        @if($canManage)
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        onclick="openEditAbsenceModal(this)"
                                        data-id="{{ $absence->id }}"
                                        data-id-personne="{{ $absence->id_personne }}"
                                        data-date-debut="{{ $absence->date_debut->toDateString() }}"
                                        data-date-fin="{{ $absence->date_fin->toDateString() }}"
                                        data-raison="{{ $absence->raison }}"
                                        class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-sky-200 bg-sky-50 hover:bg-sky-100 text-sm cursor-pointer min-h-[44px] min-w-[44px]">
                                    ✏️
                                </button>
                                <form action="{{ route('absences.destroy', $absence->id) }}" method="POST"
                                      data-confirm="Supprimer cette absence ?" data-confirm-danger>
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-rose-200 bg-rose-50 hover:bg-rose-100 text-sm cursor-pointer min-h-[44px] min-w-[44px]">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-1.5 mt-1">
                        @if($actuelle)
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">● En cours</span>
                        @endif
                        @if($absence->raison)
                            <span class="text-[12px] text-ink-muted">{{ $absence->raison }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-12 px-8">
                    <div class="text-4xl mb-2 opacity-40">🏖️</div>
                    <p class="text-ink-muted text-[13px]">Aucune absence enregistrée.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ── Formulaire --}}
    <div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-emerald-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">➕</div>
            <span class="font-heading text-[14px] font-semibold text-ink">
                {{ (auth()->user()->isAdmin() || auth()->user()->isGestionnaire()) ? 'Ajouter une absence' : 'Déclarer mon absence' }}
            </span>
        </div>
        <div class="px-5 py-5">

            @if(!auth()->user()->isAdmin() && !auth()->user()->isGestionnaire())
                <div class="flex items-start gap-2 px-4 py-3 bg-sky-50 border border-sky-200 rounded-lg text-[12.5px] text-sky-900 mb-4">
                    <span class="flex-shrink-0">ℹ️</span>
                    <span>Vous pouvez uniquement déclarer vos propres absences.</span>
                </div>
            @endif

            <form action="{{ route('absences.store') }}" method="POST" class="flex flex-col gap-4">
                @csrf

                {{-- Personne --}}
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-ink tracking-[0.2px]">
                        @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                            Personne <span class="text-rose-500">*</span>
                        @else
                            Membre
                        @endif
                    </label>
                    @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                        <select id="id_personne" name="id_personne" required
                                class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                       focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] cursor-pointer">
                            <option value="">— Choisir —</option>
                            @foreach($personnes as $p)
                                <option value="{{ $p->id }}" {{ old('id_personne') == $p->id ? 'selected' : '' }}>
                                    {{ $p->prenom }} {{ $p->nom }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="id_personne" value="{{ auth()->id() }}">
                        <div class="px-3.5 py-2.5 bg-surface-2 border-[1.5px] border-ink-faint rounded-lg text-[13.5px] text-ink font-semibold">
                            {{ auth()->user()->prenom }} {{ auth()->user()->nom }}
                        </div>
                    @endif
                    @error('id_personne')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                </div>

                {{-- Date début --}}
                <div class="flex flex-col gap-1.5">
                    <label for="date_debut" class="text-xs font-bold text-ink tracking-[0.2px]">Début <span class="text-rose-500">*</span></label>
                    <input type="date" id="date_debut" name="date_debut" value="{{ old('date_debut') }}" required
                           class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                  focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
                    @error('date_debut')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                </div>

                {{-- Date fin --}}
                <div class="flex flex-col gap-1.5">
                    <label for="date_fin" class="text-xs font-bold text-ink tracking-[0.2px]">Fin <span class="text-rose-500">*</span></label>
                    <input type="date" id="date_fin" name="date_fin" value="{{ old('date_fin') }}" required
                           class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                  focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
                    @error('date_fin')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                </div>

                {{-- Raison --}}
                <div class="flex flex-col gap-1.5">
                    <label for="raison" class="text-xs font-bold text-ink tracking-[0.2px]">
                        Raison <span class="text-ink-muted font-normal">(optionnel)</span>
                    </label>
                    <input type="text" id="raison" name="raison" value="{{ old('raison') }}" maxlength="255"
                           placeholder="Vacances, maladie, congé…"
                           class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                  focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
                    @error('raison')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                </div>

                <button type="submit"
                        class="w-full min-h-[48px] px-4 py-3 bg-accent hover:bg-accent-dark text-white font-bold text-[13.5px] rounded-lg
                               shadow-[0_3px_12px_rgba(3,105,161,0.3)] hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer
                               flex items-center justify-center gap-2">
                    ➕ {{ (auth()->user()->isAdmin() || auth()->user()->isGestionnaire()) ? "Ajouter l'absence" : 'Déclarer mon absence' }}
                </button>
            </form>
        </div>
    </div>

</div>

{{--
    Point de montage EditAbsenceModal.vue — les boutons "✏️" ci-dessus
    appellent openEditAbsenceModal(this) (pont exposé par le composant).
--}}
<div id="vue-edit-absence-modal"></div>

@endsection

@push('scripts')
<script>
    document.getElementById('date_debut')?.addEventListener('change', function () {
        const fin = document.getElementById('date_fin');
        if (!fin.value || fin.value < this.value) fin.value = this.value;
        fin.min = this.value;
    });

    window.AbsencesConfig = {
        routeUpdateBase: '{{ url('absences') }}',
        isPrivileged:    @json(auth()->user()->isAdmin() || auth()->user()->isGestionnaire()),
        currentUserId:   {{ auth()->id() }},
        personnes:       @json($personnes->map(fn($p) => ['id' => $p->id, 'nom' => $p->nom, 'prenom' => $p->prenom])),
    };
</script>
@endpush
