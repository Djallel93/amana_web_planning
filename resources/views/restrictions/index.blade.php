{{-- resources/views/restrictions/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Disponibilités — AMANA')

@section('content')

{{-- En-tête page --}}
<div class="flex flex-wrap items-start justify-between gap-4 mb-7">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">
            {{ ($user->isAdmin() || $user->isGestionnaire()) ? 'Restrictions de disponibilité' : 'Mes disponibilités' }}
        </h1>
        <p class="text-[13px] text-ink-muted mt-1">Case cochée ✓ = la personne peut effectuer la tâche ce jour-là</p>
    </div>
</div>

{{-- ── État vide ── --}}
@if($personnes->isEmpty())
    <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <div class="text-center py-16 px-8">
            <div class="text-5xl mb-3 opacity-40">🔒</div>
            <h3 class="font-heading text-base font-semibold text-ink mb-1.5">Aucun membre actif</h3>
            <p class="text-ink-muted text-[13.5px] mb-6">Ajoutez des personnes avec statut "Validé" et une date de début planning.</p>
            @if($user->isAdmin())
                <a href="{{ route('personnes.create') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-accent hover:bg-accent-dark text-white text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
                    + Ajouter une personne
                </a>
            @endif
        </div>
    </div>

{{-- ── Vue membre ── --}}
@elseif(!$user->isAdmin() && !$user->isGestionnaire())

    {{-- Formulaire d'édition personnelle --}}
    <div class="bg-sky-50 border-[1.5px] border-sky-200 rounded-xl p-5 sm:p-6 mb-5">
        <h2 class="font-heading text-[15px] font-semibold text-ink flex items-center gap-2 mb-1">
            🔧 Modifier mes disponibilités
        </h2>
        <p class="text-[13px] text-ink-muted mb-5 leading-relaxed">
            Cochez les tâches que vous <strong class="text-ink-light">pouvez effectuer</strong> chaque jour.
            Ces informations sont prises en compte lors de la génération du planning.
        </p>

        <form action="{{ route('restrictions.update') }}" method="POST" id="memberForm">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                @foreach($taches as $tache)
                    <div class="bg-white border-[1.5px] border-surface-border rounded-lg p-3.5 hover:border-accent transition-colors">
                        <div class="text-[13px] font-bold text-ink mb-2.5 flex items-center gap-1.5">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold chip-{{ $tache->code }}">
                                {{ $tache->libelle }}
                            </span>
                        </div>
                        <div class="flex flex-col gap-2">
                            @foreach(['Vendredi', 'Samedi'] as $jour)
                                @php $autorise = $restrictionsMap[$user->id][$tache->id][$jour] ?? true; @endphp
                                <label class="flex items-center gap-2 text-[13px] text-ink-light cursor-pointer min-h-[44px] sm:min-h-0">
                                    <input type="checkbox"
                                           name="checkboxes[{{ $user->id }}][{{ $tache->id }}][{{ $jour }}]"
                                           value="1"
                                           {{ $autorise ? 'checked' : '' }}
                                           class="w-4 h-4 accent-accent cursor-pointer flex-shrink-0">
                                    <span>{{ $jour }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-accent hover:bg-accent-dark text-white text-[13px] font-semibold rounded-lg
                           shadow-[0_3px_12px_rgba(3,105,161,0.3)] hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer min-h-[44px]">
                💾 Enregistrer mes disponibilités
            </button>
        </form>
    </div>

    {{-- Grille lecture seule : vue équipe --}}
    <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">👀</div>
            <span class="font-heading text-[14px] font-semibold text-ink">Disponibilités de l'équipe</span>
        </div>

        <div class="flex items-center gap-2 bg-sky-50 border-b border-sky-100 px-5 py-2.5 text-[12.5px] text-sky-900">
            <span>ℹ️</span>
            <span>Vue en lecture seule — vous pouvez consulter les disponibilités de toute l'équipe.</span>
        </div>

        {{-- Table desktop (≥ md) --}}
        <div class="hidden md:block overflow-x-auto">
            @include('restrictions.partials._table', [
                'editable'   => false,
                'formId'     => null,
                'personnes'  => $personnes,
                'taches'     => $taches,
                'restrictionsMap' => $restrictionsMap,
                'user'       => $user,
            ])
        </div>

        {{-- Cartes mobile (< md) --}}
        <div class="md:hidden divide-y divide-surface-3">
            @foreach($personnes as $personne)
                @php $isMe = $personne->id === $user->id; @endphp
                <div class="px-4 py-3 {{ $isMe ? 'bg-sky-50' : '' }}">
                    <div class="flex items-center gap-2.5 mb-2.5">
                        <div class="w-7 h-7 bg-accent rounded-full flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0">
                            {{ strtoupper(substr($personne->prenom, 0, 1)) }}
                        </div>
                        <span class="font-semibold text-[13px] text-ink">
                            {{ $personne->prenom }} {{ $personne->nom }}
                            @if($isMe)<span class="text-[11px] font-normal text-accent ml-1">(moi)</span>@endif
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(['Vendredi', 'Samedi'] as $jour)
                            <div>
                                <p class="text-[10px] font-bold text-ink-muted uppercase tracking-wide mb-1.5 px-1">{{ $jour }}</p>
                                <div class="flex flex-col gap-1">
                                    @foreach($taches as $tache)
                                        @php $autorise = $restrictionsMap[$personne->id][$tache->id][$jour] ?? true; @endphp
                                        <div class="flex items-center gap-1.5 text-[12px] px-1">
                                            <span class="{{ $autorise ? 'text-emerald-600' : 'text-rose-400 opacity-50' }}">
                                                {{ $autorise ? '✓' : '✗' }}
                                            </span>
                                            <span class="chip-{{ $tache->code }} inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold">
                                                {{ $tache->libelle }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

{{-- ── Vue admin / gestionnaire ── --}}
@else
    <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <form action="{{ route('restrictions.update') }}" method="POST" id="restrictionsForm">
            @csrf

            {{-- Table desktop (≥ md) --}}
            <div class="hidden md:block overflow-x-auto">
                @include('restrictions.partials._table', [
                    'editable'   => true,
                    'formId'     => 'restrictionsForm',
                    'personnes'  => $personnes,
                    'taches'     => $taches,
                    'restrictionsMap' => $restrictionsMap,
                    'user'       => $user,
                ])
            </div>

            {{-- Cartes mobile éditables (< md) --}}
            <div class="md:hidden divide-y divide-surface-3">
                @foreach($personnes as $personne)
                    @php $isMe = $personne->id === $user->id; @endphp
                    <div class="px-4 py-3 {{ $isMe ? 'bg-sky-50' : '' }}">
                        <div class="flex items-center gap-2.5 mb-3">
                            <div class="w-7 h-7 bg-accent rounded-full flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0">
                                {{ strtoupper(substr($personne->prenom, 0, 1)) }}
                            </div>
                            <span class="font-semibold text-[13px] text-ink">
                                {{ $personne->prenom }} {{ $personne->nom }}
                                @if($isMe)<span class="text-[11px] font-normal text-accent ml-1">(moi)</span>@endif
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            @foreach(['Vendredi', 'Samedi'] as $jour)
                                <div>
                                    <p class="text-[10px] font-bold text-ink-muted uppercase tracking-wide mb-2 px-1">{{ $jour }}</p>
                                    <div class="flex flex-col gap-1">
                                        @foreach($taches as $tache)
                                            @php $autorise = $restrictionsMap[$personne->id][$tache->id][$jour] ?? true; @endphp
                                            <label class="flex items-center gap-2 min-h-[44px] sm:min-h-0 px-1 cursor-pointer">
                                                <input type="checkbox"
                                                       name="checkboxes[{{ $personne->id }}][{{ $tache->id }}][{{ $jour }}]"
                                                       value="1"
                                                       {{ $autorise ? 'checked' : '' }}
                                                       class="w-4 h-4 accent-accent cursor-pointer flex-shrink-0">
                                                <span class="chip-{{ $tache->code }} inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold">
                                                    {{ $tache->libelle }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Toolbar --}}
            <div class="flex flex-wrap items-center gap-2.5 px-5 py-4 border-t border-surface-3">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-accent hover:bg-accent-dark text-white text-[13px] font-semibold rounded-lg
                               shadow-[0_3px_12px_rgba(3,105,161,0.3)] hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer min-h-[44px]">
                    💾 Enregistrer toutes les restrictions
                </button>
                <button type="button" onclick="toggleAll(true)"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-xs font-semibold rounded-lg transition-colors cursor-pointer bg-transparent min-h-[44px]">
                    Tout cocher
                </button>
                <button type="button" onclick="toggleAll(false)"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-xs font-semibold rounded-lg transition-colors cursor-pointer bg-transparent min-h-[44px]">
                    Tout décocher
                </button>
                <div class="ml-auto flex items-center gap-4 text-[12px] text-ink-muted">
                    <div class="flex items-center gap-1.5">
                        <input type="checkbox" checked disabled class="w-3.5 h-3.5 accent-accent"> Disponible
                    </div>
                    <div class="flex items-center gap-1.5">
                        <input type="checkbox" disabled class="w-3.5 h-3.5"> Indisponible
                    </div>
                </div>
            </div>
        </form>
    </div>
@endif

@endsection

@push('scripts')
<script>
    function toggleAll(state) {
        document.querySelectorAll('#restrictionsForm input[type="checkbox"]')
            .forEach(cb => cb.checked = state);
    }
</script>
@endpush
