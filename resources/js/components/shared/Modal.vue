<!-- resources/js/components/shared/Modal.vue -->
<!--
    Composant modal générique et réutilisable.

    ── Stratégie de conception ───────────────────────────────────────────────
    Ce composant fournit UNIQUEMENT la coquille : backdrop, conteneur centré,
    animation, fermeture par Escape/clic extérieur, gestion du focus.
    Le CONTENU (header, body, footer) est fourni par le parent via des slots.

    Chaque modal de l'app (AssignModal, SwapModal…) inclura <Modal> et mettra
    son propre HTML dans les slots — pas besoin de dupliquer le backdrop.

    ── Props & Emits ─────────────────────────────────────────────────────────
    Props : données que le PARENT envoie vers le composant enfant (sens ↓).
    Emits : événements que l'ENFANT remonte vers le parent (sens ↑).

    Ici :
      Prop  `open`      : booléen, le parent contrôle si le modal est visible.
      Emit  `close`     : le modal signale au parent qu'on veut fermer
                          (Escape, clic backdrop, bouton ×).
      Le parent réagit à @close en appelant modal.close() de son côté.
    C'est le pattern "controlled component" — l'état reste dans le parent.

    ── Slots ─────────────────────────────────────────────────────────────────
    <slot name="header"> : zone titre + bouton ×
    <slot>               : corps du modal (slot par défaut, sans name)
    <slot name="footer"> : boutons d'action (optionnel)
-->
<script setup lang="ts">
import { watch, onMounted, onUnmounted, useTemplateRef } from 'vue';

// ── Props ─────────────────────────────────────────────────────────────────
// defineProps<{...}>() : macro Vue qui déclare les props avec leur type TS.
// Pas besoin d'import — c'est une macro compilée par le plugin Vite.
const props = defineProps<{
    open: boolean;
    // maxWidth optionnel (valeur par défaut dans le template).
    // Le "?" signifie que la prop est optionnelle — le parent peut l'omettre.
    maxWidth?: string;
}>();

// ── Emits ─────────────────────────────────────────────────────────────────
// defineEmits<{...}>() : déclare les événements que ce composant peut émettre.
// La syntaxe { close: [] } dit : l'événement "close" ne transporte pas de donnée.
// On écrirait { select: [value: number] } si on voulait passer une valeur.
const emit = defineEmits<{
    close: [];
}>();

// ── Ref sur l'élément DOM du conteneur modal ──────────────────────────────
// useTemplateRef('modal-container') est lié au ref="modal-container" dans le template.
// Il permet d'accéder à l'élément DOM réel pour la gestion du focus.
const containerRef = useTemplateRef<HTMLDivElement>('modal-container');

// ── Fermeture par touche Escape ───────────────────────────────────────────
function onKeydown(e: KeyboardEvent): void {
    if (e.key === 'Escape' && props.open) emit('close');
}

onMounted(()  => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));

// ── Focus automatique à l'ouverture ──────────────────────────────────────
// watch() observe une valeur réactive et exécute une fonction quand elle change.
// Ici : dès que `open` passe à true, on donne le focus au conteneur.
// { flush: 'post' } : attend que Vue ait mis à jour le DOM avant d'exécuter —
// sinon containerRef.value ne serait pas encore visible.
watch(() => props.open, (isOpen) => {
    if (isOpen) {
        setTimeout(() => containerRef.value?.focus(), 50);
    }
}, { flush: 'post' });
</script>

<template>
    <!--
        <Teleport to="body"> : Monte ce composant directement dans <body>
        plutôt que là où il est inclus dans le DOM.
        Pourquoi ? Un modal dans un div avec overflow:hidden ou z-index faible
        serait tronqué/masqué. Teleport garantit que le backdrop est toujours
        au-dessus de tout le reste, quelle que soit la structure parente.
    -->
    <Teleport to="body">
        <!--
            <Transition> anime l'entrée/sortie du modal dans son ensemble.
            v-if="open" : le modal n'existe pas du tout dans le DOM quand fermé
            (contrairement à v-show qui le cache avec display:none).
            v-if est préférable ici : reset du scroll interne, libération mémoire.
        -->
        <Transition name="modal">
            <div
                v-if="open"
                class="fixed inset-0 bg-black/45 backdrop-blur-sm z-[400]
                       flex items-center justify-center p-4"
                @click.self="emit('close')"
                aria-modal="true"
                role="dialog"
            >
                <!--
                    .self : le @click ne se déclenche QUE si on clique sur ce div
                    exactement — pas sur ses enfants (le contenu du modal).
                    Sans .self, cliquer n'importe où dans le modal le fermerait.

                    tabindex="-1" : l'élément peut recevoir le focus programmatiquement
                    (via .focus()) mais n'est pas dans la navigation Tab naturelle.
                    Nécessaire pour que onKeydown fonctionne.
                -->
                <div
                    ref="modal-container"
                    class="bg-surface rounded-2xl shadow-lg w-full transform outline-none"
                    :class="maxWidth ?? 'max-w-sm'"
                    tabindex="-1"
                >
                    <!-- Slot header : le parent met son titre + bouton × ici -->
                    <div v-if="$slots.header" class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
                        <slot name="header" />
                        <!-- Bouton × par défaut dans le header -->
                        <button
                            class="ml-auto w-8 h-8 flex items-center justify-center rounded-md
                                   text-ink-muted hover:bg-surface-3 hover:text-ink transition-colors
                                   bg-transparent border-0 cursor-pointer text-lg leading-none
                                   min-h-[44px] min-w-[44px]"
                            @click="emit('close')"
                            aria-label="Fermer"
                        >×</button>
                    </div>

                    <!-- Slot par défaut : corps du modal -->
                    <div class="px-5 py-4">
                        <slot />
                    </div>

                    <!-- Slot footer : boutons d'action (optionnel) -->
                    <div v-if="$slots.footer" class="px-5 pb-4 flex gap-2 justify-end">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* Animation du backdrop + conteneur à l'entrée/sortie */
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
.modal-enter-from .bg-surface,
.modal-leave-to .bg-surface {
    transform: scale(0.95) translateY(8px);
}
.modal-enter-active {
    transition: opacity 0.2s ease;
}
.modal-enter-active .bg-surface {
    transition: transform 0.2s ease;
}
.modal-leave-active {
    transition: opacity 0.15s ease;
}
.modal-leave-active .bg-surface {
    transition: transform 0.15s ease;
}
</style>
