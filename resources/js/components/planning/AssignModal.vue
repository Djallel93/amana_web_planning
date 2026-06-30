<!-- resources/js/components/planning/AssignModal.vue -->
<!--
    Modal de réassignation d'une tâche — remplace editModalBackdrop/editModal
    de _edit-modal.blade.php et openEditModal/saveAssignation/unassignTask/
    deleteCreneauFromModal de planning-index.js.

    ── Communication avec PlanningGrid.vue ───────────────────────────────────
    Ce composant est "dumb" : il ne sait pas comment patcher le DOM ou
    recharger les données. Il se contente d'émettre des events ; c'est
    PlanningGrid.vue (le parent) qui les écoute et met à jour son état local.
    Pattern : "controlled component", déjà vu sur Modal.vue et SwapRequestModal.

    Exposé : une fonction open(context) appelée par le parent via une ref
    (defineExpose), plutôt qu'un useModal() interne — parce que PlanningGrid
    a besoin de connaître l'état isOpen pour d'autres raisons (désactiver des
    clics pendant que le modal est ouvert, etc.) Pattern différent de
    SwapRequestModal qui gérait tout en interne.
-->
<script setup lang="ts">
import { ref, computed } from 'vue';
import Modal from '@/components/shared/Modal.vue';
import { useToast } from '@/composables/useToast';
import type { AssignContext, PersonneAssignee } from '@/types/planning';
import { TACHES_META } from '@/types/planning';

// ── Emits ─────────────────────────────────────────────────────────────────
// "assigned"  : la sauvegarde a réussi → parent met à jour sa cellule locale
// "unassigned": désassignation réussie → idem
// "deleted"   : créneau supprimé depuis le modal → parent retire la ligne
const emit = defineEmits<{
    assigned:   [creneauId: number, tacheCode: string, personne: PersonneAssignee | null];
    unassigned: [creneauId: number, tacheCode: string];
    deleted:    [creneauId: number];
}>();

const toast = useToast();

// ── État ──────────────────────────────────────────────────────────────────
const isOpen = ref(false);
const context = ref<AssignContext | null>(null);
const personnes = ref<PersonneAssignee[]>([]);
const selectedPersonneId = ref<string>(''); // string car lié à un <select>, "" = désassigner
const saving = ref(false);

// Cache module-level — une seule requête /personnes-actives pour toute la session,
// partagée même si le modal est fermé/réouvert plusieurs fois.
let personnesCache: PersonneAssignee[] | null = null;

// ── Métadonnées d'icône/couleur par tâche (même table que _week-block) ────
const tacheMeta = computed(() => {
    if (!context.value) return null;
    return TACHES_META[context.value.tacheCode];
});

// ── Chargement des personnes ───────────────────────────────────────────────
async function loadPersonnes(): Promise<PersonneAssignee[]> {
    if (personnesCache) return personnesCache;
    const res = await fetch(window.PlanningConfig.routes.personnes, {
        headers: { 'X-CSRF-TOKEN': window.PlanningConfig.csrf, 'Accept': 'application/json' },
    });
    personnesCache = await res.json() as PersonneAssignee[];
    return personnesCache;
}

// ── Ouverture ─────────────────────────────────────────────────────────────
async function open(ctx: AssignContext): Promise<void> {
    context.value = ctx;
    selectedPersonneId.value = ctx.currentPersonneId ? String(ctx.currentPersonneId) : '';
    isOpen.value = true;
    personnes.value = await loadPersonnes();
}

function close(): void {
    isOpen.value = false;
    context.value = null;
}

// ── Sauvegarde ────────────────────────────────────────────────────────────
async function save(): Promise<void> {
    if (!context.value) return;
    const { creneauId, tacheId, tacheCode } = context.value;
    saving.value = true;

    try {
        const res = await fetch(`${window.PlanningConfig.routes.assignation}/${creneauId}/tache/${tacheId}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.PlanningConfig.csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                id_personne: selectedPersonneId.value ? parseInt(selectedPersonneId.value, 10) : null,
            }),
        });
        const data = await res.json() as {
            success: boolean;
            personne: PersonneAssignee | null;
            message: string;
        };

        if (data.success) {
            emit('assigned', creneauId, tacheCode, data.personne);
            toast.success(data.message);
            close();
        } else {
            toast.error('Erreur lors de la mise à jour');
        }
    } catch {
        toast.error('Erreur réseau');
    } finally {
        saving.value = false;
    }
}

// ── Désassignation ────────────────────────────────────────────────────────
async function unassign(): Promise<void> {
    if (!context.value) return;
    if (!confirm('Désassigner cette tâche ?')) return;

    const { creneauId, tacheId, tacheCode } = context.value;

    try {
        const res = await fetch(`${window.PlanningConfig.routes.assignation}/${creneauId}/tache/${tacheId}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': window.PlanningConfig.csrf, 'Accept': 'application/json' },
        });
        const data = await res.json() as { success: boolean };

        if (data.success) {
            emit('unassigned', creneauId, tacheCode);
            toast.success('Tâche désassignée');
            close();
        }
    } catch {
        toast.error('Erreur réseau');
    }
}

