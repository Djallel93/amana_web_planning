{{-- resources/views/admin/candidatures/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Candidatures — AMANA')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Candidatures en attente</h1>
        <p class="text-[13px] text-ink-muted mt-1">Validez ou refusez les demandes d'inscription</p>
    </div>
    <div class="bg-surface border border-surface-border rounded-xl shadow-sm px-4 py-3 flex flex-col items-center min-w-[90px]">
        <div class="font-heading text-2xl font-bold text-amber-500">{{ $candidatures->count() }}</div>
        <div class="text-[10.5px] font-bold uppercase tracking-[0.6px] text-ink-muted">En attente</div>
    </div>
</div>

@if($candidatures->isEmpty())
    <div class="bg-surface rounded-xl border border-surface-border shadow-sm">
        <div class="text-center py-16 px-8">
            <div class="text-5xl mb-3 opacity-40">✅</div>
            <h3 class="font-heading text-base font-semibold text-ink mb-1.5">Aucune candidature en attente</h3>
            <p class="text-ink-muted text-[13.5px]">
                Toutes les candidatures ont été traitées.<br>
                Les nouvelles inscriptions apparaîtront ici automatiquement.
            </p>
        </div>
    </div>
@else
    <div class="flex flex-col gap-4">
        @foreach($candidatures as $candidat)
            <div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
                <div class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-5">

                        {{-- Identité --}}
                        <div class="flex items-center gap-3.5 flex-1 min-w-[200px]">
                            <div class="w-11 h-11 flex-shrink-0 bg-accent rounded-full flex items-center justify-center text-white text-[16px] font-bold">
                                {{ strtoupper(substr($candidat->prenom, 0, 1)) }}
                            </div>
                            <div>
                                <div class="font-heading text-[15px] font-semibold text-ink">
                                    {{ $candidat->prenom }} {{ strtoupper($candidat->nom) }}
                                </div>
                                <div class="text-[13px] text-ink-muted mt-0.5">{{ $candidat->email }}</div>
                                @if($candidat->telephone)
                                    <div class="text-[12px] text-ink-muted">📞 {{ $candidat->telephone }}</div>
                                @endif
                            </div>
                        </div>

                        {{-- Infos --}}
                        <div class="flex flex-col gap-1 min-w-[160px]">
                            <div class="text-[10px] font-bold uppercase tracking-[0.7px] text-ink-muted mb-1">Informations</div>
                            <div class="text-[12.5px] text-ink-light">
                                🕐 {{ $candidat->derniere_maj?->locale('fr')->isoFormat('D MMM YYYY à HH:mm') ?? '—' }}
                            </div>
                        </div>

                        {{-- Disponibilités --}}
                        <div class="min-w-[200px] flex-1">
                            <div class="text-[10px] font-bold uppercase tracking-[0.7px] text-ink-muted mb-2">Disponibilités</div>
                            @if($candidat->restrictions->isEmpty())
                                <span class="text-[12px] text-ink-faint italic">Non renseignées</span>
                            @else
                                <div class="flex flex-col gap-2">
                                    @foreach(['Vendredi', 'Samedi'] as $jour)
                                        @php $restrictionsDuJour = $candidat->restrictions->where('jour', $jour); @endphp
                                        @if($restrictionsDuJour->isNotEmpty())
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-[11.5px] font-semibold text-ink-muted w-14">{{ $jour }}</span>
                                                @foreach($restrictionsDuJour as $r)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold
                                                                 {{ $r->autorise ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' }}">
                                                        {{ $r->autorise ? '✓' : '✗' }} {{ $r->tache->libelle ?? '—' }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Actions --}}
                        <div class="flex flex-col gap-2 min-w-[180px]">
                            <form action="{{ route('admin.candidatures.valider', $candidat->id) }}" method="POST"
                                  onsubmit="return confirmValidation(this)">
                                @csrf
                                <div class="mb-2">
                                    <label class="text-[10px] font-bold uppercase tracking-[0.6px] text-ink-muted block mb-1.5">Rôle attribué</label>
                                    <select name="role"
                                            class="w-full px-3 py-2 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                                   focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] cursor-pointer">
                                        @foreach($roles as $r)
                                            @php $roleLabels = ['admin'=>'🛡️ Administrateur','gestionnaire'=>'⚙️ Gestionnaire','membre'=>'👤 Membre']; @endphp
                                            <option value="{{ $r->code }}" {{ $r->code === 'membre' ? 'selected' : '' }}>
                                                {{ $roleLabels[$r->code] ?? $r->libelle }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit"
                                        class="w-full min-h-[44px] px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[13px] rounded-lg cursor-pointer transition-colors flex items-center justify-center gap-1.5">
                                    ✅ Valider
                                </button>
                            </form>

                            <form action="{{ route('admin.candidatures.refuser', $candidat->id) }}" method="POST"
                                  data-confirm="Refuser la candidature de {{ $candidat->prenom }} {{ $candidat->nom }} ?" data-confirm-danger>
                                @csrf
                                <button type="submit"
                                        class="w-full min-h-[44px] px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold text-[13px] rounded-lg cursor-pointer transition-colors flex items-center justify-center gap-1.5">
                                    ✕ Refuser
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@endsection

@push('scripts')
<script>
// confirm() natif est synchrone ; window.amanaConfirm() (voir
// resources/js/lib/confirmForms.ts) est asynchrone — on intercepte donc la
// soumission, on attend la réponse de l'utilisateur dans la boîte stylée,
// puis on soumet nous-mêmes le formulaire si confirmé. Un message dynamique
// (rôle sélectionné) empêche d'utiliser le simple attribut data-confirm,
// figé au rendu Blade — voir les autres formulaires de cette page pour le
// cas statique.
function confirmValidation(form) {
    const sel   = form.querySelector('select[name="role"]');
    const label = sel.options[sel.selectedIndex].text.trim();

    window.amanaConfirm({
        message: `Valider cette candidature avec le rôle "${label}" ?\nUn email d'invitation sera envoyé.`,
    }).then((confirmed) => {
        if (confirmed) form.submit();
    });

    return false;
}
</script>
@endpush
