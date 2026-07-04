<!-- resources/js/components/planning/AnnulationCoursModal.vue -->
<!--
    Modal "Annulation cours" — accessible gestionnaire + admin uniquement
    (le bouton qui l'ouvre est déjà gardé par v-if="peutEditer" côté parent,
    et la route POST /planning/annulation-cours est protégée côté serveur
    par le middleware role:gestionnaire).

    ── Déroulé ────────────────────────────────────────────────────────────
    Étape 1 (choix) : l'utilisateur choisit une date future.
    Étape 2 (confirmation) : récapitulatif des conséquences en français,
        bouton rouge final "Confirmer l'annulation".

    Si aucun créneau n'existe pour la date choisie, le serveur répond avec
    un avertissement (rien n'est modifié) — affiché directement dans la
    modale, sans fermer.
-->
<script setup lang="ts">
import { ref, computed } from "vue";
import Modal from "@/components/shared/Modal.vue";
import { useToast } from "@/composables/useToast";

const emit = defineEmits<{
    cancelled: []; // signal au parent : recharger les données du planning
}>();

const toast = useToast();

// ── État ──────────────────────────────────────────────────────────────────
const isOpen = ref(false);
const step = ref<"choix" | "confirmation">("choix");
const selectedDate = ref("");
const submitting = ref(false);
const serverWarning = ref("");

// Date minimale sélectionnable : demain (date strictement future).
const minDate = computed((): string => {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    return d.toISOString().slice(0, 10);
});

const dateLabel = computed((): string => {
    if (!selectedDate.value) return "";
    return new Date(selectedDate.value + "T00:00:00").toLocaleDateString(
        "fr-FR",
        {
            weekday: "long",
            day: "numeric",
            month: "long",
            year: "numeric",
        },
    );
});

// ── Ouverture / fermeture ──────────────────────────────────────────────────
function open(): void {
    step.value = "choix";
    selectedDate.value = "";
    serverWarning.value = "";
    isOpen.value = true;
}

function close(): void {
    isOpen.value = false;
    step.value = "choix";
    selectedDate.value = "";
    serverWarning.value = "";
}

// ── Étape 1 → 2 ─────────────────────────────────────────────────────────────
function continuer(): void {
    if (!selectedDate.value) {
        toast.error("Veuillez choisir une date.");
        return;
    }
    serverWarning.value = "";
    step.value = "confirmation";
}

function retour(): void {
    serverWarning.value = "";
    step.value = "choix";
}

// ── Confirmation finale ──────────────────────────────────────────────────
async function confirmer(): Promise<void> {
    submitting.value = true;
    serverWarning.value = "";

    try {
        const res = await fetch(window.PlanningConfig.routes.annulationCours, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": window.PlanningConfig.csrf,
                Accept: "application/json",
            },
            body: JSON.stringify({ date: selectedDate.value }),
        });
        const data = (await res.json()) as {
            success?: boolean;
            warning?: boolean;
            message?: string;
            errors?: { date?: string[] };
        };

        if (res.ok && data.success) {
            toast.success(data.message ?? "Cours annulé.");
            close();
            emit("cancelled");
        } else if (data.warning) {
            // Aucun créneau pour cette date : on informe sans fermer ni rien modifier.
            serverWarning.value =
                data.message ?? "Cette date ne peut pas être annulée.";
        } else {
            toast.error(
                data.errors?.date?.[0] ??
                    data.message ??
                    "Erreur lors de l'annulation.",
            );
        }
    } catch {
        toast.error("Erreur réseau");
    } finally {
        submitting.value = false;
    }
}

defineExpose({ open });
</script>

<template>
    <Modal :open="isOpen" @close="close" maxWidth="max-w-md">
        <template #header>
            <div
                class="w-7 h-7 bg-rose-50 rounded-md flex items-center justify-center text-sm flex-shrink-0"
            >
                🚫
            </div>
            <span class="font-heading text-[14px] font-semibold text-ink flex-1"
                >Annulation cours</span
            >
        </template>

        <!-- Étape 1 : choix de la date -->
        <div v-if="step === 'choix'" class="flex flex-col gap-4">
            <p class="text-[13px] text-ink-muted leading-relaxed">
                Choisissez la date du cours à annuler. Seules les dates futures
                sont autorisées.
            </p>

            <div>
                <p
                    class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] mb-2"
                >
                    📅 Date du cours à annuler
                </p>
                <input
                    type="date"
                    v-model="selectedDate"
                    :min="minDate"
                    class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                />
            </div>

            <div class="flex gap-2">
                <button
                    @click="continuer"
                    class="flex-1 min-h-[48px] px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-[13px] font-bold rounded-lg shadow-[0_3px_12px_rgba(225,29,72,0.3)] transition-all cursor-pointer flex items-center justify-center gap-1.5"
                >
                    Continuer
                </button>
                <button
                    @click="close"
                    class="px-4 py-2.5 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors cursor-pointer min-h-[48px]"
                >
                    Annuler
                </button>
            </div>
        </div>

        <!-- Étape 2 : confirmation avec implications -->
        <div v-else class="flex flex-col gap-4">
            <div
                class="flex items-start gap-2.5 px-4 py-3 bg-rose-50 border border-rose-200 rounded-lg text-[13px] text-rose-800 leading-relaxed"
            >
                <span class="flex-shrink-0">⚠️</span>
                <span>
                    Vous êtes sur le point d'annuler le cours du
                    <strong>{{ dateLabel }}</strong
                    >. Cette action va bloquer la date, désassigner toutes les
                    tâches en cours, et supprimer les événements calendrier
                    associés. Cette action est irréversible.
                </span>
            </div>

            <div
                v-if="serverWarning"
                class="flex items-start gap-2.5 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-[13px] text-amber-800 leading-relaxed"
            >
                <span class="flex-shrink-0">ℹ️</span>
                <span>{{ serverWarning }}</span>
            </div>

            <div class="flex gap-2">
                <button
                    @click="confirmer"
                    :disabled="submitting"
                    class="flex-1 min-h-[48px] px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-[13px] font-bold rounded-lg shadow-[0_3px_12px_rgba(225,29,72,0.3)] transition-all cursor-pointer flex items-center justify-center gap-1.5 disabled:opacity-50"
                >
                    {{
                        submitting
                            ? "⏳ Annulation…"
                            : "🚫 Confirmer l'annulation"
                    }}
                </button>
                <button
                    @click="retour"
                    :disabled="submitting"
                    class="px-4 py-2.5 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors cursor-pointer min-h-[48px] disabled:opacity-50"
                >
                    ← Retour
                </button>
            </div>
        </div>
    </Modal>
</template>
