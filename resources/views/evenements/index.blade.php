{{-- resources/views/evenements/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Événements — AMANA')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Événements organisationnels</h1>
        <p class="text-[13px] text-ink-muted mt-1">Vacances, Ramadan, événements spéciaux…</p>
    </div>
    @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
        <a href="{{ route('evenements.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-accent hover:bg-accent-dark text-white text-[13px] font-semibold rounded-lg
                  shadow-[0_3px_12px_rgba(3,105,161,0.3)] hover:-translate-y-px transition-all no-underline min-h-[44px]">
            + Créer un événement
        </a>
    @endif
</div>

<div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden">
    <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
        <div class="w-7 h-7 bg-amber-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">🎉</div>
        <span class="font-heading text-[14px] font-semibold text-ink">
            {{ $evenements->count() }} événement{{ $evenements->count() !== 1 ? 's' : '' }}
        </span>
    </div>

    {{-- Table desktop (≥ md) --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full border-collapse text-[13.5px]">
            <thead>
                <tr>
                    @php $cols = array_merge(['Événement','Début','Fin','Durée','Tâches bloquées'],
                        (auth()->user()->isAdmin() || auth()->user()->isGestionnaire()) ? ['Actions'] : []); @endphp
                    @foreach($cols as $col)
                        <th class="text-left px-5 py-2.5 text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px]
                                   bg-surface-2 border-b border-surface-3 font-body whitespace-nowrap">{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($evenements as $evt)
                    @php
                        $jours      = $evt->date_debut->diffInDays($evt->date_fin) + 1;
                        $actif      = now()->between($evt->date_debut, $evt->date_fin);
                        $futur      = now()->lt($evt->date_debut);
                        $nbBloquees = $evt->tachesBloquees->count();
                    @endphp
                    <tr class="border-b border-surface-3 last:border-0 hover:bg-surface-2 transition-colors">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-base flex-shrink-0
                                            {{ $nbBloquees > 0 ? 'bg-rose-50' : 'bg-amber-50' }}">
                                    {{ $nbBloquees > 0 ? '🚫' : '📢' }}
                                </div>
                                <div>
                                    <div class="font-semibold text-ink">{{ $evt->nom }}</div>
                                    @if($actif)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">● En cours</span>
                                    @elseif($futur)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-700">● À venir</span>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-surface-3 text-ink-muted">Passé</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-ink-muted text-[12.5px] whitespace-nowrap">
                            {{ $evt->date_debut->locale('fr')->isoFormat('D MMM YYYY') }}
                        </td>
                        <td class="px-5 py-3 text-ink-muted text-[12.5px] whitespace-nowrap">
                            {{ $evt->date_fin->locale('fr')->isoFormat('D MMM YYYY') }}
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11.5px] font-semibold bg-surface-3 text-ink-muted">{{ $jours }}j</span>
                        </td>
                        <td class="px-5 py-3">
                            @if($nbBloquees === 0)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 text-sky-700">📢 Informatif</span>
                            @else
                                <div class="flex flex-wrap gap-1">
                                    @foreach($evt->tachesBloquees as $tache)
                                        <span class="chip-{{ $tache->code }} inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold">
                                            {{ $tache->libelle }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-1.5">
                                    <a href="{{ route('evenements.edit', $evt->id) }}"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-surface-border bg-white hover:bg-surface-2 text-sm transition-colors no-underline min-h-[44px] min-w-[44px]"
                                       title="Modifier">✏️</a>
                                    <form action="{{ route('evenements.destroy', $evt->id) }}" method="POST"
                                          onsubmit="return confirm('Supprimer « {{ $evt->nom }} » ?')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-rose-200 bg-rose-50 hover:bg-rose-100 text-sm transition-colors cursor-pointer min-h-[44px] min-w-[44px]"
                                                title="Supprimer">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ (auth()->user()->isAdmin() || auth()->user()->isGestionnaire()) ? 6 : 5 }}">
                        <div class="text-center py-14 px-8">
                            <div class="text-5xl mb-3 opacity-40">🎉</div>
                            <p class="font-heading text-sm font-semibold text-ink mb-1.5">Aucun événement</p>
                            <p class="text-ink-muted text-[13.5px] mb-5">
                                @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                                    Créez des événements pour bloquer certaines tâches ou informer l'équipe.
                                @else
                                    Aucun événement n'a encore été créé.
                                @endif
                            </p>
                            @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                                <a href="{{ route('evenements.create') }}"
                                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-accent hover:bg-accent-dark text-white text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
                                    + Créer un événement
                                </a>
                            @endif
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Cartes mobile (< md) --}}
    <div class="md:hidden divide-y divide-surface-3">
        @forelse($evenements as $evt)
            @php
                $jours      = $evt->date_debut->diffInDays($evt->date_fin) + 1;
                $actif      = now()->between($evt->date_debut, $evt->date_fin);
                $futur      = now()->lt($evt->date_debut);
                $nbBloquees = $evt->tachesBloquees->count();
            @endphp
            <div class="px-4 py-3.5">
                <div class="flex items-start justify-between gap-3 mb-2">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center text-base flex-shrink-0 {{ $nbBloquees > 0 ? 'bg-rose-50' : 'bg-amber-50' }}">
                            {{ $nbBloquees > 0 ? '🚫' : '📢' }}
                        </div>
                        <div>
                            <div class="font-semibold text-[13.5px] text-ink">{{ $evt->nom }}</div>
                            <div class="text-[12px] text-ink-muted mt-0.5">
                                {{ $evt->date_debut->locale('fr')->isoFormat('D MMM') }} → {{ $evt->date_fin->locale('fr')->isoFormat('D MMM YYYY') }} · {{ $jours }}j
                            </div>
                        </div>
                    </div>
                    @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                        <div class="flex gap-1.5 flex-shrink-0">
                            <a href="{{ route('evenements.edit', $evt->id) }}"
                               class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-surface-border bg-white hover:bg-surface-2 text-sm no-underline min-h-[44px] min-w-[44px]">✏️</a>
                            <form action="{{ route('evenements.destroy', $evt->id) }}" method="POST"
                                  onsubmit="return confirm('Supprimer « {{ $evt->nom }} » ?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-rose-200 bg-rose-50 hover:bg-rose-100 text-sm cursor-pointer min-h-[44px] min-w-[44px]">🗑️</button>
                            </form>
                        </div>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                    @if($actif)<span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">● En cours</span>
                    @elseif($futur)<span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-700">● À venir</span>
                    @else<span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-surface-3 text-ink-muted">Passé</span>
                    @endif
                    @if($nbBloquees === 0)
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 text-sky-700">📢 Informatif</span>
                    @else
                        @foreach($evt->tachesBloquees as $tache)
                            <span class="chip-{{ $tache->code }} inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold">{{ $tache->libelle }}</span>
                        @endforeach
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-12 px-8">
                <div class="text-4xl mb-2 opacity-40">🎉</div>
                <p class="text-ink-muted text-[13px]">Aucun événement créé.</p>
            </div>
        @endforelse
    </div>
</div>

@endsection
