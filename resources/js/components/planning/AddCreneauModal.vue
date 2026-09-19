<!-- resources/js/components/planning/AddCreneauModal.vue -->
<!--
    Modal d'ajout manuel d'un créneau — remplace addCreneauBackdrop/addCreneauModal
    de _add-creneau-modal.blade.php et openAddCreneauModal/submitAddCreneau de
    planning-index.js.

    ── Différence avec AssignModal ──────────────────────────────────────────
    Pas de fetch au chargement — le contexte (semaine, dates déjà prises)
    arrive directement avec le contexte d'ouverture, fourni par PlanningGrid
    qui le connaît déjà depuis les données JSON chargées.

    ── Deux modes (voir AddCreneauContext) ──────────────────────────────────
    - Semaine (défaut) : ouvert depuis le bouton d'un bloc semaine, date
      bornée à cette semaine.
    - Passé (`passe: true`) : ouvert depuis le bouton « Créneau passé » de la
      barre d'outils, RÉSERVÉ AUX ADMINS (le serveur renvoie 403 sinon — voir
      PlanningEditController::createCreneau()). Sert à rattraper un week-end
      jamais généré : la semaine n'apparaît pas dans la grille, donc aucun
      bloc semaine ne peut l'ouvrir. Date libre mais strictement antérieure à
      aujourd'hui. Le moteur de rotation n'invente pas de bénévoles pour un
      jour écoulé : l'admin choisit ici, tâche par tâche, qui était de
      permanence (facultatif — une tâche laissée vide reste non assignée). Le
      créneau est ensuite synchronisé avec Google Calendar comme n'importe
      quel autre.

      Dès qu'une date est choisie, la modale interroge le contexte de cette
      date (GET /planning/creneau-passe/contexte) :
      - tâches bloquées par un événement → sélecteur désactivé (le serveur
        refuse de toute façon une assignation sur une tâche bloquée) ;
      - personnes déclarées absentes → repérées « (absent·e) » dans les listes
        et signalées par un avertissement, MAIS l'assignation reste possible
        (choix de l'admin — voir PlanningEditController::createCreneau()).
-->
<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { Modal } from '@amana/shared-ui';
import { useToast } from '@amana/shared-ui';
import type {
    AddCreneauContext,
    CreneauPasseContexte,
    PersonneAssignee,
    TacheCode,
} from "@/types/planning";
import { TACHES_META, TACHE_CODES } from "@/types/planning";

const emit = defineEmits<{
    // signal au parent : recharger les données du planning. `date` (ISO) est
    // la date du créneau créé — le parent s'en sert pour s'assurer que la
    // semaine correspondante n'est pas masquée par les filtres actifs.
    created: [date: string];
}>();

const toast = useToast();

// ── État ──────────────────────────────────────────────────────────────────
const isOpen = ref(false);
const context = ref<AddCreneauContext | null>(null);
const selectedDate = ref("");
const creating = ref(false);

const isPasse = computed((): boolean => context.value?.passe === true);

// ── Assignations (mode passé) ─────────────────────────────────────────────
// Un <select> par tâche ; "" = non assignée. Valeurs en string car liées à un
// <select> (même convention qu'AssignModal).
type Choix = Record<TacheCode, string>;

function choixVides(): Choix {
    return Object.fromEntries(TACHE_CODES.map((c) => [c, ""])) as Choix;
}

const personnes = ref<PersonneAssignee[]>([]);
const choix = ref<Choix>(choixVides());

async function loadPersonnes(): Promise<void> {
    try {
        const res = await fetch(window.PlanningConfig.routes.personnes, {
            headers: {
                "X-CSRF-TOKEN": window.PlanningConfig.csrf,
                Accept: "application/json",
            },
        });
        if (!res.ok) throw new Error("HTTP " + res.status);
        personnes.value = (await res.json()) as PersonneAssignee[];
    } catch {
        personnes.value = [];
        toast.error("Impossible de charger la liste des personnes.");
    }
}

// ── Dates locales (pas UTC) au format YYYY-MM-DD ──────────────────────────
// Cohérent avec <input type="date"> — toISOString() décalerait d'un jour
// autour de minuit pour un navigateur hors UTC.
function toLocalIso(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function todayIso(): string {
    return toLocalIso(new Date());
}

// Dernier jour sélectionnable en mode passé : hier.
const maxPasse = computed((): string => {
    const d = new Date();
    d.setDate(d.getDate() - 1);
    return toLocalIso(d);
});

// ── Libellé de la période de la semaine ───────────────────────────────────
const weekInfoHtml = computed((): string => {
    const ctx = context.value;
    if (!ctx || !ctx.weekMin || !ctx.weekMax) return "";
    const fmt = (iso: string, withYear: boolean) =>
        new Date(iso + "T00:00:00").toLocaleDateString("fr-FR", {
            day: "numeric",
            month: "long",
            year: withYear ? "numeric" : undefined,
        });
    return `Semaine du ${fmt(ctx.weekMin, false)} au ${fmt(ctx.weekMax, true)}`;
});

// Libellé complet de la date choisie (ex. « vendredi 12 septembre 2026 ») —
// permet à l'admin de vérifier qu'il vise bien un vendredi/samedi.
const selectedDateLabel = computed((): string => {
    if (!selectedDate.value) return "";
    return new Date(selectedDate.value + "T00:00:00").toLocaleDateString(
        "fr-FR",
        { weekday: "long", day: "numeric", month: "long", year: "numeric" },
    );
});

// ── Indication sur les dates déjà occupées ────────────────────────────────
const hintText = computed((): string => {
    if (!context.value) return "";
    if (isPasse.value) {
        return selectedDateLabel.value
            ? `Créneau du ${selectedDateLabel.value}.`
            : "Choisissez un jour déjà passé (les permanences ont lieu le vendredi et le samedi).";
    }
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

// ── Contexte de la date choisie (événements, absences) ─────────────────────
const contexte = ref<CreneauPasseContexte | null>(null);
const contexteEnCours = ref(false);

function todayIsoLocal(): string {
    return toLocalIso(new Date());
}

async function loadContexte(date: string): Promise<void> {
    contexteEnCours.value = true;
    try {
        const res = await fetch(
            `${window.PlanningConfig.routes.creneauContexte}?date=${encodeURIComponent(date)}`,
            {
                headers: {
                    "X-CSRF-TOKEN": window.PlanningConfig.csrf,
                    Accept: "application/json",
                },
            },
        );
        if (!res.ok) throw new Error("HTTP " + res.status);
        const data = (await res.json()) as CreneauPasseContexte;

        // Réponse périmée : l'admin a changé de date entre-temps.
        if (selectedDate.value !== date) return;

        contexte.value = data;
        // Une tâche bloquée ne peut pas être assignée : on vide un choix
        // éventuel fait avant l'arrivée du contexte.
        for (const code of TACHE_CODES) {
            if (data.tachesBloquees[code]) choix.value[code] = "";
        }
    } catch {
        if (selectedDate.value !== date) return;
        contexte.value = null;
        toast.error(
            "Impossible de vérifier les événements et absences de cette date.",
        );
    } finally {
        if (selectedDate.value === date) contexteEnCours.value = false;
    }
}

// Recharge le contexte à chaque changement de date valide (mode passé).
watch(selectedDate, (date) => {
    contexte.value = null;
    contexteEnCours.value = false;
    if (!isPasse.value || !date || date >= todayIsoLocal()) return;
    void loadContexte(date);
});

const absentIds = computed(
    (): Set<number> => new Set(contexte.value?.absents ?? []),
);

function tacheBloqueePar(code: TacheCode): string | null {
    return contexte.value?.tachesBloquees[code] ?? null;
}

function libellePersonne(p: PersonneAssignee): string {
    return absentIds.value.has(p.id) ? `${p.label} — absent·e ce jour-là` : p.label;
}

// Personnes choisies qui sont déclarées absentes ce jour-là — avertissement
// non bloquant.
const absentsChoisis = computed((): string[] => {
    const noms: string[] = [];
    for (const code of TACHE_CODES) {
        const id = parseInt(choix.value[code], 10);
        if (!id || !absentIds.value.has(id)) continue;
        const p = personnes.value.find((x) => x.id === id);
        if (p && !noms.includes(p.label)) noms.push(p.label);
    }
    return noms;
});

// ── Ouverture / fermeture ──────────────────────────────────────────────────
function open(ctx: AddCreneauContext): void {
    context.value = ctx;
    selectedDate.value = "";
    choix.value = choixVides();
    contexte.value = null;
    contexteEnCours.value = false;
    isOpen.value = true;
    if (ctx.passe) void loadPersonnes();
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
    if (isPasse.value && contexte.value?.dejaExistant) {
        toast.error("Un créneau existe déjà pour cette date.");
        return;
    }
    // Le `max` de l'input n'empêche pas une saisie clavier hors bornes.
    if (isPasse.value && selectedDate.value >= todayIso()) {
        toast.error("Choisissez une date déjà passée.");
        return;
    }

    creating.value = true;
    const date = selectedDate.value;

    // Code de tâche → id_personne, uniquement les tâches renseignées.
    const assignations: Record<string, number> = {};
    if (isPasse.value) {
        for (const code of TACHE_CODES) {
            if (choix.value[code] && !tacheBloqueePar(code)) {
                assignations[code] = parseInt(choix.value[code], 10);
            }
        }
    }

    try {
        const res = await fetch(window.PlanningConfig.routes.creneau, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": window.PlanningConfig.csrf,
                Accept: "application/json",
            },
            body: JSON.stringify(
                isPasse.value ? { date, assignations } : { date },
            ),
        });
        const data = (await res.json()) as {
            success?: boolean;
            message?: string;
            errors?: { date?: string[]; [champ: string]: string[] | undefined };
        };

        if (res.ok && data.success) {
            toast.success(data.message ?? "Créneau créé.");
            close();
            emit("created", date);
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
    <Modal
        :open="isOpen"
        @close="close"
        :max-width="isPasse ? 'max-w-md' : 'max-w-sm'"
    >
        <template #header>
            <div
                class="w-7 h-7 rounded-md flex items-center justify-center text-sm flex-shrink-0"
                :class="isPasse ? 'bg-amber-50' : 'bg-emerald-50'"
            >
                {{ isPasse ? "🕓" : "➕" }}
            </div>
            <span class="font-heading text-[14px] font-semibold text-ink flex-1">{{
                isPasse ? "Ajouter un créneau passé" : "Ajouter un créneau"
            }}</span>
        </template>

        <div class="flex flex-col gap-4">
            <div
                v-if="isPasse"
                class="flex flex-col gap-1 px-3 py-2.5 bg-amber-50 border border-amber-200 rounded-lg text-[13px] text-amber-800"
            >
                <strong>Correction administrateur</strong>
                <span
                    >Pour rattraper un week-end jamais généré. Le créneau est
                    synchronisé avec Google Calendar comme un créneau normal —
                    choisissez ci-dessous qui était de permanence.</span
                >
            </div>
            <div
                v-else
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
                    :min="isPasse ? undefined : context?.weekMin"
                    :max="isPasse ? maxPasse : context?.weekMax"
                    class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                />
                <p class="text-[11.5px] text-ink-muted mt-1.5 min-h-[18px]">
                    {{ hintText }}
                </p>
            </div>

            <!-- Assignations — mode passé uniquement -->
            <div v-if="isPasse">
                <p
                    class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] mb-2"
                >
                    👤 Qui était de permanence ?
                </p>
                <p
                    v-if="contexte?.dejaExistant"
                    class="text-[12.5px] text-rose-600 mb-2"
                >
                    Un créneau existe déjà pour cette date.
                </p>
                <p
                    v-else-if="contexte && contexte.evenements.length > 0"
                    class="text-[12.5px] text-ink-muted mb-2"
                >
                    Événement(s) ce jour-là :
                    <template v-for="(e, i) in contexte.evenements" :key="i">
                        <strong class="text-ink">{{ e.nom }}</strong
                        ><span v-if="e.bloquant"> (bloque des tâches)</span
                        ><span v-if="i < contexte.evenements.length - 1"
                            >,
                        </span> </template
                    >. Ils seront liés au créneau.
                </p>
                <p
                    v-else-if="contexteEnCours"
                    class="text-[12.5px] text-ink-muted mb-2"
                >
                    ⏳ Vérification des événements et absences…
                </p>
                <div class="flex flex-col gap-2">
                    <label
                        v-for="code in TACHE_CODES"
                        :key="code"
                        class="grid grid-cols-[7.5rem_1fr] items-center gap-2 text-[13px] text-ink"
                    >
                        <span class="font-semibold">{{
                            TACHES_META[code].label
                        }}</span>
                        <span
                            v-if="tacheBloqueePar(code)"
                            class="px-3 py-2 border-[1.5px] border-dashed border-ink-faint rounded-lg text-[12.5px] text-ink-muted bg-surface-2"
                        >
                            🚫 Bloquée — {{ tacheBloqueePar(code) }}
                        </span>
                        <select
                            v-else
                            v-model="choix[code]"
                            class="w-full px-3 py-2 border-[1.5px] border-ink-faint rounded-lg text-[14px] font-body text-ink bg-surface-2 outline-none transition cursor-pointer focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                        >
                            <option value="">— Non assignée —</option>
                            <option
                                v-for="p in personnes"
                                :key="p.id"
                                :value="String(p.id)"
                            >
                                {{ libellePersonne(p) }}
                            </option>
                        </select>
                    </label>
                </div>
                <p
                    v-if="absentsChoisis.length > 0"
                    class="mt-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-[12.5px] text-amber-800"
                >
                    ⚠️ Déclaré·e absent·e ce jour-là :
                    <strong>{{ absentsChoisis.join(", ") }}</strong>. Vous
                    pouvez quand même l'assigner.
                </p>
                <p class="text-[11.5px] text-ink-muted mt-1.5">
                    Facultatif — seules les personnes actives au planning sont
                    proposées. Une tâche laissée vide reste non assignée.
                </p>
            </div>

            <div class="flex gap-2">
                <button
                    @click="submit"
                    :disabled="creating || contexte?.dejaExistant === true"
                    class="btn-touch flex-1 px-4 py-2.5 bg-accent hover:bg-accent-dark text-white text-[13px] font-bold rounded-lg shadow-[0_3px_12px_rgba(3,105,161,0.3)] transition-all cursor-pointer flex items-center justify-center gap-1.5 disabled:opacity-50"
                >
                    {{
                        creating
                            ? "⏳ Création…"
                            : isPasse
                              ? "➕ Créer le créneau passé"
                              : "➕ Créer le créneau"
                    }}
                </button>
                <button
                    @click="close"
                    class="btn-touch px-4 py-2.5 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors cursor-pointer"
                >
                    Annuler
                </button>
            </div>
        </div>
    </Modal>
</template>
