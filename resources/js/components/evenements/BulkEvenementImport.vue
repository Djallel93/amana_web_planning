<!-- resources/js/components/evenements/BulkEvenementImport.vue -->
<!--
    Section "Saisie manuelle" de evenements/import.blade.php — alternative à
    l'upload CSV pour importer plusieurs événements en une fois.

    Même philosophie que SearchableSelect : ce composant Vue gère l'état
    interactif (ajout/suppression de lignes, cases à cocher, sélecteur de
    calendriers) mais le <form> qui l'englobe reste un <form> HTML natif
    Blade — chaque champ porte un attribut `name="rows[N][...]"` généré
    dynamiquement, donc la soumission (POST classique, rechargement de page)
    et la réhydratation après une erreur de validation (old()/@error()) se
    comportent exactement comme le reste de l'app, sans appel AJAX ni
    logique de soumission JS.

    "Tout ou rien" identique à l'import CSV : ImportEvenementsManuelRequest
    valide TOUTES les lignes d'un coup côté serveur avant que le contrôleur
    ne crée quoi que ce soit — aucune création partielle possible.
-->
<script setup lang="ts">
import { ref } from "vue";
import SearchableSelect from "@/components/shared/SearchableSelect.vue";

interface Tache {
    id: number;
    code: string;
    libelle: string;
}

interface Couleur {
    id: string;
    nom: string;
}

interface Row {
    nom: string;
    date_debut: string;
    date_fin: string;
    description: string;
    couleur: string;
    taches: number[];
    calendar_ids: string[];
}

const props = withDefaults(
    defineProps<{
        taches: Tache[];
        couleurs: Couleur[];
        calendarsApiUrl: string;
        oldRows?: Partial<Row>[];
        errors?: Record<string, string>;
    }>(),
    {
        oldRows: () => [],
        errors: () => ({}),
    },
);

function emptyRow(): Row {
    return {
        nom: "",
        date_debut: "",
        date_fin: "",
        description: "",
        couleur: "",
        taches: [],
        calendar_ids: [],
    };
}

function hydrateRow(source: Partial<Row> | undefined): Row {
    if (!source) return emptyRow();
    return {
        nom: source.nom ?? "",
        date_debut: source.date_debut ?? "",
        date_fin: source.date_fin ?? "",
        description: source.description ?? "",
        couleur: source.couleur ?? "",
        taches: Array.isArray(source.taches) ? source.taches.map(Number) : [],
        calendar_ids: Array.isArray(source.calendar_ids) ? source.calendar_ids : [],
    };
}

// Réhydratation après une erreur de validation (old('rows')) — sinon 2
// lignes vides par défaut.
const rows = ref<Row[]>(
    props.oldRows.length > 0 ? props.oldRows.map(hydrateRow) : [emptyRow(), emptyRow()],
);

function addRow(): void {
    rows.value.push(emptyRow());
}

function removeRow(index: number): void {
    if (rows.value.length <= 1) return; // toujours garder au moins une ligne
    rows.value.splice(index, 1);
}

function errorFor(index: number, field: string): string | null {
    return props.errors[`rows.${index}.${field}`] ?? null;
}

