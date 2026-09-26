<!-- resources/js/components/bilan/TallyCounterModal.vue -->
<!--
    Compteur "tally" pour saisir rapidement un effectif (Présents / En ligne)
    sans taper de chiffre au clavier — ouvert depuis le bouton 🔢 posé dans
    chaque champ de la section Présences (voir BilanView.vue).

    ── Contrôlé par le parent ────────────────────────────────────────────────
    `open` + `modelValue` sont fournis par le parent (pattern déjà utilisé par
    Modal.vue / AddCreneauModal.vue). Le compteur interne (`compte`) est
    initialisé depuis `modelValue` à CHAQUE ouverture (watch sur `open`), pas
    une fois pour toutes : si le champ contenait déjà une valeur, on repart
    de là plutôt que de 0. `null` (pas de cours) est traité comme 0 au
    démarrage du compteur — le champ reste néanmoins vide tant qu'on n'a pas
    cliqué "Enregistrer".

    ── Annuler vs Enregistrer ────────────────────────────────────────────────
    `update:modelValue` n'est émis QUE sur "Enregistrer" (ou Entrée) : fermer
    via Annuler, Escape ou un clic sur le fond n'impacte jamais le champ
    d'origine, même si le compte a été modifié entre-temps.
-->
<script setup lang="ts">
import { ref, watch } from "vue";
import { Modal } from "@amana/shared-ui";

const props = defineProps<{
    open: boolean;
    modelValue: number | null;
    label: string;
}>();

const emit = defineEmits<{
    "update:modelValue": [value: number];
    close: [];
}>();

const compte = ref(0);

// Réinitialise le compteur depuis la valeur actuelle du champ à chaque
// ouverture — pas de mémoire d'une ouverture à l'autre.
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            compte.value = props.modelValue ?? 0;
        }
    },
);

// ── Retour sonore (clic) ─────────────────────────────────────────────────
// Son synthétisé via Web Audio (pas de fichier audio à ajouter au projet).
// Un seul AudioContext est réutilisé pour tous les clics du composant — en
// créer un nouveau à chaque appel fuiterait des ressources et finirait par
// être bridé par le navigateur. Il est instancié à la volée (jamais au
// chargement du module) car les navigateurs exigent un geste utilisateur
// avant d'autoriser l'audio, et le premier +/- est justement ce geste.
//
// + et − jouent le même "tic" (carrée, ~60ms) mais avec un glissando de
// fréquence inversé : montant (600→900Hz) pour +, descendant (900→600Hz)
// pour − — un son "symétrique opposé" plutôt que juste deux hauteurs
// différentes.
let audioCtx: AudioContext | null = null;

function jouerClic(direction: "up" | "down"): void {
    try {
        if (!audioCtx) {
            audioCtx = new AudioContext();
        }
        if (audioCtx.state === "suspended") {
            void audioCtx.resume();
        }

        const duree = 0.06;
        const [freqDepart, freqArrivee] = direction === "up" ? [600, 900] : [900, 600];

        const oscillateur = audioCtx.createOscillator();
        const gain = audioCtx.createGain();

        oscillateur.type = "square";
        oscillateur.frequency.setValueAtTime(freqDepart, audioCtx.currentTime);
        oscillateur.frequency.exponentialRampToValueAtTime(
            freqArrivee,
            audioCtx.currentTime + duree,
        );

        // Attaque quasi instantanée puis extinction exponentielle rapide
        // pour un "tic" sec plutôt qu'un bip qui traîne.
        gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + duree);

        oscillateur.connect(gain);
        gain.connect(audioCtx.destination);

        oscillateur.start();
        oscillateur.stop(audioCtx.currentTime + duree);
    } catch {
        // Web Audio indisponible (ancien navigateur, contexte bloqué...) —
        // le compteur reste pleinement fonctionnel, simplement sans le son.
    }
}

function decrementer(): void {
    if (compte.value > 0) compte.value -= 1;
    jouerClic("down");
}

function incrementer(): void {
    compte.value += 1;
    jouerClic("up");
}

function annuler(): void {
    emit("close");
}

function enregistrer(): void {
    emit("update:modelValue", compte.value);
    emit("close");
}
</script>

<template>
    <Modal :open="open" max-width="max-w-xs" @close="annuler">
        <template #header>
            <div
                class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0"
            >
                🔢
            </div>
            <span class="font-heading text-[14px] font-semibold text-ink">{{
                label
            }}</span>
        </template>

        <div class="flex items-center justify-center gap-5 py-2">
            <button
                type="button"
                :disabled="compte === 0"
                class="btn-touch w-12 h-12 flex items-center justify-center rounded-full border-[1.5px] border-ink-faint text-ink text-xl font-bold bg-surface-2 hover:bg-surface-3 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                aria-label="Diminuer"
                @click="decrementer"
            >
                −
            </button>

            <span
                class="w-20 text-center font-heading text-4xl font-bold text-ink tabular-nums"
            >
                {{ compte }}
            </span>

            <button
                type="button"
                class="btn-touch w-12 h-12 flex items-center justify-center rounded-full border-[1.5px] border-ink-faint text-ink text-xl font-bold bg-surface-2 hover:bg-surface-3 transition-colors cursor-pointer"
                aria-label="Augmenter"
                @click="incrementer"
            >
                +
            </button>
        </div>

        <template #footer>
            <button
                type="button"
                class="btn-touch flex-1 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-[13px] font-bold rounded-lg transition-colors cursor-pointer"
                @click="annuler"
            >
                Annuler
            </button>
            <button
                type="button"
                class="btn-touch flex-1 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-[13px] font-bold rounded-lg transition-colors cursor-pointer"
                @click="enregistrer"
            >
                Enregistrer
            </button>
        </template>
    </Modal>
</template>
