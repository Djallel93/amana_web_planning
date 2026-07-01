<!-- resources/js/components/bilan/BilanView.vue -->
<!--
    Vue racine de la page Bilan — remplace entièrement le contenu dynamique
    de bilan/index.blade.php (date picker + sections Amana food / Présences),
    en consommant GET/POST /bilan/data (BilanController), à l'image de
    PlanningGrid.vue pour /planning/data.

    ── Enregistrement unique et partagé par date ─────────────────────────────
    Il n'y a pas de notion de propriétaire ici : n'importe quel utilisateur
    connecté peut consulter ET modifier le bilan de n'importe quelle date.
    Changer de date déclenche un nouveau chargement ; le formulaire est
    pré-rempli avec les valeurs existantes (ou à zéro si rien n'a encore été
    saisi pour cette date).
-->
<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useToast } from '@/composables/useToast';

// ── Types ─────────────────────────────────────────────────────────────────
interface BilanData {
    date:           string;
    montantCarte:   number;
    montantEspece:  number;
    nbPresents:     number;
    nbEnLigne:      number;
    existe:         boolean;
    derniereMaj:    string | null;
    derniereMajPar: string | null;
}

declare global {
    interface Window {
        BilanConfig: {
            csrf:   string;
            routes: { data: string };
        };
    }
}

const toast = useToast();

// ── État ──────────────────────────────────────────────────────────────────
function todayIso(): string {
    // Date locale (pas UTC) au format YYYY-MM-DD, cohérent avec <input type="date">.
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

const date          = ref(todayIso());
const montantCarte  = ref(0);
const montantEspece = ref(0);
const nbPresents    = ref(0);
const nbEnLigne     = ref(0);
const existe        = ref(false);
const derniereMaj   = ref<string | null>(null);
const derniereMajPar = ref<string | null>(null);

type LoadState = 'idle' | 'loading' | 'loaded' | 'error';
const loadState  = ref<LoadState>('idle');
const saving     = ref(false);

// ── CSRF ──────────────────────────────────────────────────────────────────
function getCsrf(): string {
    return window.BilanConfig?.csrf
        ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
        ?? '';
}

// ── Chargement ────────────────────────────────────────────────────────────
async function loadBilan(): Promise<void> {
    loadState.value = 'loading';
    try {
        const url = `${window.BilanConfig.routes.data}?date=${date.value}`;
        const res = await fetch(url, {
            headers: { 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json' },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json() as BilanData;

        montantCarte.value   = data.montantCarte;
        montantEspece.value  = data.montantEspece;
        nbPresents.value     = data.nbPresents;
        nbEnLigne.value      = data.nbEnLigne;
        existe.value         = data.existe;
        derniereMaj.value    = data.derniereMaj;
        derniereMajPar.value = data.derniereMajPar;

        loadState.value = 'loaded';
    } catch {
        loadState.value = 'error';
    }
}

// Recharge automatiquement à chaque changement de date.
watch(date, () => loadBilan());
onMounted(() => loadBilan());

// ── Enregistrement ────────────────────────────────────────────────────────
async function save(): Promise<void> {
    saving.value = true;
    try {
        const res = await fetch(window.BilanConfig.routes.data, {
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
                nb_presents:    nbPresents.value,
                nb_en_ligne:    nbEnLigne.value,
            }),
        });

        const data = await res.json() as { success: boolean; message: string; bilan?: BilanData };

        if (data.success && data.bilan) {
            existe.value         = data.bilan.existe;
            derniereMaj.value    = data.bilan.derniereMaj;
            derniereMajPar.value = data.bilan.derniereMajPar;
            toast.success(data.message);
        } else {
            toast.error(data.message || 'Erreur lors de l\'enregistrement.');
        }
    } catch {
        toast.error('Erreur réseau.');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-5">

        <!-- ── Sélecteur de date ── -->
        <div class="bg-white rounded-xl border border-surface-border shadow-sm px-5 py-4 flex flex-wrap items-center gap-4">
            <label for="bilan_date" class="text-xs font-bold text-ink tracking-[0.2px]">📅 Date</label>
            <input
                id="bilan_date" type="date" v-model="date"
                class="px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                       focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
            >

            <span v-if="loadState === 'loaded' && !existe" class="text-[12.5px] text-ink-muted">
                Aucun bilan enregistré pour cette date — les champs sont à zéro.
            </span>
            <span v-else-if="loadState === 'loaded' && existe && derniereMajPar" class="text-[12.5px] text-ink-muted">
                Dernière modification par <strong>{{ derniereMajPar }}</strong> le {{ derniereMaj }}
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
            <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden">
                <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
                    <div class="w-7 h-7 bg-emerald-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">🍽️</div>
                    <span class="font-heading text-[14px] font-semibold text-ink">Amana food</span>
                </div>
                <div class="px-5 py-5 flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="montant_carte" class="text-xs font-bold text-ink tracking-[0.2px]">💳 Carte bancaire</label>
                        <input
                            id="montant_carte" type="number" min="0" step="0.01" v-model.number="montantCarte"
                            class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                   focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                        >
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="montant_espece" class="text-xs font-bold text-ink tracking-[0.2px]">💵 Espèces</label>
                        <input
                            id="montant_espece" type="number" min="0" step="0.01" v-model.number="montantEspece"
                            class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                   focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                        >
                    </div>
                </div>
            </div>

            <!-- Présences -->
            <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden">
                <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
                    <div class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">👥</div>
                    <span class="font-heading text-[14px] font-semibold text-ink">Présences</span>
                </div>
                <div class="px-5 py-5 flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="nb_presents" class="text-xs font-bold text-ink tracking-[0.2px]">🧍 Présents</label>
                        <input
                            id="nb_presents" type="number" min="0" step="1" v-model.number="nbPresents"
                            class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                   focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                        >
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="nb_en_ligne" class="text-xs font-bold text-ink tracking-[0.2px]">💻 En ligne</label>
                        <input
                            id="nb_en_ligne" type="number" min="0" step="1" v-model.number="nbEnLigne"
                            class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                   focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
                        >
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Enregistrer ── -->
        <div v-if="loadState === 'loaded'">
            <button
                type="button"
                class="min-h-[48px] px-5 py-3 bg-accent hover:bg-accent-dark text-white font-bold text-[13.5px] rounded-lg
                       shadow-[0_3px_12px_rgba(3,105,161,0.3)] hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer
                       flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed"
                :disabled="saving"
                @click="save"
            >
                <span v-if="saving">⏳ Enregistrement…</span>
                <span v-else>💾 Enregistrer le bilan</span>
            </button>
        </div>
    </div>
</template>
