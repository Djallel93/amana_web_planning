{{-- resources/views/evenements/import.blade.php --}}
@extends('layouts.app')

@section('title', 'Importer des événements — AMANA')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Importer des événements</h1>
        <p class="text-[13px] text-ink-muted mt-1">Créer plusieurs événements en une fois — par fichier CSV ou saisie manuelle.</p>
    </div>
    <a href="{{ route('evenements.index') }}"
       class="inline-flex items-center gap-2 px-4 py-2.5 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
        ← Retour
    </a>
</div>

<div class="max-w-[820px] flex flex-col gap-4">

    {{-- ── Format attendu --}}
    <div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">📄</div>
            <span class="font-heading text-[14px] font-semibold text-ink">Format du fichier CSV</span>
        </div>
        <div class="p-5 flex flex-col gap-3">
            <p class="text-[12.5px] text-ink-muted leading-relaxed">
                Délimiteur <strong class="text-ink-light">point-virgule (;)</strong>, encodage UTF-8, avec une ligne d'en-tête.
                Colonnes acceptées :
            </p>
            <div class="overflow-x-auto rounded-lg border border-surface-border">
                <table class="w-full text-[12px] border-collapse">
                    <thead>
                        <tr>
                            @foreach(['Colonne', 'Obligatoire', 'Format'] as $col)
                                <th class="text-left px-3 py-2 font-bold text-ink-muted uppercase tracking-wide bg-surface-2 border-b border-surface-3">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="text-ink">
                        <tr class="border-b border-surface-3">
                            <td class="px-3 py-2 font-mono">nom</td>
                            <td class="px-3 py-2">Oui</td>
                            <td class="px-3 py-2">Texte, 150 caractères max</td>
                        </tr>
                        <tr class="border-b border-surface-3">
                            <td class="px-3 py-2 font-mono">date_debut</td>
                            <td class="px-3 py-2">Oui</td>
                            <td class="px-3 py-2">AAAA-MM-JJ</td>
                        </tr>
                        <tr class="border-b border-surface-3">
                            <td class="px-3 py-2 font-mono">date_fin</td>
                            <td class="px-3 py-2">Oui</td>
                            <td class="px-3 py-2">AAAA-MM-JJ, ≥ date_debut</td>
                        </tr>
                        <tr class="border-b border-surface-3">
                            <td class="px-3 py-2 font-mono">description</td>
                            <td class="px-3 py-2">Non</td>
                            <td class="px-3 py-2">Texte libre</td>
                        </tr>
                        <tr class="border-b border-surface-3">
                            <td class="px-3 py-2 font-mono">couleur</td>
                            <td class="px-3 py-2">Non</td>
                            <td class="px-3 py-2">1 à 11, ou nom (ex. Tomate, Basilic…)</td>
                        </tr>
                        <tr class="border-b border-surface-3">
                            <td class="px-3 py-2 font-mono">taches</td>
                            <td class="px-3 py-2">Non</td>
                            <td class="px-3 py-2">Codes séparés par « | » (ex. entree|mektaba)</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-mono">calendriers</td>
                            <td class="px-3 py-2">Non</td>
                            <td class="px-3 py-2">Noms de calendriers séparés par « | »</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <a href="{{ route('evenements.import.template') }}"
               class="inline-flex items-center gap-2 self-start px-4 py-2 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[12.5px] font-semibold rounded-lg transition-colors no-underline min-h-[40px]">
                ⬇️ Télécharger le modèle CSV
            </a>

            <details class="group border border-surface-border rounded-lg overflow-hidden">
                <summary class="flex items-center justify-between gap-2 px-3.5 py-2.5 bg-surface-2 cursor-pointer select-none text-[12.5px] font-semibold text-ink hover:bg-surface-3 transition-colors">
                    <span class="flex items-center gap-2"><span>👁️</span> Voir un exemple de fichier CSV</span>
                    <span class="text-ink-muted text-[11px] transition-transform group-open:rotate-180">▼</span>
                </summary>
                <div class="p-3.5 bg-ink-900 overflow-x-auto">
                    <pre class="text-[11.5px] font-mono leading-relaxed text-emerald-300 whitespace-pre"><code>nom;date_debut;date_fin;description;couleur;taches;calendriers
