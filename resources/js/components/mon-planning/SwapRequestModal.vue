<!-- resources/js/components/mon-planning/SwapRequestModal.vue -->
<!--
    Modal de demande d'échange de créneau — remplace le bloc <script> inline
    de resources/views/planning/mon-planning.blade.php.

    ── Stratégie de connexion avec la Blade existante ────────────────────────
    Les boutons "🔄 Échanger" dans la Blade ont actuellement onclick="openSwapModal(this)".
    Après migration, ils auront @click natif — mais pour ne pas tout refaire d'un coup,
    on expose une fonction globale window.__openSwapModal() pendant la transition.
    Un attribut data- sur chaque bouton passe le contexte (creneauId, tacheId, etc.).

    ── API consommée ──────────────────────────────────────────────────────────
    GET  /echanges/slots-disponibles?creneau_id=X&tache_id=Y
         → Slot[] (voir type ci-dessous)
    POST /echanges
         → { success: boolean, message: string }

    ── Structure des données ──────────────────────────────────────────────────
    SwapContext : ce que le bouton "Échanger" nous transmet (créneau de l'utilisateur)
    Slot        : un créneau disponible pour l'échange (réponse API)
-->
<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import Modal    from '@/components/shared/Modal.vue';
import { useModal }  from '@/composables/useModal';
import { useToast }  from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';

// ── Types ─────────────────────────────────────────────────────────────────
// Ces interfaces décrivent exactement la forme des données qu'on manipule.
// Si l'API change un nom de champ, TS nous le signalera immédiatement.

interface SwapContext {
    creneauId:    number;
    tacheId:      number;
    tacheLibelle: string;
    dateLabel:    string;   // ex. "vendredi 14 mars 2025"
}

interface Slot {
    creneau_id:    number;
    tache_id:      number;
    personne_id:   number;
    personne_nom:  string;
    date:          string;  // ISO "2025-03-14"
    date_label:    string;  // "vendredi 14 mars 2025"
    jour:          string;  // "Vendredi"
    tache_libelle: string;
}

// ── Composables ───────────────────────────────────────────────────────────
// useModal<SwapContext>() : le générique précise que data contiendra un SwapContext.
// TS refusera modal.open({ n'importe_quoi }) — seulement un SwapContext valide.
const modal = useModal<SwapContext>();
const toast = useToast();
const { ask } = useConfirm();

// ── État local ────────────────────────────────────────────────────────────
type LoadState = 'idle' | 'loading' | 'loaded' | 'error';

const slots         = ref<Slot[]>([]);
const loadState     = ref<LoadState>('idle');
const selectedSlot  = ref<Slot | null>(null);
const submitting    = ref(false);

// ── Filtre par plage de dates (du/au) ────────────────────────────────────
// Filtrage purement client : tous les slots futurs pour la tâche sont déjà
// chargés en une fois, donc pas besoin de round-trip serveur pour filtrer.
const dateFrom = ref('');
const dateTo   = ref('');

const filteredSlots = computed(() => {
    return slots.value.filter((slot) => {
        if (dateFrom.value && slot.date < dateFrom.value) return false;
        if (dateTo.value && slot.date > dateTo.value) return false;
        return true;
    });
});

// computed : le bouton "Envoyer" n'est actif que si un slot est sélectionné
// et qu'on n'est pas en train de soumettre.
const canSubmit = computed(() => selectedSlot.value !== null && !submitting.value);

// ── Fermeture avec confirmation si une sélection est en attente ───────────
// Pas de watch() nécessaire ici comme dans EditAbsenceModal : "dirty" se
// résume à "un slot a été choisi mais pas encore envoyé", ce qui se dérive
// directement de selectedSlot sans piège de timing.
const dirty = computed(() => selectedSlot.value !== null);

async function requestClose(): Promise<void> {
    if (dirty.value) {
        const ok = await ask({
            message: 'Vous avez sélectionné un créneau mais la demande n\'a pas été envoyée. Fermer quand même ?',
        });
        if (!ok) return;
    }
    modal.close();
}

// Si le slot sélectionné sort de la plage filtrée, on désélectionne pour
// éviter d'envoyer une demande sur un créneau qui n'est plus visible.
watch(filteredSlots, (visible) => {
    if (selectedSlot.value && !visible.includes(selectedSlot.value)) {
        selectedSlot.value = null;
    }
});

// ── CSRF ──────────────────────────────────────────────────────────────────
// On lit le token CSRF depuis la balise <meta name="csrf-token"> injectée
// par Blade dans le <head> — même source que le JS vanilla actuel.
// querySelector<HTMLMetaElement> : le générique dit à TS que le résultat
// est un HTMLMetaElement (qui a .content), pas juste Element.
function getCsrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

// ── Ouverture ─────────────────────────────────────────────────────────────
async function open(context: SwapContext): Promise<void> {
    slots.value        = [];
    selectedSlot.value = null;
    dateFrom.value      = '';
    dateTo.value        = '';
    loadState.value    = 'loading';
    modal.open(context);
    await loadSlots(context.creneauId, context.tacheId);
}

// ── Chargement des slots disponibles ─────────────────────────────────────
async function loadSlots(creneauId: number, tacheId: number): Promise<void> {
    // Les routes sont injectées par Blade via window.MonPlanningConfig
    // (même pattern que window.PlanningConfig dans planning-index.js).
    // On définit ce type plus bas pour que TS le connaisse.
    const url = `${window.MonPlanningConfig.routeSlots}?creneau_id=${creneauId}&tache_id=${tacheId}`;

    try {
        const res  = await fetch(url, {
            headers: { 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json' },
        });
        const data = await res.json() as Slot[];
        slots.value     = data;
        loadState.value = 'loaded';
    } catch {
        loadState.value = 'error';
    }
}

// ── Soumission ────────────────────────────────────────────────────────────
async function submit(): Promise<void> {
    if (!selectedSlot.value || !modal.data.value) return;

    submitting.value = true;

    try {
        const res = await fetch(window.MonPlanningConfig.routeStore, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                creneau_demandeur_id: modal.data.value.creneauId,
                tache_demandeur_id:   modal.data.value.tacheId,
                creneau_cible_id:     selectedSlot.value.creneau_id,
                tache_cible_id:       selectedSlot.value.tache_id,
                personne_cible_id:    selectedSlot.value.personne_id,
            }),
        });

        const data = await res.json() as { success: boolean; message: string };

        if (data.success) {
            modal.close();
            toast.success(data.message);
            // Rechargement après que le toast est visible — identique à l'original.
            setTimeout(() => window.location.reload(), 2500);
        } else {
            toast.error(data.message || 'Erreur lors de la demande.');
        }
    } catch {
        toast.error('Erreur réseau.');
    } finally {
        submitting.value = false;
    }
}

