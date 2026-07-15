<!-- resources/js/components/bilan/BilanView.vue -->
<!--
    Vue racine de la page Bilan — remplace entièrement le contenu dynamique
    de bilan/index.blade.php (date picker + sections Amana food / Présences),
    en consommant GET /bilan/data et POST /bilan/data/amana-food ou
    /bilan/data/presence (BilanController), à l'image de PlanningGrid.vue
    pour /planning/data.

    ── Deux groupes, deux boutons ─────────────────────────────────────────────
    Il n'y a pas de notion de propriétaire ici : n'importe quel utilisateur
    connecté peut consulter ET modifier le bilan de n'importe quelle date.
    Amana food et Présences ont chacun leur propre bouton d'enregistrement et
    n'envoient que leurs propres champs, pour que deux personnes puissent
    éditer les deux groupes en parallèle sans que l'une écrase les valeurs
    de l'autre avec une copie obsolète. Changer de date déclenche un nouveau
    chargement ; le formulaire est pré-rempli avec les valeurs existantes
    (ou vide si rien n'a encore été saisi pour cette date).

    ── NULL vs 0 ────────────────────────────────────────────────────────────
    montantCarte/montantEspece/nbPresents/nbEnLigne sont `number | null`.
    `null` signifie "pas de cours ce jour-là" (jamais saisi, ou explicitement
    réinitialisé) — distinct de 0, une vraie valeur saisie. Les champs
    s'affichent donc VIDES (pas "0") tant qu'aucune valeur n'a été entrée.
    Chaque section a un bouton "Réinitialiser" (visible seulement pour les
    gestionnaires/admins, via `peutReinitialiser` renvoyé par le serveur)
    qui remet son groupe à NULL après confirmation — utile pour marquer
    explicitement un jour sans cours (semaine, vacances, cours annulé…).
-->
<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';

// ── Types ─────────────────────────────────────────────────────────────────
interface BilanData {
    date:                    string;
    montantCarte:            number | null;
    montantEspece:           number | null;
    nbPresents:              number | null;
    nbEnLigne:               number | null;
    existe:                  boolean;
    derniereMajFood:         string | null;
    derniereMajFoodPar:      string | null;
    derniereMajPresence:     string | null;
    derniereMajPresencePar:  string | null;
    peutReinitialiser:       boolean;
}

declare global {
    interface Window {
        BilanConfig: {
            csrf:   string;
            routes: {
                data:            string;
                storeAmanaFood:  string;
                storePresence:   string;
                resetAmanaFood:  string;
                resetPresence:   string;
            };
        };
    }
}

const toast   = useToast();
const confirm = useConfirm();

// ── État ──────────────────────────────────────────────────────────────────
function todayIso(): string {
    // Date locale (pas UTC) au format YYYY-MM-DD, cohérent avec <input type="date">.
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

const date          = ref(todayIso());
const montantCarte  = ref<number | null>(null);
const montantEspece = ref<number | null>(null);
const nbPresents    = ref<number | null>(null);
const nbEnLigne     = ref<number | null>(null);
const existe        = ref(false);
const peutReinitialiser      = ref(false);
const derniereMajFood        = ref<string | null>(null);
const derniereMajFoodPar     = ref<string | null>(null);
const derniereMajPresence    = ref<string | null>(null);
const derniereMajPresencePar = ref<string | null>(null);

type LoadState = 'idle' | 'loading' | 'loaded' | 'error';
const loadState    = ref<LoadState>('idle');
const savingFood     = ref(false);
const savingPresence = ref(false);
const resettingFood     = ref(false);
const resettingPresence = ref(false);

// ── CSRF ──────────────────────────────────────────────────────────────────
function getCsrf(): string {
    return window.BilanConfig?.csrf
        ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
        ?? '';
}

// ── Chargement ────────────────────────────────────────────────────────────
function appliquerBilan(data: BilanData): void {
    montantCarte.value   = data.montantCarte;
    montantEspece.value  = data.montantEspece;
    nbPresents.value     = data.nbPresents;
    nbEnLigne.value       = data.nbEnLigne;
    existe.value          = data.existe;
    peutReinitialiser.value      = data.peutReinitialiser;
    derniereMajFood.value        = data.derniereMajFood;
    derniereMajFoodPar.value     = data.derniereMajFoodPar;
    derniereMajPresence.value    = data.derniereMajPresence;
    derniereMajPresencePar.value = data.derniereMajPresencePar;
}

async function loadBilan(): Promise<void> {
    loadState.value = 'loading';
    try {
        const url = `${window.BilanConfig.routes.data}?date=${date.value}`;
        const res = await fetch(url, {
            headers: { 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json' },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json() as BilanData;

        appliquerBilan(data);
        loadState.value = 'loaded';
    } catch {
        loadState.value = 'error';
    }
}

// Recharge automatiquement à chaque changement de date.
watch(date, () => loadBilan());
onMounted(() => loadBilan());

// ── Enregistrement ────────────────────────────────────────────────────────
// Deux fonctions indépendantes : chaque groupe a son propre bouton et
// n'envoie que ses propres champs, pour que deux personnes puissent éditer
// Amana food et Présences en parallèle sans s'écraser l'une l'autre.
async function saveAmanaFood(): Promise<void> {
    savingFood.value = true;
    try {
        const res = await fetch(window.BilanConfig.routes.storeAmanaFood, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                date:           date.value,
                montant_carte:  montantCarte.value,
                montant_espece: montantEspece.value,
            }),
        });

        const data = await res.json() as { success: boolean; message: string; bilan?: BilanData };

        if (data.success && data.bilan) {
            appliquerBilan(data.bilan);
            toast.success(data.message);
        } else {
            toast.error(data.message || 'Erreur lors de l\'enregistrement.');
        }
    } catch {
        toast.error('Erreur réseau.');
    } finally {
        savingFood.value = false;
    }
}

async function savePresence(): Promise<void> {
    savingPresence.value = true;
    try {
        const res = await fetch(window.BilanConfig.routes.storePresence, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                date:        date.value,
                nb_presents: nbPresents.value,
                nb_en_ligne: nbEnLigne.value,
            }),
        });

        const data = await res.json() as { success: boolean; message: string; bilan?: BilanData };

        if (data.success && data.bilan) {
            appliquerBilan(data.bilan);
            toast.success(data.message);
        } else {
            toast.error(data.message || 'Erreur lors de l\'enregistrement.');
        }
    } catch {
        toast.error('Erreur réseau.');
    } finally {
        savingPresence.value = false;
    }
}

