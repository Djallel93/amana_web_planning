<!-- resources/js/components/planning/AddCreneauModal.vue -->
<!--
    Modal d'ajout manuel d'un créneau — remplace addCreneauBackdrop/addCreneauModal
    de _add-creneau-modal.blade.php et openAddCreneauModal/submitAddCreneau de
    planning-index.js.

    ── Différence avec AssignModal ──────────────────────────────────────────
    Pas de fetch au chargement — le contexte (semaine, dates déjà prises)
    arrive directement avec le contexte d'ouverture, fourni par PlanningGrid
    qui le connaît déjà depuis les données JSON chargées.
-->
<script setup lang="ts">
import { ref, computed } from "vue";
import Modal from "@/components/shared/Modal.vue";
import { useToast } from "@/composables/useToast";
import type { AddCreneauContext } from "@/types/planning";

const emit = defineEmits<{
    created: []; // signal au parent : recharger les données du planning
}>();

const toast = useToast();

// ── État ──────────────────────────────────────────────────────────────────
const isOpen = ref(false);
const context = ref<AddCreneauContext | null>(null);
const selectedDate = ref("");
const creating = ref(false);

// ── Libellé de la période de la semaine ───────────────────────────────────
const weekInfoHtml = computed((): string => {
    if (!context.value) return "";
    const fmt = (iso: string, withYear: boolean) =>
        new Date(iso + "T00:00:00").toLocaleDateString("fr-FR", {
            day: "numeric",
            month: "long",
            year: withYear ? "numeric" : undefined,
        });
    return `Semaine du ${fmt(context.value.weekMin, false)} au ${fmt(context.value.weekMax, true)}`;
});

// ── Indication sur les dates déjà occupées ────────────────────────────────
const hintText = computed((): string => {
    if (!context.value) return "";
    if (context.value.existingDates.length === 0) {
        return "Choisissez n'importe quel jour de cette semaine.";
    }
    const labels = context.value.existingDates.map((d) =>
        new Date(d + "T00:00:00").toLocaleDateString("fr-FR", {
            weekday: "long",
            day: "numeric",
            month: "long",
        }),
    );
    return `Déjà créé : ${labels.join(", ")}.`;
});

// ── Ouverture / fermeture ──────────────────────────────────────────────────
function open(ctx: AddCreneauContext): void {
    context.value = ctx;
    selectedDate.value = "";
    isOpen.value = true;
}

function close(): void {
    isOpen.value = false;
    context.value = null;
}

// ── Soumission ────────────────────────────────────────────────────────────
async function submit(): Promise<void> {
    if (!selectedDate.value) {
        toast.error("Veuillez choisir une date.");
        return;
    }
    if (context.value?.existingDates.includes(selectedDate.value)) {
        toast.error("Un créneau existe déjà pour cette date.");
        return;
    }

    creating.value = true;

    try {
        const res = await fetch(window.PlanningConfig.routes.creneau, {
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
            message?: string;
            errors?: { date?: string[] };
        };

        if (res.ok && data.success) {
            toast.success(data.message ?? "Créneau créé.");
            close();
            emit("created");
        } else {
            toast.error(
                data.errors?.date?.[0] ??
                    data.message ??
                    "Erreur lors de la création.",
            );
        }
    } catch {
        toast.error("Erreur réseau");
    } finally {
        creating.value = false;
    }
}

defineExpose({ open });
</script>

<template>
    <Modal :open="isOpen" @close="close" maxWidth="max-w-sm">
        <template #header>
            <div
                class="w-7 h-7 bg-emerald-50 rounded-md flex items-center justify-center text-sm flex-shrink-0"
            >
                ➕
            </div>
            <span class="font-heading text-[14px] font-semibold text-ink flex-1"
                >Ajouter un créneau</span
            >
        </template>

        <div class="flex flex-col gap-4">
            <div
                class="flex items-center gap-2 px-3 py-2.5 bg-sky-50 border border-sky-100 rounded-lg text-[13px]"
            >
                <strong class="text-ink">{{ weekInfoHtml }}</strong>
                <span class="text-ink-muted"
                    >Choisissez une date dans cette semaine</span
                >
            </div>

            <div>
                <p
                    class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] mb-2"
                >
                    📅 Date du créneau
                </p>
                <input
                    type="date"
                    v-model="selectedDate"
                    :min="context?.weekMin"
                    :max="context?.weekMax"
                    class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                />
                <p class="text-[11.5px] text-ink-muted mt-1.5 min-h-[18px]">
                    {{ hintText }}
                </p>
            </div>

            <div class="flex gap-2">
                <button
                    @click="submit"
                    :disabled="creating"
                    class="flex-1 min-h-[48px] px-4 py-2.5 bg-accent hover:bg-accent-dark text-white text-[13px] font-bold rounded-lg shadow-[0_3px_12px_rgba(3,105,161,0.3)] transition-all cursor-pointer flex items-center justify-center gap-1.5 disabled:opacity-50"
                >
                    {{ creating ? "⏳ Création…" : "➕ Créer le créneau" }}
                </button>
                <button
                    @click="close"
                    class="px-4 py-2.5 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors cursor-pointer min-h-[48px]"
                >
                    Annuler
                </button>
            </div>
        </div>
    </Modal>
</template>