// ── Pont avec la Blade existante ───────────────────────────────────────────
// Les boutons Blade ont encore onclick="openSwapModal(this)" pour l'instant.
// On expose une fonction globale temporaire le temps de migrer les boutons.
// À supprimer quand on retire l'attribut onclick des boutons Blade.
//
// Typage de window : TS ne connaît pas nos propriétés custom sur window.
// On les déclare avec "declare global" pour éviter les erreurs de compilation.
declare global {
    interface Window {
        openSwapModal: (btn: HTMLElement) => void;
        MonPlanningConfig: {
            routeSlots: string;
            routeStore: string;
        };
    }
}

window.openSwapModal = (btn: HTMLElement) => {
    open({
        creneauId:    parseInt(btn.dataset.creneauId    ?? '0'),
        tacheId:      parseInt(btn.dataset.tacheId      ?? '0'),
        tacheLibelle: btn.dataset.tacheLibelle           ?? '',
        dateLabel:    btn.dataset.date                   ?? '',
    });
};
</script>

<template>
    <!--
        On utilise le composant Modal.vue générique.
        :open="modal.isOpen.value" → la prop "open" contrôle la visibilité.
        @close="requestClose"      → confirme avant de fermer si un slot est
                                      sélectionné mais pas encore envoyé.
        max-w-md au lieu de max-w-sm (le modal swap est plus large — liste de slots).
    -->
    <Modal :open="modal.isOpen.value" @close="requestClose" maxWidth="max-w-md">

        <!-- Slot header : icône + titre -->
        <template #header>
            <div class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">🔄</div>
            <span class="font-heading text-[14px] font-semibold text-ink flex-1">
                Demander un échange de créneau
            </span>
        </template>

        <!-- Slot default : corps du modal -->
        <div class="flex flex-col gap-4">

            <!-- Contexte : mon créneau -->
            <div class="flex items-center gap-3 px-4 py-3 bg-sky-50 border border-sky-200 rounded-lg">
                <span class="text-xl flex-shrink-0">📅</span>
                <div>
                    <div class="font-bold text-[13.5px] text-ink">
                        {{ modal.data.value?.dateLabel ?? '—' }}
                    </div>
                    <div class="text-[12.5px] text-ink-muted mt-0.5">
                        🔄 Tâche : {{ modal.data.value?.tacheLibelle ?? '—' }}
                    </div>
                </div>
            </div>

            <!-- Sélection du slot cible -->
            <div>
                <p class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] mb-2">
                    Choisir le créneau avec lequel échanger
                </p>

                <!-- Filtre par plage de dates -->
                <div v-if="loadState === 'loaded' && slots.length" class="flex items-center gap-2 mb-3">
                    <input
                        type="date" v-model="dateFrom" aria-label="Du"
                        class="flex-1 min-w-0 px-2.5 py-2 border-[1.5px] border-ink-faint rounded-lg text-[12.5px] font-body text-ink bg-surface-2 outline-none transition
                               focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                    >
                    <span class="text-[12px] text-ink-muted flex-shrink-0">→</span>
                    <input
                        type="date" v-model="dateTo" aria-label="Au" :min="dateFrom"
                        class="flex-1 min-w-0 px-2.5 py-2 border-[1.5px] border-ink-faint rounded-lg text-[12.5px] font-body text-ink bg-surface-2 outline-none transition
                               focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                    >
                    <button
                        v-if="dateFrom || dateTo"
                        type="button"
                        class="text-[11.5px] text-ink-muted hover:text-ink underline flex-shrink-0 cursor-pointer bg-transparent"
                        @click="dateFrom = ''; dateTo = ''"
                    >
                        Réinitialiser
                    </button>
                </div>

                <!-- Chargement -->
                <div
                    v-if="loadState === 'loading'"
                    class="text-center py-8 text-[13.5px] text-ink-muted"
                >
                    ⏳ Chargement des créneaux disponibles…
                </div>

                <!-- Erreur réseau -->
                <div
                    v-else-if="loadState === 'error'"
                    class="text-center py-6 text-rose-600 text-[13px]"
                >
                    ❌ Erreur lors du chargement.
                </div>

                <!-- Aucun créneau disponible du tout -->
                <div
                    v-else-if="loadState === 'loaded' && !slots.length"
                    class="text-center py-8 px-4 text-[13.5px] text-ink-muted
                           bg-surface-2 rounded-lg border border-surface-border"
                >
                    😕 Aucun créneau disponible pour cet échange.
                </div>

                <!-- Aucun créneau dans la plage de dates filtrée -->
                <div
                    v-else-if="loadState === 'loaded' && slots.length && !filteredSlots.length"
                    class="text-center py-8 px-4 text-[13.5px] text-ink-muted
                           bg-surface-2 rounded-lg border border-surface-border"
                >
                    😕 Aucun créneau dans cette plage de dates.
                </div>

                <!-- Liste des slots -->
                <div
                    v-else-if="filteredSlots.length"
                    class="flex flex-col gap-2 max-h-[280px] overflow-y-auto"
                >
                    <!--
                        :class dynamique : on ajoute border-accent et bg-sky-50
                        quand ce slot est sélectionné, pour le mettre en évidence.
                        On retire les classes du slot précédent automatiquement
                        car Vue recalcule :class à chaque changement de selectedSlot.
                    -->
                    <label
                        v-for="slot in filteredSlots"
                        :key="`${slot.creneau_id}-${slot.tache_id}`"
                        class="flex items-center gap-3 px-4 py-3 border-[1.5px]
                               rounded-lg cursor-pointer transition-colors"
                        :class="selectedSlot === slot
                            ? 'border-accent bg-sky-50'
                            : 'border-surface-border hover:border-accent hover:bg-sky-50'"
                    >
                        <input
                            type="radio"
                            name="slot_choice"
                            class="w-4 h-4 accent-accent flex-shrink-0"
                            :checked="selectedSlot === slot"
                            @change="selectedSlot = slot"
                        >
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-[13px] text-ink">{{ slot.date_label }}</div>
                            <div class="text-[12px] text-ink-muted mt-0.5">
                                {{ slot.tache_libelle }} · avec <strong>{{ slot.personne_nom }}</strong>
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Slot footer : boutons d'action -->
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
                <span v-if="submitting">⏳ Envoi…</span>
                <span v-else>🔄 Envoyer la demande</span>
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
