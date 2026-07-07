<!-- resources/js/components/shared/Toast.vue -->
<!--
    Composant d'affichage des toasts.

    ── Structure d'un Single File Component (SFC) Vue ────────────────────────
    Un fichier .vue a trois blocs optionnels :
      <script setup>  →  logique TypeScript (réactivité, props, events)
      <template>      →  HTML déclaratif (peut utiliser les variables du script)
      <style scoped>  →  CSS isolé à ce composant (optionnel, on préfère Tailwind)

    ── Rôle de ce composant ──────────────────────────────────────────────────
    Toast.vue ne "possède" pas les toasts — il se contente de les afficher.
    L'état vit dans useToast() (module-level), donc n'importe quel composant
    peut appeler useToast().success('...') et Toast.vue se mettra à jour
    automatiquement, même sans prop ni event entre eux.

    Ce composant doit être monté UNE SEULE FOIS, dans un layout ou en
    island indépendant. On va l'inclure via app.ts → mount #vue-toast.
-->
<script setup lang="ts">
// "lang="ts"" : active TypeScript dans ce bloc script.

import { useToast } from '@/composables/useToast';

// On récupère la liste réactive des toasts et la fonction dismiss.
// On n'a pas besoin de show/success/error ici — ce composant affiche, pas déclenche.
const { toasts, dismiss } = useToast();
</script>

<template>
    <!--
        Conteneur fixe en bas à droite — identique au <div id="toastContainer">
        actuellement en dur dans les Blade. Une fois ce composant monté,
        les divs toastContainer des Blade pourront être supprimés.

        pointer-events-none sur le conteneur + pointer-events-auto sur chaque
        toast : le fond ne capture pas les clics, mais les boutons du toast si.
    -->
    <div
        class="fixed bottom-5 right-5 z-[500] flex flex-col gap-2 pointer-events-none"
        aria-live="polite"
        aria-atomic="false"
    >
        <!--
            <TransitionGroup> : composant Vue natif qui anime les entrées/sorties
            d'une liste. name="toast" génère des classes CSS qu'on définit plus bas :
              .toast-enter-active, .toast-leave-active  →  transition en cours
              .toast-enter-from, .toast-leave-to        →  état initial/final
            move-class : anime les toasts qui "remontent" quand un autre disparaît.

            :key="toast.id" : Vue utilise cette clé pour identifier chaque élément
            de la liste et appliquer la bonne animation à chaque entrée/sortie.
            Sans key (ou avec key basée sur l'index), Vue réutiliserait les DOM
            nodes et les animations seraient cassées.
        -->
        <TransitionGroup name="toast" tag="div" class="flex flex-col gap-2" move-class="toast-move">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto flex items-center gap-2.5 bg-ink text-white
                       px-4 py-3 rounded-xl shadow-lg border-l-[3px] text-[13px]
                       font-medium min-w-[240px] max-w-[360px] cursor-pointer"
                :class="toast.type === 'success' ? 'border-l-emerald-400' : 'border-l-rose-400'"
                @click="dismiss(toast.id)"
                role="alert"
            >
                <span>{{ toast.type === 'success' ? '✅' : '❌' }}</span>
                <span class="flex-1">{{ toast.message }}</span>
                <!-- Bouton de fermeture explicite (accessibilité) -->
                <button
                    class="ml-1 opacity-60 hover:opacity-100 transition-opacity bg-transparent
                           border-0 text-white cursor-pointer text-base leading-none p-0"
                    @click.stop="dismiss(toast.id)"
                    aria-label="Fermer"
                >×</button>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
/*
    Animations pour <TransitionGroup name="toast">.
    Vue applique ces classes automatiquement selon le cycle de vie de chaque élément.

    Pourquoi <style scoped> ici et pas Tailwind ?
    Les classes de transition Vue (.toast-enter-from, etc.) ne sont pas des classes
    qu'on met dans le :class du template — elles sont appliquées dynamiquement par Vue.
    Tailwind ne peut pas les scanner statiquement, donc on les écrit en CSS pur.
    "scoped" garantit qu'elles n'affectent que ce composant.
*/

/* État de départ à l'entrée : invisible + décalé vers le bas */
.toast-enter-from {
    opacity: 0;
    transform: translateY(12px) scale(0.96);
}

/* État de départ à la sortie : visible (identique à l'état normal) */
.toast-leave-from {
    opacity: 1;
    transform: translateY(0) scale(1);
}

/* État final à la sortie : invisible + décalé vers la droite */
.toast-leave-to {
    opacity: 0;
    transform: translateX(20px) scale(0.96);
}

/* Durée et easing des transitions d'entrée et de sortie */
.toast-enter-active {
    transition: opacity 0.25s ease, transform 0.25s ease;
}
.toast-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
    /* position: absolute pendant la sortie pour que les autres toasts
       "remontent" en douceur via move-class, sans saut brutal */
    position: absolute;
    right: 0;
}

/* Animation du repositionnement des toasts restants */
.toast-move {
    transition: transform 0.25s ease;
}
</style>