function toggleTache(row: Row, tacheId: number): void {
    const i = row.taches.indexOf(tacheId);
    if (i === -1) row.taches.push(tacheId);
    else row.taches.splice(i, 1);
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            v-for="(row, index) in rows"
            :key="index"
            class="bg-surface-2 rounded-xl border border-surface-border overflow-hidden"
        >
            <div class="flex items-center justify-between gap-2.5 px-4 py-3 border-b border-surface-3 bg-surface-3">
                <span class="font-heading text-[12.5px] font-semibold text-ink">Événement {{ index + 1 }}</span>
                <button
                    type="button"
                    @click="removeRow(index)"
                    :disabled="rows.length <= 1"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11.5px] font-semibold rounded-md transition-colors cursor-pointer
                           border border-rose-200 text-rose-600 bg-transparent hover:bg-rose-50
                           disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent"
                >
                    🗑️ Supprimer
                </button>
            </div>

            <div class="p-4 flex flex-col gap-3.5">
                <!-- Nom -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-ink tracking-[0.2px]">
                        Nom <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        :name="`rows[${index}][nom]`"
                        v-model="row.nom"
                        maxlength="150"
                        required
                        placeholder="Ex : Vacances Noël, Ramadan…"
                        class="w-full px-3.5 py-2.5 border-[1.5px] rounded-lg text-[13.5px] font-body text-ink bg-surface outline-none transition
                               focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                        :class="errorFor(index, 'nom') ? 'border-rose-400' : 'border-ink-faint'"
                    >
                    <span v-if="errorFor(index, 'nom')" class="text-xs text-rose-600">{{ errorFor(index, "nom") }}</span>
                </div>

                <!-- Dates -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-ink tracking-[0.2px]">
                            Date de début <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="date"
                            :name="`rows[${index}][date_debut]`"
                            v-model="row.date_debut"
                            required
                            class="w-full px-3.5 py-2.5 border-[1.5px] rounded-lg text-[13.5px] font-body text-ink bg-surface outline-none transition
                                   focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                            :class="errorFor(index, 'date_debut') ? 'border-rose-400' : 'border-ink-faint'"
                        >
                        <span v-if="errorFor(index, 'date_debut')" class="text-xs text-rose-600">{{ errorFor(index, "date_debut") }}</span>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-ink tracking-[0.2px]">
                            Date de fin <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="date"
                            :name="`rows[${index}][date_fin]`"
                            v-model="row.date_fin"
                            :min="row.date_debut || undefined"
                            required
                            class="w-full px-3.5 py-2.5 border-[1.5px] rounded-lg text-[13.5px] font-body text-ink bg-surface outline-none transition
                                   focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                            :class="errorFor(index, 'date_fin') ? 'border-rose-400' : 'border-ink-faint'"
                        >
                        <span v-if="errorFor(index, 'date_fin')" class="text-xs text-rose-600">{{ errorFor(index, "date_fin") }}</span>
                    </div>
                </div>

                <!-- Description -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-ink tracking-[0.2px]">
                        Description <span class="text-ink-muted font-normal">(optionnel)</span>
                    </label>
                    <textarea
                        :name="`rows[${index}][description]`"
                        v-model="row.description"
                        rows="2"
                        placeholder="Notes complémentaires…"
                        class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-[13.5px] font-body text-ink bg-surface outline-none transition resize-y
                               focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                    ></textarea>
                </div>

                <!-- Couleur -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-ink tracking-[0.2px]">
                        Couleur Google Calendar <span class="text-ink-muted font-normal">(optionnel)</span>
                    </label>
                    <select
                        :name="`rows[${index}][couleur]`"
                        v-model="row.couleur"
                        class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-[13.5px] font-body text-ink bg-surface outline-none transition cursor-pointer
                               focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                    >
                        <option value="">Couleur par défaut du calendrier</option>
                        <option v-for="c in couleurs" :key="c.id" :value="c.id">{{ c.nom }}</option>
                    </select>
                </div>

                <!-- Tâches bloquées -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-ink tracking-[0.2px]">
                        Tâches bloquées <span class="text-ink-muted font-normal">(optionnel — vide = informatif)</span>
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="t in taches"
                            :key="t.id"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-semibold cursor-pointer border transition-colors select-none"
                            :class="row.taches.includes(t.id)
                                ? 'bg-rose-50 border-rose-300 text-rose-700'
                                : 'bg-surface border-surface-border text-ink-muted hover:border-ink-faint'"
                        >
                            <input
                                type="checkbox"
                                class="hidden"
                                :name="`rows[${index}][taches][]`"
                                :value="t.id"
                                :checked="row.taches.includes(t.id)"
                                @change="toggleTache(row, t.id)"
                            >
                            {{ t.libelle }}
                        </label>
                    </div>
                </div>

                <!-- Calendriers -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-ink tracking-[0.2px]">
                        Calendriers Google Calendar <span class="text-ink-muted font-normal">(optionnel)</span>
                    </label>
                    <SearchableSelect
                        :api-url="calendarsApiUrl"
                        v-model="row.calendar_ids"
                        multiple
                        :input-name="`rows[${index}][calendar_ids]`"
                        placeholder="Sélectionner un ou plusieurs calendriers…"
                    />
                </div>
            </div>
        </div>

        <button
            type="button"
            @click="addRow"
            class="inline-flex items-center gap-2 self-start px-4 py-2.5 border-[1.5px] border-dashed border-accent text-accent hover:bg-sky-50
                   text-[13px] font-semibold rounded-lg transition-colors cursor-pointer min-h-[44px] bg-transparent"
        >
            ➕ Ajouter un événement
        </button>
    </div>
</template>
