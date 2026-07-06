<!-- resources/js/components/shared/OfflineBanner.vue -->
<!--
    Bannière fixe affichée quand le navigateur détecte une perte de
    connexion (navigator.onLine + événements online/offline).

    Monté une seule fois dans le layout principal (voir app.ts,
    #vue-offline-banner) — visible sur toutes les pages, comme Toast.vue.

    Objectif : éviter qu'un volontaire consultant son planning en mobilité
    (avant d'arriver à l'association, par exemple) se retrouve face à une
    erreur réseau sans comprendre pourquoi — la bannière explique la cause
    plutôt que de laisser l'état d'erreur générique des vues faire deviner.
-->
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

const isOffline = ref(!navigator.onLine);

function onOnline(): void { isOffline.value = false; }
function onOffline(): void { isOffline.value = true; }

onMounted(() => {
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);
});

onUnmounted(() => {
    window.removeEventListener('online', onOnline);
    window.removeEventListener('offline', onOffline);
});
</script>

<template>
    <Transition name="offline-banner">
        <div
            v-if="isOffline"
            role="status"
            aria-live="polite"
            class="fixed top-0 inset-x-0 z-[500] bg-amber-500 text-white text-[12.5px] font-semibold
                   text-center py-2 px-4 shadow-md"
        >
            📡 Connexion internet perdue — certaines actions peuvent échouer jusqu'au retour du réseau.
        </div>
    </Transition>
</template>

<style scoped>
.offline-banner-enter-active,
.offline-banner-leave-active {
    transition: transform 0.2s ease, opacity 0.2s ease;
}
.offline-banner-enter-from,
.offline-banner-leave-to {
    transform: translateY(-100%);
    opacity: 0;
}
</style>