Ramadan;2026-03-01;2026-03-30;Fermeture pendant le mois sacré;Tomate;entree|mektaba;Calendrier Général
Journée portes ouvertes;2026-05-16;2026-05-16;;Basilic;;Calendrier Général
Formation bénévoles;2026-06-06;2026-06-06;Session obligatoire pour les nouveaux bénévoles;;cours;</code></pre>
                </div>
                <p class="px-3.5 py-2.5 text-[11.5px] text-ink-muted leading-relaxed bg-surface border-t border-surface-3">
                    La 2<sup>e</sup> ligne bloque les tâches <span class="font-mono">entree</span> et <span class="font-mono">mektaba</span> et se synchronise sur le calendrier « Calendrier Général ».
                    La 3<sup>e</sup> ligne est purement informative (aucune tâche bloquée, aucun calendrier). La 4<sup>e</sup> bloque <span class="font-mono">cours</span> sans se synchroniser sur un calendrier.
                </p>
            </details>

            <p class="text-[11.5px] text-ink-muted leading-relaxed">
                <strong class="text-amber-600">Import tout ou rien :</strong> si une seule ligne est invalide,
                aucun événement n'est créé. Corrigez les lignes signalées ci-dessous puis réessayez.
            </p>
        </div>
    </div>

    {{-- ── Erreurs de validation --}}
    @if(session('import_errors'))
        <div class="bg-surface rounded-xl border border-rose-200 shadow-sm overflow-hidden">
            <div class="flex items-center gap-2.5 px-5 py-4 border-b border-rose-200 bg-rose-50">
                <span class="text-sm">🚫</span>
                <span class="font-heading text-[14px] font-semibold text-rose-800">
                    {{ count(session('import_errors')) }} ligne{{ count(session('import_errors')) > 1 ? 's' : '' }} invalide{{ count(session('import_errors')) > 1 ? 's' : '' }}
                </span>
            </div>
            <div class="max-h-[320px] overflow-y-auto">
                <table class="w-full text-[12.5px] border-collapse">
                    <tbody>
                        @foreach(session('import_errors') as $err)
                            <tr class="border-b border-surface-3 last:border-0">
                                <td class="px-4 py-2.5 font-mono text-ink-muted whitespace-nowrap align-top">Ligne {{ $err['ligne'] }}</td>
                                <td class="px-4 py-2.5 text-rose-700">
                                    @foreach($err['erreurs'] as $e)
                                        <div>{{ $e }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ── Formulaire d'upload --}}
    <div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-amber-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">📥</div>
            <span class="font-heading text-[14px] font-semibold text-ink">Fichier à importer</span>
        </div>
        <form action="{{ route('evenements.import.store') }}" method="POST" enctype="multipart/form-data" class="p-5 flex flex-col gap-4">
            @csrf

            <div class="flex flex-col gap-1.5">
                <label for="csv" class="text-xs font-bold text-ink tracking-[0.2px]">
                    Fichier CSV <span class="text-rose-500">*</span>
                </label>
                <input type="file" id="csv" name="csv" accept=".csv,text/csv" required
                       class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-[13px] font-body text-ink bg-surface-2 outline-none transition
                              focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
                @error('csv')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
            </div>

            <div class="flex flex-wrap gap-3 items-center">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-6 py-3 bg-accent hover:bg-accent-dark text-white font-bold text-[13.5px] rounded-lg
                               shadow-[0_3px_14px_rgba(3,105,161,0.35)] hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer min-h-[48px]">
                    📥 Importer
                </button>
                <a href="{{ route('evenements.index') }}"
                   class="inline-flex items-center gap-2 px-6 py-3 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink font-semibold text-[13.5px] rounded-lg transition-colors no-underline min-h-[48px]">
                    Annuler
                </a>
            </div>
        </form>
    </div>

    {{-- ── Séparateur --}}
    <div class="flex items-center gap-3 py-1">
        <div class="flex-1 h-px bg-surface-border"></div>
        <span class="text-[11px] font-bold text-ink-muted tracking-[0.5px]">OU</span>
        <div class="flex-1 h-px bg-surface-border"></div>
    </div>

    {{-- ── Saisie manuelle multi-lignes --}}
    <div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-emerald-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">✍️</div>
            <span class="font-heading text-[14px] font-semibold text-ink">Saisie manuelle</span>
        </div>
        <form action="{{ route('evenements.import.manuel') }}" method="POST" class="p-5 flex flex-col gap-4">
            @csrf

            <p class="text-[12.5px] text-ink-muted leading-relaxed -mt-1">
                Deux lignes par défaut — ajoutez-en autant que nécessaire avec « ➕ Ajouter un événement ».
                <strong class="text-amber-600">Import tout ou rien :</strong> si une seule ligne est invalide,
                aucun événement n'est créé.
            </p>

            @error('rows')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror

            @php
                $oldRows = old('rows', []);
                $errorMessages = collect($errors->messages())->mapWithKeys(fn($msgs, $key) => [$key => $msgs[0]])->all();
            @endphp

            {{--
                Point de montage BulkEvenementImport.vue — voir resources/js/app.ts.
                Chaque champ de chaque ligne porte son propre name="rows[N][...]"
                (généré côté Vue), donc ce <form> reste une soumission HTML
                classique : pas d'appel AJAX, la réhydratation après erreur de
                validation (old('rows') / $errors) se fait via les data-attributes
                ci-dessous, exactement comme le reste des formulaires de l'app.
            --}}
            <div
                data-bulk-evenement-import
                data-taches="{{ json_encode($taches->map(fn($t) => ['id' => $t->id, 'code' => $t->code, 'libelle' => $t->libelle])) }}"
                data-couleurs="{{ json_encode($couleurs) }}"
                data-calendars-api-url="{{ route('calendriers.index') }}"
                data-old-rows="{{ json_encode($oldRows) }}"
                data-errors="{{ json_encode($errorMessages) }}"
            ></div>

            <div class="flex flex-wrap gap-3 items-center pt-1">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-6 py-3 bg-accent hover:bg-accent-dark text-white font-bold text-[13.5px] rounded-lg
                               shadow-[0_3px_14px_rgba(3,105,161,0.35)] hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer min-h-[48px]">
                    📥 Importer ces événements
                </button>
                <a href="{{ route('evenements.index') }}"
                   class="inline-flex items-center gap-2 px-6 py-3 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink font-semibold text-[13.5px] rounded-lg transition-colors no-underline min-h-[48px]">
                    Annuler
                </a>
            </div>
        </form>
    </div>

</div>

@endsection
