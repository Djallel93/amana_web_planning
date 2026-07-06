<!-- resources/js/components/shared/SkeletonPlanningGrid.vue -->
<!--
    Squelette de chargement pour PlanningGrid.vue.

    Remplace le texte "⏳ Chargement du planning…" par des blocs gris animés
    approximant la forme réelle de la grille (une carte par semaine, une
    ligne par créneau) — réduit la sensation d'attente sur la vue la plus
    lourde de l'app, sans avoir besoin de connaître les données à l'avance.
-->
<script setup lang="ts">
withDefaults(defineProps<{
    semaines?: number;
    creneauxParSemaine?: number;
}>(), {
    semaines: 2,
    creneauxParSemaine: 2,
});
</script>

<template>
    <div class="flex flex-col gap-5" aria-hidden="true">
        <div
            v-for="s in semaines" :key="s"
            class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden"
        >
            <!-- En-tête de semaine -->
            <div class="px-4 py-3 border-b border-surface-3 flex items-center gap-3">
                <div class="h-3.5 w-32 rounded bg-surface-3 animate-pulse"></div>
                <div class="h-3 w-20 rounded bg-surface-3 animate-pulse ml-auto"></div>
            </div>

            <!-- Lignes de créneaux -->
            <div
                v-for="c in creneauxParSemaine" :key="c"
                class="px-4 py-3.5 border-b border-surface-3 last:border-0 flex items-center gap-3"
            >
                <div class="h-3 w-24 rounded bg-surface-3 animate-pulse flex-shrink-0"></div>
                <div class="flex gap-2 flex-1">
                    <div class="h-6 w-20 rounded-full bg-surface-3 animate-pulse"></div>
                    <div class="h-6 w-20 rounded-full bg-surface-3 animate-pulse"></div>
                    <div class="h-6 w-20 rounded-full bg-surface-3 animate-pulse hidden sm:block"></div>
                </div>
            </div>
        </div>
    </div>
</template>
