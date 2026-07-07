<!-- resources/js/components/planning/PlanningGrid.vue -->
<!--
    Composant racine de la page Planning — remplace entièrement le contenu
    dynamique de planning/index.blade.php (filtres + #planningContainer +
    les deux modals), en consommant GET /planning/data au lieu du rendu
    Blade côté serveur.

    ── Ce qui reste en Blade ──────────────────────────────────────────────
    Le header de page, le bouton "Générer", le message "aucun planning"
    restent dans planning/index.blade.php — seule la grille + filtres +
    modals sont désormais pilotés par ce composant Vue.

    ── Stratégie de chargement ────────────────────────────────────────────
    Au montage : fetch GET /planning/data → état réactif `semaines`.
    Toute mutation (assignation, suppression, création) met à jour l'état
    LOCAL directement (pas de re-fetch complet) pour rester réactif — sauf
    la création de créneau qui déclenche un re-fetch car elle change la
    structure d'une semaine entière (nouvelle ligne, recalcul datesExistantes).
-->
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import AssignModal from '@/components/planning/AssignModal.vue';
import AddCreneauModal from '@/components/planning/AddCreneauModal.vue';
import AnnulationCoursModal from '@/components/planning/AnnulationCoursModal.vue';
import SkeletonPlanningGrid from '@/components/shared/SkeletonPlanningGrid.vue';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import type {
    PlanningResponse, SemaineData, CreneauData, PersonneAssignee, AssignContext, AddCreneauContext,
} from '@/types/planning';
import { TACHES_META, TACHE_CODES } from '@/types/planning';

const toast = useToast();
const { ask } = useConfirm();

// ── État principal ──────────────────────────────────────────────────────
const semaines    = ref<SemaineData[]>([]);
const historique  = ref(false);
const peutEditer  = ref(false);
const loading     = ref(true);
const loadError   = ref(false);

// ── Refs vers les modals enfants (pour appeler .open() depuis ici) ───────
// useTemplateRef('assign-modal') correspond à ref="assign-modal" dans le template.
// Le type générique précise quelle interface expose le composant enfant —
// ici, ce qu'on a déclaré via defineExpose({ open }) dans AssignModal.vue.
import { useTemplateRef } from 'vue';
const assignModalRef     = useTemplateRef<InstanceType<typeof AssignModal>>('assign-modal');
const addCreneauModalRef = useTemplateRef<InstanceType<typeof AddCreneauModal>>('add-creneau-modal');
const annulationCoursModalRef = useTemplateRef<InstanceType<typeof AnnulationCoursModal>>('annulation-cours-modal');

// ── Filtres (années / mois) ───────────────────────────────────────────────
// Set<number> réactif : on utilise ref(new Set()) plutôt qu'un tableau parce
// que has()/add()/delete() sont plus directs pour ce cas d'usage que des
// recherches répétées dans un array avec indexOf/includes.
const activeYears  = ref<Set<number>>(new Set());
const activeMonths = ref<Set<number>>(new Set());

// ── Données dérivées pour la barre de filtres ─────────────────────────────
const allYears = computed((): number[] => {
    const years = new Set(semaines.value.map(s => s.anneeAffichage));
    return Array.from(years).sort((a, b) => b - a); // tri descendant
});

const allMonths = computed((): { num: number; label: string }[] => {
    const months = new Map<number, string>();
    const fmt = new Intl.DateTimeFormat('fr-FR', { month: 'long' });
    semaines.value.forEach(s => {
        if (!months.has(s.moisAffichage)) {
            // mois 1-12 → Date arbitraire dans ce mois pour formater le nom
            const label = fmt.format(new Date(2024, s.moisAffichage - 1, 1));
            months.set(s.moisAffichage, label.charAt(0).toUpperCase() + label.slice(1));
        }
    });
    return Array.from(months.entries())
        .sort(([a], [b]) => a - b)
        .map(([num, label]) => ({ num, label }));
});

// ── Semaines visibles après filtres ────────────────────────────────────────
const semainesVisibles = computed((): SemaineData[] => {
    return semaines.value.filter(s => {
        const yearOk  = activeYears.value.size === 0  || activeYears.value.has(s.anneeAffichage);
        const monthOk = activeMonths.value.size === 0 || activeMonths.value.has(s.moisAffichage);
        return yearOk && monthOk;
    });
});

