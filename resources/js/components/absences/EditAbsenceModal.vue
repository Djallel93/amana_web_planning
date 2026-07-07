<!-- resources/js/components/absences/EditAbsenceModal.vue -->
<!--
    Modal d'édition d'une absence existante.

    ── Stratégie de connexion avec la Blade existante ────────────────────────
    Comme SwapRequestModal.vue : la Blade absences/index.blade.php reste en
    grande partie inchangée. Chaque bouton "✏️" porte des attributs data-*
    avec les valeurs actuelles de l'absence, et appelle
    window.openEditAbsenceModal(this) — pont exposé ci-dessous.

    ── Permissions ──────────────────────────────────────────────────────────
    window.AbsencesConfig.isPrivileged indique si l'utilisateur connecté est
    admin/gestionnaire (peut choisir n'importe quelle personne dans la liste)
    ou membre (le champ personne est verrouillé sur lui-même — cohérent avec
    le formulaire de création déjà en place).

    ── API consommée ────────────────────────────────────────────────────────
    PUT /absences/{id} → { success: boolean, message: string, absence?: {...} }
-->
<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import Modal    from '@/components/shared/Modal.vue';
import { useModal } from '@/composables/useModal';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';

// ── Types ─────────────────────────────────────────────────────────────────
interface Personne {
    id: number;
    nom: string;
    prenom: string;
}

interface AbsenceContext {
    id:          number;
    idPersonne:  number;
    dateDebut:   string; // ISO "2026-07-01"
    dateFin:     string;
    raison:      string;
}

// ── Composables ───────────────────────────────────────────────────────────
const modal = useModal<AbsenceContext>();
const toast = useToast();
const { ask } = useConfirm();

// ── Config injectée par Blade ────────────────────────────────────────────
// Lue une seule fois au setup (config statique, injectée avant le montage
// de l'app Vue) et exposée comme variables locales — jamais référencée
// directement via `window` dans le <template> (non résolu par le compilateur
// de templates Vue, qui ne connaît que _ctx et un jeu de globaux whitelistés).
const isPrivileged = window.AbsencesConfig?.isPrivileged ?? false;
const personnes    = window.AbsencesConfig?.personnes ?? [];

// ── État local du formulaire ─────────────────────────────────────────────
const idPersonne = ref<number | null>(null);
const dateDebut  = ref('');
const dateFin    = ref('');
const raison     = ref('');
const submitting = ref(false);

const personneActuelle = computed(() =>
    personnes.find(p => p.id === idPersonne.value) ?? null
);

const canSubmit = computed(() =>
    idPersonne.value !== null && dateDebut.value !== '' && dateFin.value !== '' && !submitting.value
);

// ── Suivi des modifications non enregistrées ──────────────────────────────
// Utilisé pour avertir avant une fermeture accidentelle (Escape, backdrop,
// bouton Annuler) — voir requestClose() plus bas.
//
// suppressDirtyTracking évite un piège classique : watch() ne s'exécute
// qu'au tick suivant (microtâche), donc positionner dirty.value = false
// juste après avoir rempli les champs dans open() serait écrasé par le
// watcher déclenché par CE remplissage initial, une fois qu'il s'exécute.
let suppressDirtyTracking = false;
const dirty = ref(false);
watch([idPersonne, dateDebut, dateFin, raison], () => {
    if (suppressDirtyTracking) return;
    dirty.value = true;
});

// ── CSRF ──────────────────────────────────────────────────────────────────
function getCsrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

// ── Ouverture ─────────────────────────────────────────────────────────────
function open(context: AbsenceContext): void {
    suppressDirtyTracking = true;
    idPersonne.value = context.idPersonne;
    dateDebut.value  = context.dateDebut;
    dateFin.value    = context.dateFin;
    raison.value     = context.raison;
    modal.open(context);
    dirty.value = false;
    // Réactive le suivi après que le watcher déclenché par CE remplissage
    // initial se soit exécuté (nextTick garantit l'ordre par rapport au
    // watch(), qui tourne en microtâche).
    nextTick(() => { suppressDirtyTracking = false; });
}

// ── Ajustement automatique de la date de fin (comme le formulaire de création) ──
function onDateDebutChange(): void {
    if (!dateFin.value || dateFin.value < dateDebut.value) {
        dateFin.value = dateDebut.value;
    }
}

// ── Soumission ────────────────────────────────────────────────────────────
async function submit(): Promise<void> {
    if (!modal.data.value || idPersonne.value === null) return;

    submitting.value = true;

    try {
        const res = await fetch(`${window.AbsencesConfig.routeUpdateBase}/${modal.data.value.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                id_personne: idPersonne.value,
                date_debut:  dateDebut.value,
                date_fin:    dateFin.value,
                raison:      raison.value || null,
            }),
        });

        const data = await res.json() as { success: boolean; message: string };

        if (data.success) {
            dirty.value = false;
            modal.close();
            toast.success(data.message);
            // Rechargement pour refléter les changements dans la liste —
            // même stratégie que SwapRequestModal après une action réussie.
            setTimeout(() => window.location.reload(), 1200);
        } else {
            toast.error(data.message || 'Erreur lors de la modification.');
        }
    } catch {
        toast.error('Erreur réseau.');
    } finally {
        submitting.value = false;
    }
}

// ── Fermeture avec confirmation si des modifications sont en attente ─────
async function requestClose(): Promise<void> {
    if (dirty.value) {
        const ok = await ask({
            message: 'Des modifications non enregistrées seront perdues. Fermer quand même ?',
        });
        if (!ok) return;
    }
    modal.close();
}

// ── Pont avec la Blade existante ───────────────────────────────────────────
declare global {
    interface Window {
        openEditAbsenceModal: (btn: HTMLElement) => void;
        AbsencesConfig: {
            routeUpdateBase: string;
            isPrivileged:    boolean;
            currentUserId:   number;
            personnes:       Personne[];
        };
    }
}

window.openEditAbsenceModal = (btn: HTMLElement) => {
    open({
        id:         parseInt(btn.dataset.id ?? '0'),
        idPersonne: parseInt(btn.dataset.idPersonne ?? '0'),
        dateDebut:  btn.dataset.dateDebut ?? '',
        dateFin:    btn.dataset.dateFin ?? '',
        raison:     btn.dataset.raison ?? '',
    });
};
</script>

<template>
    <Modal :open="modal.isOpen.value" @close="requestClose" maxWidth="max-w-md">

        <template #header>
            <div class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">✏️</div>
            <span class="font-heading text-[14px] font-semibold text-ink flex-1">
                Modifier l'absence
            </span>
        </template>

        <div class="flex flex-col gap-4">

            <!-- Personne -->
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-bold text-ink tracking-[0.2px]">
                    <template v-if="isPrivileged">Personne <span class="text-rose-500">*</span></template>
                    <template v-else>Membre</template>
                </label>

                <select
                    v-if="isPrivileged"
                    v-model="idPersonne"
                    class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                           focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] cursor-pointer"
                >
                    <option v-for="p in personnes" :key="p.id" :value="p.id">
                        {{ p.prenom }} {{ p.nom }}
                    </option>
                </select>
                <div v-else class="px-3.5 py-2.5 bg-surface-2 border-[1.5px] border-ink-faint rounded-lg text-[13.5px] text-ink font-semibold">
                    {{ personneActuelle?.prenom }} {{ personneActuelle?.nom }}
                </div>
            </div>

            <!-- Date début -->
            <div class="flex flex-col gap-1.5">
                <label for="edit_date_debut" class="text-xs font-bold text-ink tracking-[0.2px]">Début <span class="text-rose-500">*</span></label>
                <input
                    id="edit_date_debut" type="date" v-model="dateDebut" required
                    @change="onDateDebutChange"
                    class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                           focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                >
            </div>

            <!-- Date fin -->
            <div class="flex flex-col gap-1.5">
                <label for="edit_date_fin" class="text-xs font-bold text-ink tracking-[0.2px]">Fin <span class="text-rose-500">*</span></label>
                <input
                    id="edit_date_fin" type="date" v-model="dateFin" required :min="dateDebut"
                    class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                           focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                >
            </div>

            <!-- Raison -->
            <div class="flex flex-col gap-1.5">
                <label for="edit_raison" class="text-xs font-bold text-ink tracking-[0.2px]">
                    Raison <span class="text-ink-muted font-normal">(optionnel)</span>
                </label>
                <input
                    id="edit_raison" type="text" v-model="raison" maxlength="255"
                    placeholder="Vacances, maladie, congé…"
                    class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                           focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                >
            </div>
        </div>

        <template #footer>
            <button
                class="flex-1 min-h-[48px] px-4 py-2.5 bg-accent hover:bg-accent-dark
                       text-white text-[13px] font-bold rounded-lg transition-all cursor-pointer
                       flex items-center justify-center gap-1.5
                       disabled:opacity-40 disabled:cursor-not-allowed
                       shadow-[0_3px_12px_rgba(3,105,161,0.3)]"
                :disabled="!canSubmit"
                @click="submit"
            >
                <span v-if="submitting">⏳ Enregistrement…</span>
                <span v-else>💾 Enregistrer</span>
            </button>
            <button
                class="px-4 py-2.5 border-[1.5px] border-ink-faint text-ink-muted
                       hover:bg-surface-3 hover:text-ink text-[13px] font-semibold
                       rounded-lg transition-colors cursor-pointer bg-transparent min-h-[48px]"
                @click="requestClose"
            >
                Annuler
            </button>
        </template>
    </Modal>
</template>