// ── Suppression du créneau entier depuis le modal ─────────────────────────
async function deleteCreneau(): Promise<void> {
    if (!context.value) return;
    if (!confirm('Supprimer tout ce créneau ?')) return;

    const { creneauId } = context.value;
    close();

    try {
        const res = await fetch(`${window.PlanningConfig.routes.creneau}/${creneauId}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': window.PlanningConfig.csrf, 'Accept': 'application/json' },
        });
        const data = await res.json() as { success: boolean; message: string };

        if (data.success) {
            emit('deleted', creneauId);
            toast.success(data.message);
        } else {
            toast.error('Erreur');
        }
    } catch {
        toast.error('Erreur réseau');
    }
}

// ── Exposition au parent ───────────────────────────────────────────────────
// defineExpose() rend ces méthodes accessibles depuis le parent via une ref
// template : <AssignModal ref="assignModalRef" /> puis assignModalRef.value?.open(ctx).
// Sans ça, ces fonctions resteraient privées au composant (encapsulation par défaut
// dans <script setup> — tout est privé sauf ce qui passe par defineExpose).
defineExpose({ open });

</script>

<template>
    <Modal :open="isOpen" @close="close" maxWidth="max-w-sm">
        <template #header>
            <div class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">
                {{ tacheMeta?.label.split(' ')[0] ?? '✏️' }}
            </div>
            <span class="font-heading text-[14px] font-semibold text-ink flex-1">
                Modifier — {{ context?.tacheLabel }}
            </span>
        </template>

        <div class="flex flex-col gap-4">
            <!-- Contexte -->
            <div class="flex items-center gap-2 px-3 py-2.5 bg-sky-50 border border-sky-100 rounded-lg text-[13px]">
                <strong class="text-ink">{{ context?.jour }} {{ context?.dateLabel }}</strong>
                <span class="text-ink-muted">Tâche : {{ context?.tacheLabel }}</span>
            </div>

            <!-- Réassigner -->
            <div>
                <p class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] mb-2">👤 Réassigner à</p>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-stretch">
                    <select
                        v-model="selectedPersonneId"
                        class="w-full flex-1 px-3 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base
                               font-body text-ink bg-surface-2 outline-none transition cursor-pointer
                               focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                    >
                        <option value="">— Aucune personne (désassigner) —</option>
                        <option v-for="p in personnes" :key="p.id" :value="String(p.id)">
                            {{ p.label }}
                        </option>
                    </select>
                    <button
                        @click="save"
                        :disabled="saving"
                        class="w-full sm:w-auto px-4 py-2.5 bg-accent hover:bg-accent-dark text-white text-[13px]
                               font-bold rounded-lg shadow-[0_2px_10px_rgba(3,105,161,0.3)] transition-all
                               cursor-pointer min-h-[44px] whitespace-nowrap disabled:opacity-50"
                    >
                        {{ saving ? '…' : 'Enregistrer' }}
                    </button>
                </div>
            </div>

            <div class="h-px bg-surface-3"></div>

            <!-- Zone dangereuse -->
            <div>
                <p class="text-[10.5px] font-bold text-rose-500 uppercase tracking-[0.7px] mb-2">⚠️ Zone dangereuse</p>
                <div class="flex gap-2 flex-wrap">
                    <button
                        @click="unassign"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-[12.5px] font-semibold rounded-lg
                               cursor-pointer transition-colors min-h-[44px] bg-rose-50 border border-rose-200
                               text-rose-700 hover:bg-rose-100"
                    >
                        ✕ Désassigner
                    </button>
                    <button
                        @click="deleteCreneau"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-[12.5px] font-semibold rounded-lg
                               cursor-pointer transition-colors min-h-[44px] bg-rose-50 border border-rose-200
                               text-rose-700 hover:bg-rose-100"
                    >
                        🗑️ Supprimer le créneau
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