// ── Réinitialisation ──────────────────────────────────────────────────────
// Remet un groupe à NULL ("pas de cours ce jour-là") — réservé aux
// gestionnaires/admins. Confirmation obligatoire : action destructive qui
// écrase les valeurs déjà saisies pour cette date.
async function resetAmanaFood(): Promise<void> {
    const ok = await confirm.ask({
        danger: true,
        title: 'Réinitialiser Amana food',
        message: `Les valeurs Amana food du ${date.value} seront effacées (marquées "pas de cours"). Cette action est irréversible.`,
        confirmLabel: 'Réinitialiser',
    });
    if (!ok) return;

    resettingFood.value = true;
    try {
        const res = await fetch(window.BilanConfig.routes.resetAmanaFood, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ date: date.value }),
        });

        const data = await res.json() as { success: boolean; message: string; bilan?: BilanData };

        if (data.success && data.bilan) {
            appliquerBilan(data.bilan);
            toast.success(data.message);
        } else {
            toast.error(data.message || 'Erreur lors de la réinitialisation.');
        }
    } catch {
        toast.error('Erreur réseau.');
    } finally {
        resettingFood.value = false;
    }
}

async function resetPresence(): Promise<void> {
    const ok = await confirm.ask({
        danger: true,
        title: 'Réinitialiser Présences',
        message: `Les valeurs Présences du ${date.value} seront effacées (marquées "pas de cours"). Cette action est irréversible.`,
        confirmLabel: 'Réinitialiser',
    });
    if (!ok) return;

    resettingPresence.value = true;
    try {
        const res = await fetch(window.BilanConfig.routes.resetPresence, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ date: date.value }),
        });

        const data = await res.json() as { success: boolean; message: string; bilan?: BilanData };

        if (data.success && data.bilan) {
            appliquerBilan(data.bilan);
            toast.success(data.message);
        } else {
            toast.error(data.message || 'Erreur lors de la réinitialisation.');
        }
    } catch {
        toast.error('Erreur réseau.');
    } finally {
        resettingPresence.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-5">

        <!-- ── Sélecteur de date ── -->
        <div class="bg-surface rounded-xl border border-surface-border shadow-sm px-5 py-4 flex flex-wrap items-center gap-4">
            <label for="bilan_date" class="text-xs font-bold text-ink tracking-[0.2px]">📅 Date</label>
            <input
                id="bilan_date" type="date" v-model="date"
                class="px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                       focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
            >

            <span v-if="loadState === 'loaded' && !existe" class="text-[12.5px] text-ink-muted">
                Aucun bilan enregistré pour cette date — les champs sont vides.
            </span>
        </div>

        <!-- ── Chargement / erreur ── -->
        <div v-if="loadState === 'loading'" class="text-center py-10 text-[13.5px] text-ink-muted">
            ⏳ Chargement du bilan…
        </div>
        <div v-else-if="loadState === 'error'" class="text-center py-8 text-rose-600 text-[13px]">
            ❌ Erreur lors du chargement du bilan.
        </div>

        <!-- ── Formulaire ── -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <!-- Amana food -->
            <div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
                <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
                    <div class="w-7 h-7 bg-emerald-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">🍽️</div>
                    <span class="font-heading text-[14px] font-semibold text-ink">Amana food</span>
                </div>
                <div class="px-5 py-5 flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="montant_carte" class="text-xs font-bold text-ink tracking-[0.2px]">💳 Carte bancaire</label>
                        <input
                            id="montant_carte" type="number" min="0" step="0.01" v-model.number="montantCarte"
                            placeholder="Pas de cours"
                            class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                   focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                        >
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="montant_espece" class="text-xs font-bold text-ink tracking-[0.2px]">💵 Espèces</label>
                        <input
                            id="montant_espece" type="number" min="0" step="0.01" v-model.number="montantEspece"
                            placeholder="Pas de cours"
                            class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                   focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                        >
                    </div>

                    <span v-if="montantCarte === null && montantEspece === null" class="text-[11.5px] text-ink-muted italic">
                        Marqué comme "pas de cours" pour cette date.
                    </span>
                    <span v-if="derniereMajFoodPar" class="text-[11.5px] text-ink-muted">
                        Dernière modification par <strong>{{ derniereMajFoodPar }}</strong> le {{ derniereMajFood }}
                    </span>

                    <div class="flex items-center gap-2.5">
                        <button
                            type="button"
                            class="min-h-[44px] px-5 py-2.5 bg-accent hover:bg-accent-dark text-white font-bold text-[13px] rounded-lg
                                   shadow-[0_3px_12px_rgba(3,105,161,0.3)] hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer
                                   flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed"
                            :disabled="savingFood"
                            @click="saveAmanaFood"
                        >
                            <span v-if="savingFood">⏳ Enregistrement…</span>
                            <span v-else>💾 Enregistrer Amana food</span>
                        </button>

                        <!-- Réservé aux gestionnaires/admins (voir BilanController::resetAmanaFood). -->
                        <button
                            v-if="peutReinitialiser"
                            type="button"
                            class="min-h-[44px] px-4 py-2.5 bg-transparent hover:bg-rose-50 text-rose-600 font-bold text-[13px] rounded-lg
                                   border-[1.5px] border-rose-200 hover:border-rose-300 transition-all cursor-pointer
                                   flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed"
                            :disabled="resettingFood"
                            @click="resetAmanaFood"
                        >
                            <span v-if="resettingFood">⏳…</span>
                            <span v-else>♻️ Réinitialiser</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Présences -->
            <div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
                <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
                    <div class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">👥</div>
                    <span class="font-heading text-[14px] font-semibold text-ink">Présences</span>
                </div>
                <div class="px-5 py-5 flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="nb_presents" class="text-xs font-bold text-ink tracking-[0.2px]">🧍 Présents</label>
                        <input
                            id="nb_presents" type="number" min="0" step="1" v-model.number="nbPresents"
                            placeholder="Pas de cours"
                            class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                   focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                        >
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="nb_en_ligne" class="text-xs font-bold text-ink tracking-[0.2px]">💻 En ligne</label>
                        <input
                            id="nb_en_ligne" type="number" min="0" step="1" v-model.number="nbEnLigne"
                            placeholder="Pas de cours"
                            class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                   focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                        >
                    </div>

                    <span v-if="nbPresents === null && nbEnLigne === null" class="text-[11.5px] text-ink-muted italic">
                        Marqué comme "pas de cours" pour cette date.
                    </span>
                    <span v-if="derniereMajPresencePar" class="text-[11.5px] text-ink-muted">
                        Dernière modification par <strong>{{ derniereMajPresencePar }}</strong> le {{ derniereMajPresence }}
                    </span>

                    <div class="flex items-center gap-2.5">
                        <button
                            type="button"
                            class="min-h-[44px] px-5 py-2.5 bg-accent hover:bg-accent-dark text-white font-bold text-[13px] rounded-lg
                                   shadow-[0_3px_12px_rgba(3,105,161,0.3)] hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer
                                   flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed"
                            :disabled="savingPresence"
                            @click="savePresence"
                        >
                            <span v-if="savingPresence">⏳ Enregistrement…</span>
                            <span v-else>💾 Enregistrer Présences</span>
                        </button>

                        <!-- Réservé aux gestionnaires/admins (voir BilanController::resetPresence). -->
                        <button
                            v-if="peutReinitialiser"
                            type="button"
                            class="min-h-[44px] px-4 py-2.5 bg-transparent hover:bg-rose-50 text-rose-600 font-bold text-[13px] rounded-lg
                                   border-[1.5px] border-rose-200 hover:border-rose-300 transition-all cursor-pointer
                                   flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed"
                            :disabled="resettingPresence"
                            @click="resetPresence"
                        >
                            <span v-if="resettingPresence">⏳…</span>
                            <span v-else>♻️ Réinitialiser</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
