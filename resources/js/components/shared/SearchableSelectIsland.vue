<!-- resources/js/components/shared/SearchableSelectIsland.vue -->
<!--
    Racine de montage (« îlot ») pour SearchableSelect de @amana/shared-ui.

    ── Pourquoi un wrapper ? ────────────────────────────────────────────────
    SearchableSelect est un composant contrôlé (v-model). Monté directement
    sur un élément Blade par createApp() dans app.ts, il n'aurait aucun
    parent pour porter sa valeur. Ce wrapper tient donc cet état local
    (initialisé depuis data-current-value) et le relie au v-model.

    Il remplace l'ancien createApp({ data, render: h(...) }) inline d'app.ts :
    un composant par fichier (règle vue/one-component-per-file), et un
    template SFC est précompilé par Vite — pas besoin de h() ni du
    compilateur de templates à l'exécution.

    SearchableSelect lui-même a été promu vers @amana/shared-ui le
    04/09/2026 (roadmap mobile §4.3/step 7) : voir
    amana_shared_ui/src/components/SearchableSelect.vue.

    placeholder et errorMessage sont optionnels : non fournis (undefined),
    ce sont les valeurs par défaut du composant partagé qui s'appliquent.
-->
<script setup lang="ts">
import { ref } from "vue";
import { SearchableSelect } from "@amana/shared-ui";

const props = defineProps<{
    apiUrl: string;
    inputName: string;
    inputId: string;
    multiple: boolean;
    /** Valeur initiale (data-current-value) : chaîne en mode simple, tableau en mode multiple. */
    initialValue: string | string[];
    placeholder?: string;
    errorMessage?: string;
}>();

// Lue une seule fois : après le montage, le composant est seul maître de sa valeur.
const value = ref<string | string[]>(props.initialValue);
</script>

<template>
    <SearchableSelect
        v-model="value"
        :api-url="apiUrl"
        :input-name="inputName"
        :input-id="inputId"
        :multiple="multiple"
        :placeholder="placeholder"
        :error-message="errorMessage"
    />
</template>