const resultsCountLabel = computed((): string => {
    if (activeYears.value.size === 0 && activeMonths.value.size === 0) return '';
    const n = semainesVisibles.value.length;
    return `${n} semaine${n !== 1 ? 's' : ''} affichée${n !== 1 ? 's' : ''}`;
});

function toggleYearFilter(year: number): void {
    activeYears.value.has(year) ? activeYears.value.delete(year) : activeYears.value.add(year);
    // Vue ne détecte pas les mutations internes d'un Set par défaut pour le
    // déclenchement de réactivité dans tous les cas — on réassigne une copie
    // pour garantir la mise à jour du computed.
    activeYears.value = new Set(activeYears.value);
}

function toggleMonthFilter(month: number): void {
    activeMonths.value.has(month) ? activeMonths.value.delete(month) : activeMonths.value.add(month);
    activeMonths.value = new Set(activeMonths.value);
}

function clearFilters(): void {
    activeYears.value = new Set();
    activeMonths.value = new Set();
}

// ── Chargement initial + activation des filtres par défaut ───────────────
async function loadData(): Promise<void> {
    loading.value = true;
    loadError.value = false;
    try {
        const url = `${window.PlanningConfig.routes.data}${historique.value ? '?historique=1' : ''}`;
        const res = await fetch(url, {
            headers: { 'X-CSRF-TOKEN': window.PlanningConfig.csrf, 'Accept': 'application/json' },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json() as PlanningResponse;

        semaines.value   = data.semaines;
        historique.value = data.historique;
        peutEditer.value = data.peutEditer;
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

function applyDefaultFilters(): void {
    const now = new Date();
    const currentMonth = now.getMonth() + 1;
    const currentYear  = now.getFullYear();
    const previousMonth = currentMonth === 1  ? 12 : currentMonth - 1;
    const nextMonth     = currentMonth === 12 ? 1  : currentMonth + 1;
    const previousMonthYear = currentMonth === 1  ? currentYear - 1 : currentYear;
    const nextMonthYear     = currentMonth === 12 ? currentYear + 1 : currentYear;

    activeYears.value  = new Set([currentYear]);
    activeMonths.value = new Set([previousMonth, currentMonth, nextMonth]);
    if (previousMonthYear !== currentYear) activeYears.value.add(previousMonthYear);
    if (nextMonthYear !== currentYear)     activeYears.value.add(nextMonthYear);
    activeYears.value = new Set(activeYears.value);
}

onMounted(async () => {
    await loadData();
    applyDefaultFilters();
});

// ── Helpers de lookup dans l'état local ───────────────────────────────────
function findCreneau(creneauId: number): CreneauData | undefined {
    for (const semaine of semaines.value) {
        const found = semaine.creneaux.find(c => c.id === creneauId);
        if (found) return found;
    }
    return undefined;
}

// ── Ouverture du modal d'assignation depuis une cellule ───────────────────
function openAssign(creneau: CreneauData, tacheCode: typeof TACHE_CODES[number]): void {
    if (!peutEditer.value) return;
    const tache = creneau.taches.find(t => t.code === tacheCode);
    // tache.tacheId est null si la ligne CreneauTache n'existe pas encore en base
    // (tâche jamais configurée pour ce créneau). Le PATCH /tache/null échouerait
    // en 404 — on bloque l'ouverture plutôt que de laisser passer un appel invalide.
    if (!tache || tache.bloquee || tache.tacheId === null) return;

    const ctx: AssignContext = {
        creneauId: creneau.id,
        tacheId: tache.tacheId,
        tacheCode,
        tacheLabel: TACHES_META[tacheCode].label.replace(/^\S+\s/, ''), // retire l'emoji pour le titre modal
        jour: creneau.jour,
        dateLabel: creneau.dateLabel,
        currentPersonneId: tache.personne?.id ?? null,
    };
    assignModalRef.value?.open(ctx);
}

// ── Mise à jour locale après une assignation réussie ──────────────────────
function onAssigned(creneauId: number, tacheCode: string, personne: PersonneAssignee | null): void {
    const creneau = findCreneau(creneauId);
    const tache = creneau?.taches.find(t => t.code === tacheCode);
    if (tache) tache.personne = personne;
}

function onUnassigned(creneauId: number, tacheCode: string): void {
    onAssigned(creneauId, tacheCode, null);
}

// ── Suppression d'un créneau (depuis le modal ou le bouton 🗑️ de la ligne) ─
async function deleteCreneau(creneauId: number): Promise<void> {
    if (!(await ask({ message: 'Supprimer ce créneau et toutes ses tâches ?', danger: true }))) return;
    await doDeleteCreneau(creneauId);
}

async function doDeleteCreneau(creneauId: number): Promise<void> {
    try {
        const res = await fetch(`${window.PlanningConfig.routes.creneau}/${creneauId}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': window.PlanningConfig.csrf, 'Accept': 'application/json' },
        });
        const data = await res.json() as { success: boolean; message: string };
        if (data.success) {
            removeCreneauLocally(creneauId);
            toast.success(data.message);
        } else {
            toast.error('Erreur');
        }
    } catch {
        toast.error('Erreur réseau');
    }
}

// Appelé par AssignModal quand on supprime depuis la zone dangereuse
function onDeletedFromModal(creneauId: number): void {
    removeCreneauLocally(creneauId);
}

function removeCreneauLocally(creneauId: number): void {
    for (const semaine of semaines.value) {
        const idx = semaine.creneaux.findIndex(c => c.id === creneauId);
        if (idx !== -1) {
            semaine.creneaux.splice(idx, 1);
            break;
        }
    }
    // Retirer les semaines devenues vides (équivalent de checkEmptyWeeks()).
    semaines.value = semaines.value.filter(s => s.creneaux.length > 0);
}

// ── Suppression de toute une semaine ──────────────────────────────────────
async function deleteWeek(semaine: SemaineData): Promise<void> {
    const ids = semaine.creneaux.map(c => c.id);
    if (!(await ask({ message: `Supprimer les ${ids.length} créneaux de cette semaine ?`, danger: true }))) return;

    let n = 0;
    for (const id of ids) {
        try {
            const res = await fetch(`${window.PlanningConfig.routes.creneau}/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': window.PlanningConfig.csrf, 'Accept': 'application/json' },
            });
            const data = await res.json() as { success: boolean };
            if (data.success) n++;
        } catch { /* on continue malgré une erreur isolée, comme l'original */ }
    }
    semaines.value = semaines.value.filter(s => s.cle !== semaine.cle);
    toast.success(`Semaine supprimée (${n} créneaux)`);
}

// ── Ajout d'un créneau ─────────────────────────────────────────────────────
function openAddCreneau(semaine: SemaineData): void {
    const ctx: AddCreneauContext = {
        weekMin: semaine.lundi,
        weekMax: semaine.dimanche,
        existingDates: semaine.datesExistantes,
    };
    addCreneauModalRef.value?.open(ctx);
}

// Un créneau créé change la structure de la semaine (nouvelle ligne,
// nouvelles datesExistantes) — plus simple et plus sûr de recharger
// que de reconstruire l'objet localement.
async function onCreneauCreated(): Promise<void> {
    await loadData();
}

// ── Annulation cours ────────────────────────────────────────────────────
function openAnnulationCours(): void {
    annulationCoursModalRef.value?.open();
}

// Une annulation débloque/rebloque potentiellement plusieurs tâches et fait
// apparaître un nouvel événement bloquant — comme pour la création de
// créneau, on recharge entièrement plutôt que de patcher l'état local.
async function onCoursAnnule(): Promise<void> {
    await loadData();
}

// ── Bascule historique ────────────────────────────────────────────────────
async function toggleHistorique(): Promise<void> {
    historique.value = !historique.value;
    await loadData();
}

</script>

<template>
    <div>
        <!-- Bandeau historique -->
        <div
            v-if="historique"
            class="flex items-center gap-3 px-4 py-3 mb-5 bg-amber-50 border border-amber-200 rounded-lg text-[13px] text-amber-800"
        >
            <span>📚</span>
            <span class="flex-1">Affichage de tout l'historique.</span>
            <button
                @click="toggleHistorique"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[12px] font-semibold border border-amber-300
                       rounded-lg hover:bg-amber-100 transition-colors text-amber-800 min-h-[44px] bg-transparent cursor-pointer"
            >
                ← Vue normale (1 an)
            </button>
        </div>

        <!-- Chargement -->
        <SkeletonPlanningGrid v-if="loading" :semaines="3" :creneaux-par-semaine="2" />

        <!-- Erreur réseau -->
        <div v-else-if="loadError" class="text-center py-16 text-rose-600 text-[13.5px]">
            ❌ Erreur lors du chargement du planning.
            <button @click="loadData" class="ml-2 underline cursor-pointer bg-transparent border-0 text-rose-600">Réessayer</button>
        </div>

        <!-- Aucun planning -->
        <div v-else-if="semaines.length === 0" class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
            <div class="text-center py-16 px-8">
                <div class="text-5xl mb-3 opacity-40">📭</div>
                <h3 class="font-heading text-base font-semibold text-ink mb-1.5">Aucun planning généré</h3>
                <p class="text-ink-muted text-[13.5px] mb-6">
                    <template v-if="!historique">
                        Aucun créneau dans les 12 derniers mois.
                        <button @click="toggleHistorique" class="text-accent font-semibold hover:underline bg-transparent border-0 cursor-pointer p-0">
                            Voir tout l'historique
                        </button>
                    </template>
                    <template v-else>
                        Cliquez sur "Générer" pour créer le premier planning automatique.
                    </template>
                </p>
            </div>
        </div>

        <template v-else>
            <!-- Barre de filtres -->
            <div class="flex flex-wrap items-center gap-2.5 px-4 py-3 mb-5 bg-surface border border-surface-border rounded-xl shadow-sm">
                <span class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.8px]">Filtrer</span>

                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-[9.5px] font-bold text-ink-faint uppercase tracking-[0.8px]">Année</span>
                    <div class="flex gap-1 flex-wrap">
                        <span
                            v-for="year in allYears" :key="year"
                            class="px-2.5 py-1 rounded-md text-[12px] font-semibold cursor-pointer select-none transition-colors border"
                            :class="activeYears.has(year)
                                ? 'bg-accent text-white border-accent'
                                : 'bg-surface-2 text-ink-muted border-surface-border hover:border-accent hover:text-accent'"
                            @click="toggleYearFilter(year)"
                        >{{ year }}</span>
                    </div>
                </div>

                <div class="w-px h-5 bg-surface-border flex-shrink-0"></div>

                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-[9.5px] font-bold text-ink-faint uppercase tracking-[0.8px]">Mois</span>
                    <div class="flex gap-1 flex-wrap">
                        <span
                            v-for="m in allMonths" :key="m.num"
                            class="px-2.5 py-1 rounded-md text-[12px] font-semibold cursor-pointer select-none transition-colors border"
                            :class="activeMonths.has(m.num)
                                ? 'bg-accent text-white border-accent'
                                : 'bg-surface-2 text-ink-muted border-surface-border hover:border-accent hover:text-accent'"
                            @click="toggleMonthFilter(m.num)"
                        >{{ m.label }}</span>
                    </div>
                </div>

                <div class="w-px h-5 bg-surface-border flex-shrink-0"></div>

                <button
                    @click="clearFilters"
                    class="px-2.5 py-1 text-[12px] font-semibold text-ink-muted border border-surface-border rounded-md
                           hover:border-rose-300 hover:text-rose-500 transition-colors bg-transparent cursor-pointer min-h-[44px]"
                >✕ Effacer</button>

                <button
                    v-if="!historique"
                    @click="toggleHistorique"
                    class="px-2.5 py-1 text-[12px] font-semibold text-ink-muted border border-surface-border rounded-md
                           hover:border-accent hover:text-accent transition-colors min-h-[44px] inline-flex items-center
                           whitespace-nowrap bg-transparent cursor-pointer"
                >📚 Historique complet</button>

                <span class="ml-auto text-[11.5px] text-ink-muted italic">{{ resultsCountLabel }}</span>

                <button
                    v-if="peutEditer"
                    @click="openAnnulationCours"
                    class="px-3 py-1.5 text-[12px] font-bold text-white bg-rose-600 hover:bg-rose-700
                           rounded-md transition-colors min-h-[44px] inline-flex items-center gap-1.5
                           whitespace-nowrap border-0 cursor-pointer shadow-[0_2px_8px_rgba(225,29,72,0.25)]"
                >🚫 Annulation cours</button>
            </div>

            <!-- Blocs semaine -->
            <div v-for="semaine in semainesVisibles" :key="semaine.cle"
                 class="week-block mb-4 bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">

                <!-- Header semaine -->
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-surface-3 bg-surface-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-heading text-[13px] font-semibold text-ink flex items-center gap-1.5">
                            📅 <span class="text-accent font-bold">S{{ semaine.numeroSemaine }}</span>
                            {{ semaine.libelleSemaine }}
                        </span>
                        <span
                            v-if="semaine.evenementBloquantTotal"
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold
                                   bg-rose-500/20 border border-rose-500/40 text-rose-300"
                        >🚫 {{ semaine.evenementBloquantTotal }}</span>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[11.5px] text-ink-muted">
                            {{ semaine.creneaux.length }} créneau{{ semaine.creneaux.length > 1 ? 'x' : '' }}
                        </span>
                        <template v-if="peutEditer">
                            <button
                                @click="openAddCreneau(semaine)"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11.5px] font-semibold rounded-lg
                                       cursor-pointer transition-colors min-h-[44px] bg-sky-500/20 border border-sky-500/50
                                       text-sky-700 hover:bg-sky-500/30"
                            >➕ Créneau</button>
                            <button
                                @click="deleteWeek(semaine)"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11.5px] font-semibold rounded-lg
                                       cursor-pointer transition-colors min-h-[44px] bg-rose-500/15 border border-rose-500/40
                                       text-rose-600 hover:bg-rose-500/25"
                            >🗑️ Semaine</button>
                        </template>
                    </div>
                </div>

                <!-- Bannières événements -->
                <template v-for="(b, i) in semaine.bannieres" :key="i">
                    <div v-if="b.informatif" class="flex items-center gap-2 px-4 py-2 bg-sky-50 border-b border-sky-100 text-[12.5px] text-sky-800">
                        <span>📅</span><span class="font-bold">{{ b.nom }}</span><span class="opacity-70">— {{ b.dateLabel }}</span>
                    </div>
                    <div v-else class="flex flex-wrap items-center gap-2 px-4 py-2 bg-rose-50 border-b border-rose-100 text-[12.5px] text-rose-800">
                        <span>🚫</span><span class="font-bold">{{ b.nom }}</span><span class="opacity-70">— {{ b.dateLabel }}</span>
                        <div class="flex flex-wrap gap-1 ml-1">
                            <span v-for="tb in b.tachesBloquees" :key="tb.code"
                                  class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold"
                                  :class="`chip-${tb.code}`">
                                {{ tb.libelle }}
                            </span>
                        </div>
                    </div>
                </template>

                <!-- Table desktop -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full border-collapse text-[13px]" style="min-width:680px;">
                        <thead>
                            <tr>
                                <th class="text-left px-4 py-2.5 text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] bg-surface-2 border-b border-surface-3 whitespace-nowrap font-body w-36">Jour</th>
                                <th v-for="code in TACHE_CODES" :key="code"
                                    class="text-left px-3 py-2.5 text-[11px] font-bold bg-surface-2 border-b border-surface-3 whitespace-nowrap font-body"
                                    :class="TACHES_META[code].colorClass">
                                    {{ TACHES_META[code].label }}
                                </th>
                                <th class="text-left px-3 py-2.5 text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] bg-surface-2 border-b border-surface-3 font-body">Événements</th>
                                <th class="w-9 bg-surface-2 border-b border-surface-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="creneau in semaine.creneaux" :key="creneau.id"
                                class="border-b border-surface-3 last:border-0 group transition-colors"
                                :class="creneau.toutBloque ? 'bg-orange-50' : 'hover:bg-surface-2'">

                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <strong class="font-heading text-[13px] text-ink">{{ creneau.jour }}</strong>
                                        <span class="text-ink-muted text-[11.5px]">{{ creneau.dateLabel }}</span>
                                        <span v-if="creneau.toutBloque" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">● Bloqué</span>
                                        <span v-else-if="creneau.partielBloque" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">● Partiel</span>
                                    </div>
                                </td>

                                <td v-for="tache in creneau.taches" :key="tache.code"
                                    class="px-2 py-2 relative" :class="tache.bloquee ? 'bg-orange-50' : ''">
                                    <div v-if="tache.bloquee" class="flex items-center gap-1 px-2 py-1 rounded-md cursor-default">
                                        <span class="text-orange-500 text-xs font-semibold" :title="tache.evenementBloquant ?? ''">
                                            🚫 {{ (tache.evenementBloquant ?? '').slice(0, 18) }}
                                        </span>
                                    </div>
                                    <div v-else
                                         class="flex items-center gap-1.5 px-2 py-1 rounded-md transition-colors group/cell"
                                         :class="peutEditer ? 'cursor-pointer hover:bg-surface-3' : 'cursor-default'"
                                         @click="peutEditer && openAssign(creneau, tache.code)">
                                        <span v-if="tache.personne" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold" :class="`chip-${tache.code}`">
                                            {{ tache.personne.label }}
                                        </span>
                                        <span v-else class="text-ink-faint italic text-xs">—</span>
                                        <span v-if="peutEditer" class="opacity-0 group-hover/cell:opacity-100 transition-opacity text-[11px] text-ink-faint flex-shrink-0">✏️</span>
                                    </div>
                                </td>

                                <td class="px-3 py-2.5">
                                    <span v-if="creneau.evenements" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium" :class="creneau.toutBloque ? 'bg-rose-100 text-rose-700' : 'bg-surface-3 text-ink-muted'">
                                        {{ creneau.evenements }}
                                    </span>
                                    <span v-else class="text-ink-faint text-xs">—</span>
                                </td>

                                <td class="pr-3 text-right">
                                    <button v-if="peutEditer" @click="deleteCreneau(creneau.id)"
                                            class="opacity-0 group-hover:opacity-100 transition-opacity w-7 h-7 rounded-md bg-transparent
                                                   border border-transparent hover:bg-rose-50 hover:border-rose-200 text-sm cursor-pointer
                                                   flex items-center justify-center min-h-[44px] min-w-[44px]">🗑️</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Cartes mobile -->
                <div class="md:hidden divide-y divide-surface-3">
                    <div v-for="creneau in semaine.creneaux" :key="creneau.id"
                         class="px-4 py-3" :class="creneau.toutBloque ? 'bg-orange-50' : ''">

                        <div class="flex items-center justify-between mb-2.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <strong class="font-heading text-[13.5px] text-ink">{{ creneau.jour }}</strong>
                                <span class="text-ink-muted text-[12px]">{{ creneau.dateLabel }}</span>
                                <span v-if="creneau.toutBloque" class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">● Bloqué</span>
                                <span v-else-if="creneau.partielBloque" class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">● Partiel</span>
                            </div>
                            <button v-if="peutEditer" @click="deleteCreneau(creneau.id)"
                                    class="w-9 h-9 rounded-md border border-rose-200 bg-rose-50 hover:bg-rose-100 text-sm cursor-pointer
                                           flex items-center justify-center min-h-[44px] min-w-[44px]">🗑️</button>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div v-for="tache in creneau.taches" :key="tache.code"
                                 class="rounded-lg p-2.5 transition-colors"
                                 :class="[
                                     tache.bloquee ? 'bg-orange-50' : 'bg-surface-2',
                                     peutEditer && !tache.bloquee ? 'cursor-pointer hover:bg-surface-3 active:bg-surface-border' : '',
                                 ]"
                                 @click="peutEditer && !tache.bloquee && openAssign(creneau, tache.code)">
                                <div class="text-[10px] font-bold text-ink-muted mb-1">{{ TACHES_META[tache.code].label }}</div>
                                <span v-if="tache.bloquee" class="text-orange-500 text-xs font-semibold">
                                    🚫 {{ (tache.evenementBloquant ?? '').slice(0, 14) }}
                                </span>
                                <span v-else-if="tache.personne" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold" :class="`chip-${tache.code}`">
                                    {{ tache.personne.label.split(' ')[0] }} {{ tache.personne.label.split(' ')[1]?.slice(0, 8) }}
                                </span>
                                <span v-else class="text-ink-faint italic text-xs">—</span>
                            </div>
                        </div>

                        <div v-if="creneau.evenements" class="mt-2 text-[11.5px] text-ink-muted">📅 {{ creneau.evenements }}</div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Modals -->
        <AssignModal
            v-if="peutEditer"
            ref="assign-modal"
            @assigned="onAssigned"
            @unassigned="onUnassigned"
            @deleted="onDeletedFromModal"
        />
        <AddCreneauModal
            v-if="peutEditer"
            ref="add-creneau-modal"
            @created="onCreneauCreated"
        />
        <AnnulationCoursModal
            v-if="peutEditer"
            ref="annulation-cours-modal"
            @cancelled="onCoursAnnule"
        />
    </div>
</template>
